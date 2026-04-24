<?php

declare(strict_types=1);

namespace Semitexa\Theme\Model;

/**
 * Output of ThemeResolverInterface::resolve() — the full rendering context
 * for a request. Carries enough data that Twig + asset pipeline do not
 * re-derive anything: template paths and asset URLs are already computed.
 *
 * A resolver constructs this; Twig globals and AssetCollector consume it.
 */
final readonly class ThemeAssignment
{
    public function __construct(
        /** Module alias of the active theme, e.g. 'theme-sky'. */
        public string $theme,
        /** Skin slug, e.g. 'batumi' or 'default'. */
        public string $skin,
        /**
         * Twig namespace root for the active theme (no '@' prefix, no trailing '/').
         * Example: 'project-layouts-theme-sky'. Build full template paths via
         * layoutTemplate() / templatePath() instead of concatenating manually.
         */
        public string $layoutNamespace,
        /** Absolute URL to the skin's tokens.css, ready to drop into <link href>. */
        public string $skinCssUrl,
        /**
         * Absolute URL prefix for the theme's static assets (images, fonts),
         * e.g. '/assets/theme-sky/'. Includes trailing slash.
         */
        public string $assetBasePath,
        /** Zero-based index of the matched specificity level (0 = most specific, 7 = default). */
        public int $matchedRuleIdx,
        /**
         * Human-readable label of the matched fallback level, e.g. 'tenant+domain+locale'
         * or 'default'. Used by theme:resolve and debug tooling.
         */
        public string $matchedRuleLabel,
    ) {
    }

    /**
     * Build a Twig template reference under this theme's namespace.
     *
     * Example: layoutTemplate('app') → '@project-layouts-theme-sky/layouts/app.html.twig'
     */
    public function layoutTemplate(string $name): string
    {
        return '@' . $this->layoutNamespace . '/layouts/' . $name . '.html.twig';
    }

    /**
     * Build a Twig template reference for a non-layout template under this theme.
     *
     * Example: templatePath('partials/header.html.twig')
     *        → '@project-layouts-theme-sky/partials/header.html.twig'
     */
    public function templatePath(string $relative): string
    {
        return '@' . $this->layoutNamespace . '/' . ltrim($relative, '/');
    }
}
