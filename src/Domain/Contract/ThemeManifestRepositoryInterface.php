<?php

declare(strict_types=1);

namespace Semitexa\Theme\Domain\Contract;

use Semitexa\Theme\Exception\InvalidThemeConfigException;
use Semitexa\Theme\Model\ThemeManifest;

/**
 * Source of truth for theme manifests (replaces ThemeRulesRepositoryInterface).
 *
 * v1 ships a filesystem-backed implementation that scans packages/ and
 * src/theme/ for `theme.json` files. A future DB-backed implementation
 * for per-tenant admin editing would implement this same interface.
 *
 * `load()` is expected to be cached within a request and validated at
 * first call:
 *  - every manifest has a unique id
 *  - every `extends` target exists
 *  - no cycles in the extends chain
 *  - exactly one manifest has `activeWhen.always = true` (root marker)
 *  - every referenced skin slug exists in SkinDiscovery
 */
interface ThemeManifestRepositoryInterface
{
    /**
     * @return list<ThemeManifest>
     * @throws InvalidThemeConfigException
     */
    public function load(): array;

    /** @throws InvalidThemeConfigException */
    public function findRoot(): ThemeManifest;

    /** @throws InvalidThemeConfigException */
    public function findById(string $id): ?ThemeManifest;

    /**
     * Walk the extends chain from $id up to the root (inclusive of both).
     *
     * @return list<ThemeManifest> ordered from leaf to root
     * @throws InvalidThemeConfigException
     */
    public function chainOf(string $id): array;
}
