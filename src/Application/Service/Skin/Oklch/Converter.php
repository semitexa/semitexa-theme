<?php

declare(strict_types=1);

namespace Semitexa\Theme\Application\Service\Skin\Oklch;

final class Converter
{
    public static function hexToOklch(string $hex): Color
    {
        [$r, $g, $b] = self::hexToSrgb($hex);
        return self::srgbToOklch($r, $g, $b);
    }

    public static function oklchToHex(Color $color): string
    {
        [$r, $g, $b] = self::oklchToSrgb($color);
        return self::srgbToHex($r, $g, $b);
    }

    /** @return array{float, float, float} Normalized 0..1 sRGB */
    public static function hexToSrgb(string $hex): array
    {
        $normalized = ltrim(strtolower($hex), '#');
        if (strlen($normalized) !== 6 || !ctype_xdigit($normalized)) {
            throw new \InvalidArgumentException("Invalid hex color: {$hex}");
        }
        return [
            hexdec(substr($normalized, 0, 2)) / 255.0,
            hexdec(substr($normalized, 2, 2)) / 255.0,
            hexdec(substr($normalized, 4, 2)) / 255.0,
        ];
    }

    public static function srgbToHex(float $r, float $g, float $b): string
    {
        $clamp = static fn(float $v): int => max(0, min(255, (int) round($v * 255)));
        return sprintf('#%02x%02x%02x', $clamp($r), $clamp($g), $clamp($b));
    }

    public static function srgbToOklch(float $r, float $g, float $b): Color
    {
        $lr = self::linearize($r);
        $lg = self::linearize($g);
        $lb = self::linearize($b);

        $l = 0.4122214708 * $lr + 0.5363325363 * $lg + 0.0514459929 * $lb;
        $m = 0.2119034982 * $lr + 0.6806995451 * $lg + 0.1073969566 * $lb;
        $s = 0.0883024619 * $lr + 0.2817188376 * $lg + 0.6299787005 * $lb;

        $lp = self::cbrt($l);
        $mp = self::cbrt($m);
        $sp = self::cbrt($s);

        $L = 0.2104542553 * $lp + 0.7936177850 * $mp - 0.0040720468 * $sp;
        $a = 1.9779984951 * $lp - 2.4285922050 * $mp + 0.4505937099 * $sp;
        $bLab = 0.0259040371 * $lp + 0.7827717662 * $mp - 0.8086757660 * $sp;

        $C = sqrt($a * $a + $bLab * $bLab);
        $H = atan2($bLab, $a) * 180.0 / M_PI;
        if ($H < 0.0) {
            $H += 360.0;
        }

        return new Color($L, $C, $H);
    }

    /** @return array{float, float, float} Normalized 0..1 sRGB (clamped) */
    public static function oklchToSrgb(Color $color): array
    {
        $hRad = $color->h * M_PI / 180.0;
        $L = $color->l;
        $a = $color->c * cos($hRad);
        $bLab = $color->c * sin($hRad);

        $lp = $L + 0.3963377774 * $a + 0.2158037573 * $bLab;
        $mp = $L - 0.1055613458 * $a - 0.0638541728 * $bLab;
        $sp = $L - 0.0894841775 * $a - 1.2914855480 * $bLab;

        $l = $lp ** 3;
        $m = $mp ** 3;
        $s = $sp ** 3;

        $lr = +4.0767416621 * $l - 3.3077115913 * $m + 0.2309699292 * $s;
        $lg = -1.2684380046 * $l + 2.6097574011 * $m - 0.3413193965 * $s;
        $lb = -0.0041960863 * $l - 0.7034186147 * $m + 1.7076147010 * $s;

        return [
            self::delinearize($lr),
            self::delinearize($lg),
            self::delinearize($lb),
        ];
    }

    private static function linearize(float $c): float
    {
        return $c <= 0.04045 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
    }

    private static function delinearize(float $c): float
    {
        if ($c <= 0.0) {
            return 0.0;
        }
        if ($c >= 1.0) {
            return 1.0;
        }
        return $c <= 0.0031308 ? $c * 12.92 : 1.055 * ($c ** (1.0 / 2.4)) - 0.055;
    }

    private static function cbrt(float $x): float
    {
        return $x < 0.0 ? -((-$x) ** (1.0 / 3.0)) : $x ** (1.0 / 3.0);
    }
}
