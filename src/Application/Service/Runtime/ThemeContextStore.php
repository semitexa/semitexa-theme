<?php

declare(strict_types=1);

namespace Semitexa\Theme\Application\Service\Runtime;

use Semitexa\Core\Lifecycle\PerRequestStateRegistry;
use Semitexa\Theme\Domain\Model\ThemeAssignment;
use Swoole\Coroutine;

/**
 * Coroutine-safe per-request storage for the active ThemeAssignment.
 *
 * Populated once per request by the LocaleResolved listener (after tenant
 * + locale are known). Read by Twig globals and AssetCollector integration.
 *
 * Mirrors the pattern used by Semitexa\Locale\Context\LocaleContextStore
 * and Semitexa\Ssr\Application\Service\Asset\AssetCollectorStore: Swoole\Coroutine::getContext()
 * under Swoole; a static fallback for CLI/tests. The queue worker runs each
 * job outside a coroutine, so the fallback is reset through
 * {@see PerRequestStateRegistry} after every unit of work — otherwise one
 * tenant's job leaves its theme for the next job on the worker.
 */
final class ThemeContextStore
{
    private const KEY = '__semitexa_theme_assignment';

    private static ?ThemeAssignment $staticFallback = null;

    private static bool $registered = false;

    public static function set(ThemeAssignment $assignment): void
    {
        if (self::inCoroutine()) {
            Coroutine::getContext()[self::KEY] = $assignment;
            return;
        }

        self::ensureRegistered();
        self::$staticFallback = $assignment;
    }

    /**
     * @throws \RuntimeException when nothing has been stored yet — callers
     *                           running before the resolver listener fired
     *                           should use getOrNull() instead.
     */
    public static function get(): ThemeAssignment
    {
        $assignment = self::getOrNull();
        if ($assignment === null) {
            throw new \RuntimeException(
                'No ThemeAssignment in context. '
                . 'The theme resolver must fire on LocaleResolved before templates render.'
            );
        }
        return $assignment;
    }

    public static function getOrNull(): ?ThemeAssignment
    {
        if (self::inCoroutine()) {
            return Coroutine::getContext()[self::KEY] ?? self::$staticFallback;
        }

        return self::$staticFallback;
    }

    public static function reset(): void
    {
        if (self::inCoroutine()) {
            $ctx = Coroutine::getContext();
            unset($ctx[self::KEY]);
            return;
        }

        self::$staticFallback = null;
    }

    /** Lazy, so a worker that never assigns a theme registers nothing. */
    private static function ensureRegistered(): void
    {
        if (self::$registered) {
            return;
        }

        PerRequestStateRegistry::register('theme_context_store', static function (): void {
            self::$staticFallback = null;
        });
        self::$registered = true;
    }

    private static function inCoroutine(): bool
    {
        return class_exists(Coroutine::class, false) && Coroutine::getCid() > 0;
    }
}
