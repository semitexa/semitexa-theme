<?php

declare(strict_types=1);

namespace Semitexa\Theme\Discovery;

use Semitexa\Theme\Contract\SkinDiscoveryInterface;
use Semitexa\Theme\Model\SkinEntry;

/**
 * Filesystem-backed skin discovery.
 *
 * v1 source: `packages/semitexa-skins-base/src/Application/Static/skins/`
 * (canonical pool, served at `/assets/skins-base/skins/<slug>/tokens.css`
 * via ssr's asset pipeline).
 *
 * Future sources (not yet implemented):
 *  - `var/skins/<slug>/tokens.css` — per-project overrides, needs a serve
 *    path to be wired up (e.g. symlinked into skins-base/Static or
 *    registered as its own asset module).
 *
 * A skin is discoverable iff `<source>/<slug>/tokens.css` exists. Slug
 * collisions resolve to the latest source in `sources()`.
 */
final class SkinDiscovery implements SkinDiscoveryInterface
{
    public function __construct(
        private readonly string $projectRoot,
    ) {
    }

    /** @return list<SkinEntry> */
    public function availableSkins(): array
    {
        $bySlug = [];
        foreach ($this->sources() as $source) {
            foreach ((array) @glob($source['root'] . '/*', GLOB_ONLYDIR) as $dir) {
                $slug = basename((string) $dir);
                $tokens = $dir . '/tokens.css';
                if (! is_file($tokens)) {
                    continue;
                }
                $bySlug[$slug] = new SkinEntry(
                    slug: $slug,
                    tokensUrl: rtrim($source['urlBase'], '/') . '/' . $slug . '/tokens.css',
                    tokensFilePath: $tokens,
                    source: $source['name'],
                );
            }
        }
        ksort($bySlug);
        return array_values($bySlug);
    }

    public function find(string $slug): ?SkinEntry
    {
        foreach ($this->availableSkins() as $entry) {
            if ($entry->slug === $slug) {
                return $entry;
            }
        }
        return null;
    }

    /** @return list<string> */
    public function availableSlugs(): array
    {
        return array_map(static fn (SkinEntry $e) => $e->slug, $this->availableSkins());
    }

    /**
     * @return list<array{name: string, root: string, urlBase: string}>
     */
    private function sources(): array
    {
        return [
            [
                'name' => 'skins-base',
                'root' => $this->projectRoot . '/packages/semitexa-skins-base/src/Application/Static/skins',
                'urlBase' => '/assets/skins-base/skins',
            ],
        ];
    }
}
