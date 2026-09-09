<?php

declare(strict_types=1);

namespace Semitexa\Theme\Application\Service\Runtime;

use Semitexa\Core\Log\StaticLoggerBridge;
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
 * `tk-resolver-fallback-audit-followup`. The other fallback in this class —
 * a manifest chain that cannot be built — degrades too, but says so in the
 * log rather than passing for normal operation.
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
        } catch (\Throwable $e) {
            // Config changed mid-worker and the leaf theme disappeared. Degrading
            // to a single-id chain is deliberate — a live request must not 500
            // because a manifest moved under it — but it is a degrade, and the
            // only other caller of chainOf() (ManifestThemeResolver) does not
            // catch at all. This comment used to say the resolver logged it on
            // the next assignment build. It did not: the package had no logging
            // of any kind, so a theme could vanish and leave no trace anywhere.
            StaticLoggerBridge::warning('theme', 'Theme chain unavailable; serving the assigned theme alone', [
                'theme' => $assignment->theme,
                'message' => $e->getMessage(),
            ]);

            return [$assignment->theme];
        }
        return array_map(static fn ($m) => $m->id, $chain);
    }
}
