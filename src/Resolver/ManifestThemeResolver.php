<?php

declare(strict_types=1);

namespace Semitexa\Theme\Resolver;

use Semitexa\Theme\Contract\SkinDiscoveryInterface;
use Semitexa\Theme\Contract\ThemeManifestRepositoryInterface;
use Semitexa\Theme\Contract\ThemeResolverInterface;
use Semitexa\Theme\Discovery\ThemeDiscovery;
use Semitexa\Theme\Exception\ThemeResolutionException;
use Semitexa\Theme\Model\ThemeAssignment;
use Semitexa\Theme\Model\ThemeContext;
use Semitexa\Theme\Model\ThemeManifest;
use Semitexa\Theme\Support\SpecificityMatcher;

/**
 * Two-phase resolver over theme manifests.
 *
 *   Phase 1 — theme selection:
 *     Build a candidate list from all manifests (one candidate per
 *     manifest). Match via SpecificityMatcher. Winner is the active
 *     theme for this request.
 *
 *   Phase 2 — skin selection within the winning theme:
 *     If theme.skin is a fixed slug, use it. Otherwise build a
 *     candidate list from theme.skin.conditions (plus an implicit
 *     "fallback" for theme.skin.default), match via the same
 *     SpecificityMatcher, return winner.
 *
 * Both phases reuse the same 7-level specificity algorithm. Skin URL
 * comes from SkinDiscovery (resolved to a serve URL, not templated).
 */
final class ManifestThemeResolver implements ThemeResolverInterface
{
    public function __construct(
        private readonly ThemeManifestRepositoryInterface $manifests,
        private readonly ThemeDiscovery $themeDiscovery,
        private readonly SkinDiscoveryInterface $skinDiscovery,
        private readonly string $assetBasePathTemplate = '/assets/{theme}/',
    ) {
    }

    public function resolve(ThemeContext $ctx): ThemeAssignment
    {
        $theme = $this->selectTheme($ctx);
        $skin = $this->selectSkin($theme, $ctx);

        $skinEntry = $this->skinDiscovery->find($skin['slug']);
        if ($skinEntry === null) {
            throw new ThemeResolutionException(
                "Theme '{$theme['manifest']->id}' resolved to unknown skin '{$skin['slug']}'"
            );
        }

        /** @var ThemeManifest $tm */
        $tm = $theme['manifest'];

        // Template-lookup namespace: walk the extends chain and pick the first theme
        // whose directory actually has a Twig namespace registered (in practice the
        // root today, since child themes are activation-only and carry no templates).
        // Once SSR request-scoped theme ships (Phase 3 RFC), SSR walks this chain
        // internally per-template and this becomes the leaf id again.
        $templateSourceId = $this->resolveTemplateSourceId($tm);

        return new ThemeAssignment(
            theme: $tm->id,
            skin: $skin['slug'],
            layoutNamespace: $this->themeDiscovery->layoutNamespaceFor($templateSourceId),
            skinCssUrl: $skinEntry->tokensUrl,
            assetBasePath: strtr($this->assetBasePathTemplate, ['{theme}' => $templateSourceId]),
            matchedRuleIdx: $theme['level'],
            matchedRuleLabel: sprintf(
                'theme=%s via %s · skin=%s via %s',
                $tm->id,
                $theme['label'],
                $skin['slug'],
                $skin['label'],
            ),
        );
    }

    /**
     * Return the id of the theme that OWNS layout templates for the active
     * chain. Child themes (leaves) are override collections — they may
     * override individual templates of other modules via SSR's chain-aware
     * loader, but they do not ship their own `layouts/` unless they're
     * registered modules. This walks to the root of the chain, which in v1
     * is always a packaged semitexa-module carrying the base layouts.
     *
     * Once a child theme ships as a real package (with its own composer
     * module registration), this method can return the leaf id when that
     * leaf has `layouts/` templates — SSR will then resolve them directly.
     */
    private function resolveTemplateSourceId(ThemeManifest $tm): string
    {
        $chain = $this->manifests->chainOf($tm->id);
        $root = end($chain);
        return $root instanceof ThemeManifest ? $root->id : $tm->id;
    }

    /**
     * @return array{manifest: ThemeManifest, level: int, label: string}
     */
    private function selectTheme(ThemeContext $ctx): array
    {
        $candidates = [];
        foreach ($this->manifests->load() as $m) {
            $candidates[] = ['active_when' => $m->activeWhen, 'payload' => $m];
        }

        $match = SpecificityMatcher::match($candidates, $ctx);
        if ($match === null) {
            throw new ThemeResolutionException(
                'No theme matched and no root fallback found. '
                . 'This should have been caught by manifest validation at boot.'
            );
        }

        return [
            'manifest' => $match['payload'],
            'level' => $match['idx'],
            'label' => $match['label'],
        ];
    }

    /**
     * @return array{slug: string, label: string}
     */
    private function selectSkin(array $theme, ThemeContext $ctx): array
    {
        /** @var ThemeManifest $tm */
        $tm = $theme['manifest'];
        $selection = $tm->skin;

        if (! $selection->isConditional()) {
            return ['slug' => $selection->default, 'label' => 'theme-default'];
        }

        $candidates = [];
        foreach ($selection->conditions as $cond) {
            $candidates[] = ['active_when' => $cond->when, 'payload' => $cond->use];
        }

        $match = SpecificityMatcher::match($candidates, $ctx);
        if ($match !== null) {
            return [
                'slug' => $match['payload'],
                'label' => 'skin-rule ' . $match['label'],
            ];
        }

        return ['slug' => $selection->default, 'label' => 'skin-default'];
    }
}
