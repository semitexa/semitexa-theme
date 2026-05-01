<?php

declare(strict_types=1);

namespace Semitexa\Theme\Application\Service\Runtime;

use Semitexa\Core\Support\ProjectRoot;
use Semitexa\Ssr\Application\Service\Asset\ModuleAssetRegistry;
use Semitexa\Ssr\Application\Service\Template\ModuleTemplateRegistry;
use Semitexa\Theme\Domain\Contract\ThemeManifestRepositoryInterface;
use Semitexa\Theme\Domain\Contract\ThemeResolverInterface;
use Semitexa\Theme\Discovery\SkinDiscovery;
use Semitexa\Theme\Discovery\ThemeDiscovery;
use Semitexa\Theme\Application\Service\Repository\FileBackedThemeManifestRepository;
use Semitexa\Theme\Application\Service\Resolver\ManifestThemeResolver;

/**
 * Lazy singleton that wires the manifest-based resolver chain.
 *
 * Replaces the earlier rule-table-based bootstrap. The public surface
 * (resolver(), override(), reset()) is unchanged — callers don't need
 * to know whether manifests or a central rule file produced the
 * resolver.
 *
 * Repository scans both `packages/<pkg>/theme.json` and
 * `src/theme/<id>/theme.json` on first access.
 */
final class ThemeResolverBootstrap
{
    private static ?ThemeResolverInterface $resolver = null;
    private static ?ThemeManifestRepositoryInterface $repository = null;
    private static bool $ssrChainResolverWired = false;

    public static function resolver(): ThemeResolverInterface
    {
        $r = self::$resolver ??= self::buildDefaultResolver();
        self::wireSsrChainResolver();
        return $r;
    }

    /**
     * Register a closure with SSR's template + asset registries so per-request
     * theme chain walking activates. Idempotent; safe to call from multiple
     * code paths. Runs lazily on first resolver access so plain CLI tools
     * that never resolve a theme don't pay for it.
     */
    private static function wireSsrChainResolver(): void
    {
        if (self::$ssrChainResolverWired) {
            return;
        }
        $closure = static function (): array {
            static $provider = null;
            $provider ??= new ThemeContextProvider(self::repository());
            return $provider->activeChain();
        };
        if (class_exists(ModuleTemplateRegistry::class, false)) {
            ModuleTemplateRegistry::setChainResolver($closure);
        }
        if (class_exists(ModuleAssetRegistry::class, false)) {
            ModuleAssetRegistry::setChainResolver($closure);
        }
        self::$ssrChainResolverWired = true;
    }

    public static function repository(): ThemeManifestRepositoryInterface
    {
        return self::$repository ??= self::buildDefaultRepository();
    }

    public static function override(ThemeResolverInterface $resolver): void
    {
        self::$resolver = $resolver;
    }

    public static function overrideRepository(ThemeManifestRepositoryInterface $repo): void
    {
        self::$repository = $repo;
        self::$resolver = null;
    }

    public static function reset(): void
    {
        self::$resolver = null;
        self::$repository = null;
        self::$ssrChainResolverWired = false;
        if (class_exists(ModuleTemplateRegistry::class, false)) {
            ModuleTemplateRegistry::setChainResolver(null);
        }
        if (class_exists(ModuleAssetRegistry::class, false)) {
            ModuleAssetRegistry::setChainResolver(null);
        }
    }

    private static function buildDefaultResolver(): ThemeResolverInterface
    {
        $root = ProjectRoot::get();
        return new ManifestThemeResolver(
            self::repository(),
            new ThemeDiscovery($root),
            new SkinDiscovery($root),
        );
    }

    private static function buildDefaultRepository(): ThemeManifestRepositoryInterface
    {
        $root = ProjectRoot::get();
        return new FileBackedThemeManifestRepository(
            projectRoot: $root,
            skins: new SkinDiscovery($root),
            knownLocales: null,
            allowUnknownLocales: false,
        );
    }
}
