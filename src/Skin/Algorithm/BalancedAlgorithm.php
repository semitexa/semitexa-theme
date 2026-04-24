<?php

declare(strict_types=1);

namespace Semitexa\Theme\Skin\Algorithm;

use Semitexa\Theme\Contract\SkinAlgorithm;
use Semitexa\Theme\Skin\KnobResolver;
use Semitexa\Theme\Skin\Oklch\Color;
use Semitexa\Theme\Skin\Oklch\ContrastScore;
use Semitexa\Theme\Skin\Oklch\Converter;
use Semitexa\Theme\Skin\SkinMode;
use Semitexa\Theme\Skin\SkinPalette;
use Semitexa\Theme\Skin\SkinParams;
use Semitexa\Theme\Skin\TokenContract;

/**
 * Safe, corporate-readable palette. WCAG-AA contrast for brand accent.
 * Knobs let callers soften or sharpen the non-color character without
 * leaving "balanced" territory.
 */
final class BalancedAlgorithm implements SkinAlgorithm
{
    private const HUE_SUCCESS = 145.0;
    private const HUE_WARNING = 70.0;
    private const HUE_DANGER = 25.0;
    private const HUE_INFO = 240.0;

    public function id(): string
    {
        return 'balanced';
    }

    public function description(): string
    {
        return 'Corporate-readable. Soft drop shadows, conservative radii, smooth transitions. WCAG-AA contrast on brand.';
    }

    public function knobSchema(): array
    {
        return [
            'radius_scale' => [
                'enum' => ['compact', 'default', 'rounded'],
                'default' => 'default',
                'description' => 'How round corners feel — compact=tight, rounded=softer.',
            ],
            'shadow_intensity' => [
                'enum' => ['minimal', 'default', 'pronounced'],
                'default' => 'default',
                'description' => 'Drop shadow strength on raised surfaces.',
            ],
            'motion_speed' => [
                'enum' => ['fast', 'default', 'slow'],
                'default' => 'default',
                'description' => 'Transition duration character.',
            ],
        ];
    }

    public function generate(SkinParams $params): SkinPalette
    {
        $knobs = KnobResolver::resolve($params->knobs, $this->knobSchema());
        $seed = $this->normalizeSeed(Converter::hexToOklch($params->seedHex));
        $hue = $seed->h;

        $tokens = $this->generateColorTokens($seed, $hue, $params->contrastFloor, $params->mode);
        $tokens += $this->generateNonColorTokens($knobs, $params->mode);

        return new SkinPalette($tokens);
    }

    /** @return array<string, string> */
    private function generateColorTokens(Color $seed, float $hue, float $contrastFloor, SkinMode $mode): array
    {
        $tokens = [];

        if ($mode === SkinMode::Dark) {
            $tokens[TokenContract::SurfacePage->value]   = $this->hex(new Color(0.14, 0.008, $hue));
            $tokens[TokenContract::SurfacePanel->value]  = $this->hex(new Color(0.18, 0.010, $hue));
            $tokens[TokenContract::SurfaceRaised->value] = $this->hex(new Color(0.22, 0.012, $hue));
            $tokens[TokenContract::SurfaceSunken->value] = $this->hex(new Color(0.10, 0.008, $hue));

            $tokens[TokenContract::BorderSubtle->value] = $this->hex(new Color(0.28, 0.015, $hue));
            $tokens[TokenContract::BorderStrong->value] = $this->hex(new Color(0.42, 0.020, $hue));

            $tokens[TokenContract::TextPrimary->value] = $this->hex(new Color(0.95, 0.010, $hue));
            $tokens[TokenContract::TextMuted->value]   = $this->hex(new Color(0.65, 0.012, $hue));
        } else {
            $tokens[TokenContract::SurfacePage->value]   = $this->hex(new Color(0.985, 0.004, $hue));
            $tokens[TokenContract::SurfacePanel->value]  = $this->hex(new Color(0.965, 0.008, $hue));
            $tokens[TokenContract::SurfaceRaised->value] = $this->hex(new Color(1.0, 0.0, $hue));
            $tokens[TokenContract::SurfaceSunken->value] = $this->hex(new Color(0.94, 0.01, $hue));

            $tokens[TokenContract::BorderSubtle->value] = $this->hex(new Color(0.9, 0.015, $hue));
            $tokens[TokenContract::BorderStrong->value] = $this->hex(new Color(0.78, 0.025, $hue));

            $tokens[TokenContract::TextPrimary->value] = $this->hex(new Color(0.22, 0.015, $hue));
            $tokens[TokenContract::TextMuted->value]   = $this->hex(new Color(0.5, 0.01, $hue));
        }

        $surfacePage = $tokens[TokenContract::SurfacePage->value];
        $accent = $this->ensureContrastAgainst($seed, $surfacePage, $contrastFloor, $mode);
        $accentHex = $this->hex($accent);
        $onAccent = $this->pickOnAccentText($accentHex);
        $tokens[TokenContract::AccentBrand->value]          = $accentHex;
        $tokens[TokenContract::AccentBrandContrast->value]  = $onAccent;
        $tokens[TokenContract::TextOnAccent->value]         = $onAccent;

        $stateChroma = max(0.11, min(0.16, $seed->c));
        // State colors shift slightly lighter in dark mode so they remain legible on dark surfaces.
        $stateLightBump = $mode === SkinMode::Dark ? 0.08 : 0.0;
        $tokens[TokenContract::StateSuccess->value] = $this->hex(new Color(0.55 + $stateLightBump, $stateChroma, self::HUE_SUCCESS));
        $tokens[TokenContract::StateWarning->value] = $this->hex(new Color(0.72 + ($mode === SkinMode::Dark ? 0.04 : 0.0), $stateChroma, self::HUE_WARNING));
        $tokens[TokenContract::StateDanger->value]  = $this->hex(new Color(0.55 + $stateLightBump, $stateChroma + 0.02, self::HUE_DANGER));
        $tokens[TokenContract::StateInfo->value]    = $this->hex(new Color(0.56 + $stateLightBump, $stateChroma, self::HUE_INFO));

        $tokens[TokenContract::FocusRing->value] = $this->hex($accent->scaleChroma(1.15));

        $chartKeys = [
            TokenContract::Chart1, TokenContract::Chart2, TokenContract::Chart3, TokenContract::Chart4,
            TokenContract::Chart5, TokenContract::Chart6, TokenContract::Chart7, TokenContract::Chart8,
        ];
        $chartBase = $mode === SkinMode::Dark ? 0.68 : 0.62;
        foreach ($chartKeys as $i => $role) {
            $h = fmod($hue + $i * 45.0, 360.0);
            $l = $chartBase - ($i % 2) * 0.05;
            $tokens[$role->value] = $this->hex(new Color($l, 0.13, $h));
        }

        return $tokens;
    }

    /**
     * @param array<string, string> $knobs resolved (no missing keys)
     * @return array<string, string>
     */
    private function generateNonColorTokens(array $knobs, SkinMode $mode): array
    {
        $radii = match ($knobs['radius_scale']) {
            'compact' => ['none' => '0', 'sm' => '0.125rem', 'md' => '0.25rem', 'lg' => '0.5rem', 'pill' => '9999px'],
            'rounded' => ['none' => '0', 'sm' => '0.375rem', 'md' => '0.75rem', 'lg' => '1rem',   'pill' => '9999px'],
            default   => ['none' => '0', 'sm' => '0.25rem',  'md' => '0.5rem',  'lg' => '0.75rem','pill' => '9999px'],
        };

        [$alphaXs, $alphaSm, $alphaMd, $alphaLg] = match ($knobs['shadow_intensity']) {
            'minimal'    => [0.03, 0.04, 0.06, 0.08],
            'pronounced' => [0.08, 0.12, 0.18, 0.25],
            default      => [0.05, 0.06, 0.10, 0.14],
        };
        // Shadows on dark backgrounds need higher alpha to remain perceptible
        // since rgba(0,0,0,α) over a dark surface has tiny visual contrast.
        if ($mode === SkinMode::Dark) {
            $alphaXs *= 3.0;
            $alphaSm *= 3.0;
            $alphaMd *= 3.0;
            $alphaLg *= 3.0;
        }

        [$fast, $normal, $slow] = match ($knobs['motion_speed']) {
            'fast'  => ['60ms',  '120ms', '240ms'],
            'slow'  => ['200ms', '360ms', '640ms'],
            default => ['120ms', '240ms', '480ms'],
        };

        $shadowColorToken = $mode === SkinMode::Dark ? 'rgba(0, 0, 0, 0.4)' : 'rgba(0, 0, 0, 0.1)';

        return [
            TokenContract::RadiusNone->value => $radii['none'],
            TokenContract::RadiusSm->value   => $radii['sm'],
            TokenContract::RadiusMd->value   => $radii['md'],
            TokenContract::RadiusLg->value   => $radii['lg'],
            TokenContract::RadiusPill->value => $radii['pill'],

            TokenContract::ShadowXs->value => "0 1px 2px 0 rgba(0, 0, 0, {$alphaXs})",
            TokenContract::ShadowSm->value => "0 1px 3px 0 rgba(0, 0, 0, {$alphaSm})",
            TokenContract::ShadowMd->value => "0 4px 6px -1px rgba(0, 0, 0, {$alphaMd})",
            TokenContract::ShadowLg->value => "0 10px 15px -3px rgba(0, 0, 0, {$alphaLg})",
            TokenContract::ShadowColor->value => $shadowColorToken,

            TokenContract::MotionDurationFast->value   => $fast,
            TokenContract::MotionDurationNormal->value => $normal,
            TokenContract::MotionDurationSlow->value   => $slow,
            TokenContract::MotionEasingStandard->value    => 'cubic-bezier(0.4, 0, 0.2, 1)',
            TokenContract::MotionEasingEmphasized->value  => 'cubic-bezier(0.3, 0, 0, 1)',

            TokenContract::SurfaceBlur->value       => 'none',
            TokenContract::SurfaceSaturation->value => '100%',
        ];
    }

    private function normalizeSeed(Color $seed): Color
    {
        $l = max(0.35, min(0.70, $seed->l));
        $c = min(0.25, $seed->c);
        return new Color($l, $c, $seed->h);
    }

    /**
     * Walk lightness in the direction that IMPROVES contrast against the reference.
     * Dark backgrounds need the accent to be brighter; light backgrounds, darker.
     * Prior code only darkened, which produced unreadable accents against dark surfaces.
     */
    private function ensureContrastAgainst(Color $color, string $referenceHex, float $floor, SkinMode $mode): Color
    {
        $attempt = $color;
        $step = $mode === SkinMode::Dark ? 0.03 : -0.03;
        $bound = $mode === SkinMode::Dark ? 0.95 : 0.10;
        for ($i = 0; $i < 20; $i++) {
            $hex = $this->hex($attempt);
            if (ContrastScore::contrast($hex, $referenceHex) >= $floor) {
                return $attempt;
            }
            $next = $attempt->l + $step;
            if ($mode === SkinMode::Dark) {
                $next = min($bound, $next);
            } else {
                $next = max($bound, $next);
            }
            $attempt = $attempt->withLightness($next);
        }
        return $attempt;
    }

    private function pickOnAccentText(string $accentHex): string
    {
        $whiteContrast = ContrastScore::contrast($accentHex, '#ffffff');
        $blackContrast = ContrastScore::contrast($accentHex, '#111111');
        return $whiteContrast >= $blackContrast ? '#ffffff' : '#111111';
    }

    private function hex(Color $color): string
    {
        return Converter::oklchToHex($color);
    }
}
