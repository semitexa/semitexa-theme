<?php

declare(strict_types=1);

namespace Semitexa\Theme\Runtime;

use Semitexa\Core\Theme\ThemeProviderInterface;
use Semitexa\Theme\Domain\Contract\ThemeManifestRepositoryInterface;

/**
 * Concrete `ThemeProviderInterface` implementation backed by the
 * manifest-based resolver output (`ThemeContextStore`).
 *
 * Runs on every template / asset lookup. If the per-request assignment
 * is populated (by `ApplyThemeOnAuthCheckListener` during AuthCheck
 * phase), walks the manifest `extends` chain and returns ids leaf-first.
 * Otherwise returns an empty array — a safety fallback that lets SSR
 * fall through to the env-`THEME` default when no per-request resolver
 * has run (e.g., bootstrap, CLI rendering, or projects that haven't
 * enabled the theme manifest pipeline). Replacing this fallback with a
 * hard-fail or explicit default is tracked separately under
 * `tk-resolver-fallback-audit-followup`.
 */
final class ThemeContextProvider implements ThemeProviderInterface
{
    public function __construct(
        private readonly ThemeManifestRepositoryInterface $manifests,
    ) {
    }

    public function activeChain(): array
    {
        $assignment = ThemeContextStore::getOrNull();
        if ($assignment === null) {
            return [];
        }
        try {
            $chain = $this->manifests->chainOf($assignment->theme);
        } catch (\Throwable) {
            // Config changed mid-worker and leaf theme disappeared — fall back
            // to single-id chain. Logged by resolver on next assignment build.
            return [$assignment->theme];
        }
        return array_map(static fn ($m) => $m->id, $chain);
    }
}
