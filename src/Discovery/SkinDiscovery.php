<?php

declare(strict_types=1);

namespace Semitexa\Theme\Discovery;

use Semitexa\Theme\Domain\Contract\SkinDiscoveryInterface;
use Semitexa\Theme\Domain\Model\SkinEntry;

/**
 * Filesystem-backed skin discovery.
 *
 * Two sources, in priority order (later overrides earlier when slugs collide):
 *
 *   1. `vendor/semitexa/theme/src/Application/Static/css/skins/` — framework
 *      default. Ships the single `default` skin used as the baseline when no
 *      project skin matches. (Was previously in semitexa/skins-base before
 *      the ep-ssr-theme-skin-reconciliation fold.)
 *   2. `src/skins/` — project-local. Drop a `<slug>/tokens.css` here and it
 *      is auto-discovered. Slug collisions with framework-default win (project wins).
 *
 * Both sources are served at the unified URL prefix `/assets/skins/<slug>/tokens.css`
 * by `BootProjectSkinsAssetAliasListener`, which registers a SSR asset alias
 * pointing to both dirs (project first → priority override).
 *
 * A skin is discoverable iff `<source>/<slug>/tokens.css` exists.
 *
 * The scan runs once per instance and is cached for its lifetime: the
 * resolver (ThemeResolverBootstrap) keeps one instance per worker and calls
 * find() on every request, and skins are installed files — same contract as
 * FileBackedThemeManifestRepository, whose manifests reference these slugs
 * and are likewise cached until the worker restarts.
 */
final class SkinDiscovery implements SkinDiscoveryInterface
{
    public const ASSET_URL_PREFIX = '/assets/skins';
    public const PROJECT_SKINS_DIR = '/src/skins';
    public const FRAMEWORK_SKINS_DIR = '/vendor/semitexa/theme/src/Application/Static/css/skins';

    /** @var array<string, SkinEntry>|null slug => entry, alphabetical */
    private ?array $bySlug = null;

    public function __construct(
        private readonly string $projectRoot,
    ) {
    }

    /** @return list<SkinEntry> */
    public function availableSkins(): array
    {
        return array_values($this->bySlug ??= $this->scan());
    }

    public function find(string $slug): ?SkinEntry
    {
        return ($this->bySlug ??= $this->scan())[$slug] ?? null;
    }

    /** @return list<string> */
    public function availableSlugs(): array
    {
        return array_map(static fn (SkinEntry $e) => $e->slug, $this->availableSkins());
    }

    /** @return array<string, SkinEntry> */
    private function scan(): array
    {
        $bySlug = [];
        foreach ($this->sources() as $source) {
            foreach ((array) @glob($source['root'] . '/*', GLOB_ONLYDIR) as $dir) {
                $slug = basename((string) $dir);
                $tokens = $dir . '/tokens.css';
                if (! is_file($tokens)) {
                    continue;
                }
                // Later sources win → project overrides framework.
                $bySlug[$slug] = new SkinEntry(
                    slug: $slug,
                    tokensUrl: self::ASSET_URL_PREFIX . '/' . $slug . '/tokens.css',
                    tokensFilePath: $tokens,
                    source: $source['name'],
                );
            }
        }
        ksort($bySlug);
        return $bySlug;
    }

    /**
     * Project root + skins-relative dirs that SSR should also serve under
     * `/assets/skins/`. The listener uses this list so URL + filesystem stay
     * authoritative in one place.
     *
     * @return list<string>
     */
    public function rootsForAssetAlias(): array
    {
        // Order matters: framework first (registered, lowest priority),
        // project second (registered last → prepended → highest priority).
        return array_values(array_filter([
            $this->projectRoot . self::FRAMEWORK_SKINS_DIR,
            $this->projectRoot . self::PROJECT_SKINS_DIR,
        ], static fn (string $path) => is_dir($path)));
    }

    /**
     * @return list<array{name: string, root: string}>
     */
    private function sources(): array
    {
        return [
            [
                'name' => 'framework',
                'root' => $this->projectRoot . self::FRAMEWORK_SKINS_DIR,
            ],
            [
                'name' => 'project',
                'root' => $this->projectRoot . self::PROJECT_SKINS_DIR,
            ],
        ];
    }
}
