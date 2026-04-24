<?php

declare(strict_types=1);

namespace Semitexa\Theme\Model;

/**
 * Parsed representation of a single `theme.json` file.
 *
 * Each manifest is self-describing: it names itself, points at an
 * optional parent (for template inheritance), declares when it applies
 * (active_when), and declares which skin to use (simple or conditional).
 *
 * Manifests live either in a package:
 *   `packages/<vendor>-<pkg>/theme.json`
 * or in project source:
 *   `src/theme/<id>/theme.json`
 *
 * The containing directory is carried as `path` — this is the root for
 * SSR's template-override substrate (`<path>/<module>/templates/*`).
 * Once SSR request-scoped theme ships (deferred RFC), `path` + the
 * extends-chain become the fallback chain for template resolution.
 */
final readonly class ThemeManifest
{
    public function __construct(
        public string $id,
        public ?string $extends,
        public ActiveWhen $activeWhen,
        public SkinSelection $skin,
        public string $path,    // absolute FS path of the theme directory
        public string $source,  // 'package' | 'project'
    ) {
    }
}
