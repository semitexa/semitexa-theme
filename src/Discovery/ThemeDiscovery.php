<?php

declare(strict_types=1);

namespace Semitexa\Theme\Discovery;

/**
 * Enumerates theme packages available to the application.
 *
 * v1 convention: any `semitexa-module` package whose module name starts
 * with `theme-` is a theme. Later this tightens to a dedicated
 * `semitexa-theme` composer type or `extra.semitexa-module.kind = theme`
 * marker — keeping the lookup in one place so the upgrade is local.
 *
 * Returns the short module alias (e.g. 'theme-sky'), not the composer
 * name. The alias is what appears in var/config/themes.json rules.
 */
final class ThemeDiscovery
{
    public function __construct(
        private readonly string $projectRoot,
    ) {
    }

    /** @return list<string> */
    public function availableThemes(): array
    {
        $themes = [];
        foreach ($this->packageRoots() as $root) {
            $composer = $root . '/composer.json';
            if (! is_file($composer)) {
                continue;
            }
            $data = json_decode((string) @file_get_contents($composer), true);
            if (! is_array($data)) {
                continue;
            }
            if (($data['type'] ?? null) !== 'semitexa-module') {
                continue;
            }
            $name = $data['extra']['semitexa-module']['name'] ?? null;
            if (! is_string($name) || ! str_starts_with($name, 'theme-')) {
                continue;
            }
            $themes[] = $name;
        }
        sort($themes);
        return array_values(array_unique($themes));
    }

    /**
     * Build the Twig namespace for a theme — matches the convention used by
     * ssr's ModuleTemplateRegistry (every alias ends up prefixed with
     * `project-layouts-`).
     */
    public function layoutNamespaceFor(string $theme): string
    {
        return 'project-layouts-' . $theme;
    }

    /** @return list<string> */
    private function packageRoots(): array
    {
        $roots = [];
        foreach ((array) @glob($this->projectRoot . '/packages/*', GLOB_ONLYDIR) as $dir) {
            $roots[] = (string) $dir;
        }
        foreach ((array) @glob($this->projectRoot . '/vendor/semitexa/*', GLOB_ONLYDIR) as $dir) {
            $roots[] = (string) $dir;
        }
        return $roots;
    }
}
