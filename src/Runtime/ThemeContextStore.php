<?php

declare(strict_types=1);

namespace Semitexa\Theme\Runtime;

use Semitexa\Theme\Model\ThemeAssignment;
use Swoole\Coroutine;

/**
 * Coroutine-safe per-request storage for the active ThemeAssignment.
 *
 * Populated once per request by the LocaleResolved listener (after tenant
 * + locale are known). Read by Twig globals and AssetCollector integration.
 *
 * Mirrors the pattern used by Semitexa\Locale\Context\LocaleContextStore
 * and Semitexa\Ssr\Asset\AssetCollectorStore: Swoole\Coroutine::getContext()
 * under Swoole; a static fallback for CLI/tests.
 */
final class ThemeContextStore
{
    private const KEY = '__semitexa_theme_assignment';

    private static ?ThemeAssignment $staticFallback = null;

    public static function set(ThemeAssignment $assignment): void
    {
        if (self::inCoroutine()) {
            Coroutine::getContext()[self::KEY] = $assignment;
            return;
        }

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

    private static function inCoroutine(): bool
    {
        return class_exists(Coroutine::class, false) && Coroutine::getCid() > 0;
    }
}
