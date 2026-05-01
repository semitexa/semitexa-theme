<?php

declare(strict_types=1);

namespace Semitexa\Theme\Application\Service\Skin\Oklch;

final class ContrastScore
{
    public const AA_NORMAL = 4.5;
    public const AA_LARGE = 3.0;
    public const AAA_NORMAL = 7.0;

    public static function relativeLuminance(float $r, float $g, float $b): float
    {
        $channel = static fn(float $c): float => $c <= 0.03928
            ? $c / 12.92
            : (($c + 0.055) / 1.055) ** 2.4;

        return 0.2126 * $channel($r) + 0.7152 * $channel($g) + 0.0722 * $channel($b);
    }

    public static function contrast(string $hexA, string $hexB): float
    {
        [$ra, $ga, $ba] = Converter::hexToSrgb($hexA);
        [$rb, $gb, $bb] = Converter::hexToSrgb($hexB);

        $la = self::relativeLuminance($ra, $ga, $ba);
        $lb = self::relativeLuminance($rb, $gb, $bb);

        $lighter = max($la, $lb);
        $darker = min($la, $lb);

        return ($lighter + 0.05) / ($darker + 0.05);
    }

    public static function meetsAa(string $hex, string $againstHex = '#ffffff'): bool
    {
        return self::contrast($hex, $againstHex) >= self::AA_NORMAL;
    }
}
