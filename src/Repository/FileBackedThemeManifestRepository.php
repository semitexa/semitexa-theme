<?php

declare(strict_types=1);

namespace Semitexa\Theme\Repository;

use Semitexa\Theme\Contract\SkinDiscoveryInterface;
use Semitexa\Theme\Contract\ThemeManifestRepositoryInterface;
use Semitexa\Theme\Exception\InvalidThemeConfigException;
use Semitexa\Theme\Model\ActiveWhen;
use Semitexa\Theme\Model\SkinCondition;
use Semitexa\Theme\Model\SkinSelection;
use Semitexa\Theme\Model\ThemeManifest;

/**
 * Filesystem-backed manifest repository.
 *
 * Scan order (all merged; id collisions are fatal):
 *  1. `vendor/semitexa/<pkg>/theme.json`    (composer-installed themes — production)
 *  2. `packages/<vendor>-<pkg>/theme.json`  (path-repo themes — monorepo dev)
 *  3. `src/theme/<id>/theme.json`           (project-owned themes)
 *
 * The `vendor/` and `packages/` branches discover the same kind of artifact
 * (a packaged theme) but at the two locations Composer can place it: under
 * `vendor/` after a normal `composer install`, or under `packages/` when the
 * theme is consumed via a path repository (the monorepo dev layout). Both
 * scans must run because the same project can have a mix.
 *
 * Validation (all fatal via InvalidThemeConfigException):
 *  - every manifest has a non-empty unique `id`
 *  - every `extends` target exists in the set
 *  - no cycles in the extends chain (DFS-based detection)
 *  - exactly one manifest declares `active_when.always = true` (root)
 *  - `active_when` that is not `always` has at least one filter axis set
 *  - every referenced skin slug exists in SkinDiscovery
 *
 * Parse/validation runs once and is cached for the repo instance's
 * lifetime. Worker reuses the cache until restart — same behavior as
 * the previous central-file repository.
 */
final class FileBackedThemeManifestRepository implements ThemeManifestRepositoryInterface
{
    /** @var list<ThemeManifest>|null */
    private ?array $cached = null;

    public function __construct(
        private readonly string $projectRoot,
        private readonly SkinDiscoveryInterface $skins,
        /** @var list<string>|null null = skip locale validation entirely */
        private readonly ?array $knownLocales = null,
        private readonly bool $allowUnknownLocales = false,
    ) {
    }

    public function load(): array
    {
        if ($this->cached !== null) {
            return $this->cached;
        }

        $manifests = [];
        foreach ($this->discover() as $location) {
            $raw = $this->readJson($location['file']);
            $manifests[] = $this->parseManifest($raw, $location);
        }

        $this->validate($manifests);

        return $this->cached = $manifests;
    }

    public function findRoot(): ThemeManifest
    {
        foreach ($this->load() as $m) {
            if ($m->activeWhen->isRootFallback()) {
                return $m;
            }
        }
        throw new InvalidThemeConfigException(
            'No root theme found — exactly one manifest must declare "active_when": {"always": true}'
        );
    }

    public function findById(string $id): ?ThemeManifest
    {
        foreach ($this->load() as $m) {
            if ($m->id === $id) {
                return $m;
            }
        }
        return null;
    }

    public function chainOf(string $id): array
    {
        $chain = [];
        $current = $this->findById($id);
        if ($current === null) {
            throw new InvalidThemeConfigException("Theme '{$id}' not found");
        }

        $seen = [];
        while ($current !== null) {
            if (isset($seen[$current->id])) {
                throw new InvalidThemeConfigException(
                    "Cycle detected in extends chain at theme '{$current->id}'"
                );
            }
            $seen[$current->id] = true;
            $chain[] = $current;
            if ($current->extends === null) {
                break;
            }
            $next = $this->findById($current->extends);
            if ($next === null) {
                throw new InvalidThemeConfigException(
                    "Theme '{$current->id}' extends '{$current->extends}' which is not installed"
                );
            }
            $current = $next;
        }
        return $chain;
    }

    /**
     * @return list<array{file: string, dir: string, source: string, inferred_id: string}>
     */
    private function discover(): array
    {
        $out = [];
        $seen = [];
        $seenPackagedThemeKeys = [];

        // Composer-installed packages (production layout). Scoped to the
        // semitexa vendor namespace, mirroring ThemeDiscovery::packageRoots().
        foreach ((array) @glob($this->projectRoot . '/vendor/semitexa/*/theme.json') as $file) {
            $file = (string) $file;
            $real = (string) (realpath($file) ?: $file);
            $packageKey = 'semitexa-' . basename(dirname($file));
            if (isset($seen[$real]) || isset($seenPackagedThemeKeys[$packageKey])) {
                continue;
            }
            $seen[$real] = true;
            $seenPackagedThemeKeys[$packageKey] = true;
            $out[] = [
                'file' => $file,
                'dir' => dirname($file),
                'source' => 'vendor',
                'inferred_id' => '',
            ];
        }

        // Path-repo packages (monorepo dev layout). Composer's `path` repo with
        // `symlink: true` makes vendor/<pkg> point at packages/<pkg>, so the
        // realpath dedup above keeps a manifest from being parsed twice.
        foreach ((array) @glob($this->projectRoot . '/packages/*/theme.json') as $file) {
            $file = (string) $file;
            $real = (string) (realpath($file) ?: $file);
            $packageKey = basename(dirname($file));
            if (isset($seen[$real]) || isset($seenPackagedThemeKeys[$packageKey])) {
                continue;
            }
            $seen[$real] = true;
            $seenPackagedThemeKeys[$packageKey] = true;
            $out[] = [
                'file' => $file,
                'dir' => dirname($file),
                'source' => 'package',
                'inferred_id' => '', // packages declare id explicitly; dir name is not used
            ];
        }

        // Project-owned themes (always last so packaged themes can be overridden
        // by a same-id project theme — though id collisions are still fatal in
        // validate(); this ordering only affects the duplicate error's "found
        // at" message).
        foreach ((array) @glob($this->projectRoot . '/src/theme/*/theme.json') as $file) {
            $file = (string) $file;
            $real = (string) (realpath($file) ?: $file);
            if (isset($seen[$real])) {
                continue;
            }
            $seen[$real] = true;
            $out[] = [
                'file' => $file,
                'dir' => dirname($file),
                'source' => 'project',
                'inferred_id' => basename(dirname($file)), // project themes allow dir-name = id
            ];
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    private function readJson(string $path): array
    {
        $raw = (string) @file_get_contents($path);
        if ($raw === '') {
            throw new InvalidThemeConfigException("Theme manifest empty or unreadable: {$path}");
        }
        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            throw new InvalidThemeConfigException("Theme manifest is not a JSON object: {$path}");
        }
        return $decoded;
    }

    /**
     * @param array<string, mixed> $raw
     * @param array{file: string, dir: string, source: 'vendor'|'package'|'project', inferred_id: string} $location
     */
    private function parseManifest(array $raw, array $location): ThemeManifest
    {
        $where = $location['file'];

        $id = $raw['id'] ?? $location['inferred_id'];
        if (! is_string($id) || $id === '') {
            throw new InvalidThemeConfigException("{$where}: manifest missing 'id'");
        }

        $extends = $raw['extends'] ?? null;
        if ($extends !== null && (! is_string($extends) || $extends === '')) {
            throw new InvalidThemeConfigException("{$where}: 'extends' must be a non-empty string or null");
        }

        $activeWhenRaw = $raw['active_when'] ?? null;
        if (! is_array($activeWhenRaw)) {
            throw new InvalidThemeConfigException("{$where}: 'active_when' must be an object");
        }
        $activeWhen = ActiveWhen::fromArray($activeWhenRaw);
        if (! $activeWhen->always
            && $activeWhen->tenant === null
            && $activeWhen->domain === null
            && $activeWhen->locale === null
        ) {
            throw new InvalidThemeConfigException(
                "{$where}: 'active_when' must declare either 'always: true' OR at least one of tenant/domain/locale"
            );
        }
        $this->validateLocale($activeWhen->locale, $where);

        $skin = $this->parseSkin($raw['skin'] ?? null, $where);

        return new ThemeManifest(
            id: $id,
            extends: $extends,
            activeWhen: $activeWhen,
            skin: $skin,
            path: $location['dir'],
            source: $location['source'],
        );
    }

    private function parseSkin(mixed $raw, string $where): SkinSelection
    {
        if (is_string($raw) && $raw !== '') {
            return SkinSelection::fixed($raw);
        }
        if (is_array($raw)) {
            $default = $raw['default'] ?? null;
            if (! is_string($default) || $default === '') {
                throw new InvalidThemeConfigException("{$where}: 'skin.default' must be a non-empty string");
            }
            $conditions = [];
            foreach ((array) ($raw['when'] ?? []) as $idx => $condRaw) {
                if (! is_array($condRaw)) {
                    throw new InvalidThemeConfigException("{$where}: skin.when[{$idx}] must be an object");
                }
                $use = $condRaw['use'] ?? null;
                if (! is_string($use) || $use === '') {
                    throw new InvalidThemeConfigException("{$where}: skin.when[{$idx}].use must be a non-empty string");
                }
                $awRaw = $condRaw;
                unset($awRaw['use']);
                $aw = ActiveWhen::fromArray($awRaw);
                if ($aw->always
                    || ($aw->tenant === null && $aw->domain === null && $aw->locale === null)
                ) {
                    throw new InvalidThemeConfigException(
                        "{$where}: skin.when[{$idx}] must declare at least one of tenant/domain/locale"
                    );
                }
                $this->validateLocale($aw->locale, "{$where} skin.when[{$idx}]");
                $conditions[] = new SkinCondition($aw, $use);
            }
            return new SkinSelection($default, $conditions);
        }
        throw new InvalidThemeConfigException("{$where}: 'skin' must be a string or an object");
    }

    private function validateLocale(?string $locale, string $where): void
    {
        if ($locale === null) {
            return;
        }
        if ($this->knownLocales === null || $this->allowUnknownLocales) {
            return;
        }
        if (! in_array($locale, $this->knownLocales, true)) {
            throw new InvalidThemeConfigException(
                "{$where}: locale '{$locale}' is not in the known locale list"
            );
        }
    }

    /**
     * @param list<ThemeManifest> $manifests
     */
    private function validate(array $manifests): void
    {
        if ($manifests === []) {
            throw new InvalidThemeConfigException(
                'No theme manifests discovered. Add a theme.json to a package or src/theme/<id>/.'
            );
        }

        $byId = [];
        $rootCount = 0;
        foreach ($manifests as $m) {
            if (isset($byId[$m->id])) {
                throw new InvalidThemeConfigException(
                    "Duplicate theme id '{$m->id}' (found at {$byId[$m->id]->path} and {$m->path})"
                );
            }
            $byId[$m->id] = $m;
            if ($m->activeWhen->isRootFallback()) {
                $rootCount++;
            }
        }

        if ($rootCount === 0) {
            throw new InvalidThemeConfigException(
                'No root theme — exactly one manifest must declare "active_when": {"always": true}'
            );
        }
        if ($rootCount > 1) {
            throw new InvalidThemeConfigException(
                "Multiple root themes declared — only one manifest may have 'active_when.always = true' (found {$rootCount})"
            );
        }

        // extends existence + cycle detection
        $availableSkins = $this->skins->availableSlugs();
        foreach ($manifests as $m) {
            if ($m->extends !== null && ! isset($byId[$m->extends])) {
                throw new InvalidThemeConfigException(
                    "Theme '{$m->id}' extends '{$m->extends}' which is not installed"
                );
            }
            $this->detectCycle($m, $byId);

            // Every skin slug referenced must be installed.
            foreach ($m->skin->allPossibleSkins() as $slug) {
                if ($availableSkins !== [] && ! in_array($slug, $availableSkins, true)) {
                    throw new InvalidThemeConfigException(
                        "Theme '{$m->id}' references unknown skin '{$slug}'. "
                        . 'Available: ' . implode(', ', $availableSkins)
                    );
                }
            }
        }
    }

    /** @param array<string, ThemeManifest> $byId */
    private function detectCycle(ThemeManifest $start, array $byId): void
    {
        $seen = [];
        $current = $start;
        while ($current !== null) {
            if (isset($seen[$current->id])) {
                throw new InvalidThemeConfigException(
                    "Cycle in extends chain starting at theme '{$start->id}' (revisits '{$current->id}')"
                );
            }
            $seen[$current->id] = true;
            if ($current->extends === null) {
                return;
            }
            $current = $byId[$current->extends] ?? null;
            // null here means missing extends — already caught by the outer validate() loop
        }
    }
}
