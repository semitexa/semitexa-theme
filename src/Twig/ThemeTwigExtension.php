<?php

declare(strict_types=1);

namespace Semitexa\Theme\Twig;

use Semitexa\Locale\Context\LocaleContextStore;
use Semitexa\Ssr\Attribute\AsTwigExtension;
use Semitexa\Ssr\Extension\TwigExtensionRegistry;
use Semitexa\Tenancy\Context\TenantContextStore;
use Semitexa\Theme\Model\ThemeAssignment;
use Semitexa\Theme\Model\ThemeContext;
use Semitexa\Theme\Runtime\ThemeContextStore;
use Semitexa\Theme\Runtime\ThemeResolverBootstrap;

/**
 * Twig globals for theme-aware templates.
 *
 * Functions:
 *   theme_layout(name)    → '@project-layouts-<theme>/layouts/<name>.html.twig'
 *                           so `{% extends theme_layout('app') %}` resolves
 *                           per request context.
 *   theme_template(path)  → '@project-layouts-<theme>/<path>'
 *                           for includes in non-layouts dirs.
 *   theme_skin_css()      → '/assets/theme/skins/<skin>/tokens.css'
 *                           drop straight into <link href>.
 *   theme_asset(relative) → '<assetBasePath><relative>'
 *                           for theme-owned images/fonts.
 *   theme_info()          → associative array with the full assignment
 *                           (theme, skin, matched_rule_label, ...) —
 *                           useful for debug banners + data-theme attrs.
 *
 * All functions are safe to call before the listener fires: they return
 * the documented defaults instead of throwing.
 */
#[AsTwigExtension]
final class ThemeTwigExtension
{
    public function registerFunctions(): void
    {
        TwigExtensionRegistry::registerFunction('theme_layout', [$this, 'layout']);
        TwigExtensionRegistry::registerFunction('theme_template', [$this, 'template']);
        TwigExtensionRegistry::registerFunction('theme_skin_css', [$this, 'skinCss']);
        TwigExtensionRegistry::registerFunction('theme_asset', [$this, 'asset']);
        TwigExtensionRegistry::registerFunction('theme_info', [$this, 'info']);
    }

    public function layout(string $name): string
    {
        return $this->current()->layoutTemplate($name);
    }

    public function template(string $relative): string
    {
        return $this->current()->templatePath($relative);
    }

    public function skinCss(): string
    {
        return $this->current()->skinCssUrl;
    }

    public function asset(string $relative): string
    {
        return $this->current()->assetBasePath . ltrim($relative, '/');
    }

    /** @return array<string, mixed> */
    public function info(): array
    {
        $a = $this->current();
        return [
            'theme' => $a->theme,
            'skin' => $a->skin,
            'layout_namespace' => $a->layoutNamespace,
            'matched_rule_label' => $a->matchedRuleLabel,
            'matched_rule_idx' => $a->matchedRuleIdx,
            'skin_css_url' => $a->skinCssUrl,
            'asset_base_path' => $a->assetBasePath,
        ];
    }

    /**
     * Return the active ThemeAssignment. If the event listener populated
     * ThemeContextStore, use it. Otherwise resolve lazily from whatever
     * request-scope data is available — so templates render correctly
     * even when tenancy/locale bootstrappers aren't enabled for the
     * project (e.g. single-tenant dev setups).
     */
    private function current(): ThemeAssignment
    {
        $existing = ThemeContextStore::getOrNull();
        if ($existing !== null) {
            return $existing;
        }

        $ctx = new ThemeContext(
            tenant: $this->peekTenant(),
            domain: $this->peekDomain(),
            locale: LocaleContextStore::getLocale(),
        );

        $assignment = ThemeResolverBootstrap::resolver()->resolve($ctx);
        ThemeContextStore::set($assignment);
        return $assignment;
    }

    private function peekTenant(): ?string
    {
        if (! class_exists(TenantContextStore::class, false)) {
            return null;
        }
        $ctx = TenantContextStore::shared()->tryGet();
        if ($ctx === null || ! method_exists($ctx, 'getTenantId')) {
            return null;
        }
        $id = (string) $ctx->getTenantId();
        return $id === '' || $id === 'default' ? null : $id;
    }

    private function peekDomain(): string
    {
        // Primary: the event-driven listener populates ThemeContextStore from
        // Request::getHost() when tenancy is enabled — this branch is never
        // reached in that case. For tenancy-disabled dev setups Swoole does
        // not bridge HTTP_HOST into $_SERVER, so we fall back to an env
        // override. Multi-tenant production flips TENANCY_ENABLED=true.
        $host = $_SERVER['HTTP_HOST'] ?? \Semitexa\Core\Environment::getEnvValue('THEME_FALLBACK_DOMAIN') ?? '';
        if (! is_string($host)) {
            return '';
        }
        $host = strtolower(trim($host));
        if (str_contains($host, ':')) {
            $host = substr($host, 0, strpos($host, ':'));
        }
        return $host;
    }
}
