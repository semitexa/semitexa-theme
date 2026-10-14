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

    /**
     * APCA lightness contrast (Lc, APCA-W3 0.0.98G-4g) of text on a background:
     * positive for dark text on light, negative for light on dark. Reported next
     * to the WCAG 2 ratio, which stays the gate: APCA is not yet a requirement,
     * but it reads mid-tone pairs (white on a saturated blue) more truthfully.
     */
    public static function apcaLc(string $textHex, string $backgroundHex): float
    {
        $y = static function (string $hex): float {
            [$r, $g, $b] = Converter::hexToSrgb($hex);
            $lum = 0.2126729 * $r ** 2.4 + 0.7151522 * $g ** 2.4 + 0.0721750 * $b ** 2.4;

            return $lum > 0.022 ? $lum : $lum + (0.022 - $lum) ** 1.414;
        };
        $text = $y($textHex);
        $background = $y($backgroundHex);
        if (abs($background - $text) < 0.0005) {
            return 0.0;
        }
        if ($background > $text) {
            $sapc = ($background ** 0.56 - $text ** 0.57) * 1.14;

            return $sapc < 0.1 ? 0.0 : ($sapc - 0.027) * 100;
        }
        $sapc = ($background ** 0.65 - $text ** 0.62) * 1.14;

        return $sapc > -0.1 ? 0.0 : ($sapc + 0.027) * 100;
    }

    public static function meetsAa(string $hex, string $againstHex = '#ffffff'): bool
    {
        return self::contrast($hex, $againstHex) >= self::AA_NORMAL;
    }
}
