<?php

declare(strict_types=1);

namespace Semitexa\Theme\Skin\Algorithm;

use Semitexa\Theme\Domain\Contract\SkinAlgorithmInterface;
use Semitexa\Theme\Skin\KnobResolver;
use Semitexa\Theme\Skin\Oklch\Color;
use Semitexa\Theme\Skin\Oklch\ContrastScore;
use Semitexa\Theme\Skin\Oklch\Converter;
use Semitexa\Theme\Skin\SkinMode;
use Semitexa\Theme\Skin\SkinPalette;
use Semitexa\Theme\Skin\SkinParams;
use Semitexa\Theme\Skin\TokenContract;

/**
 * Translucent, modern. Panels get backdrop-blur, larger radii, slower
 * emphasized motion, diffuse shadows with light-from-above feel.
 *
 * Color math mirrors balanced but lightens surfaces slightly so the
 * frosted panels keep a visible gradient over arbitrary backdrops.
 */
final class GlassAlgorithm implements SkinAlgorithmInterface
{
    private const HUE_SUCCESS = 145.0;
    private const HUE_WARNING = 70.0;
    private const HUE_DANGER = 25.0;
    private const HUE_INFO = 240.0;

    public function id(): string
    {
        return 'glass';
    }

    public function description(): string
    {
        return 'Translucent frosted panels, larger radii, diffuse shadows, emphasized motion. Modern SaaS / mac-style.';
    }

    public function knobSchema(): array
    {
        return [
            'blur_amount' => [
                'enum' => ['subtle', 'medium', 'heavy'],
                'default' => 'medium',
                'description' => 'Backdrop blur radius on panels.',
            ],
            'surface_transparency' => [
                'enum' => ['light', 'medium', 'heavy'],
                'default' => 'medium',
                'description' => 'How much the backdrop shows through panels.',
            ],
            'shadow_softness' => [
                'enum' => ['tight', 'standard', 'wide'],
                'default' => 'standard',
                'description' => 'Spread of diffuse drop shadows.',
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
            // Glass-dark: surfaces a touch brighter than balanced-dark so backdrop-saturation
            // boost has material to push against (frosted effect needs pale-against-void).
            $tokens[TokenContract::SurfacePage->value]   = $this->hex(new Color(0.13, 0.008, $hue));
            $tokens[TokenContract::SurfacePanel->value]  = $this->hex(new Color(0.20, 0.012, $hue));
            $tokens[TokenContract::SurfaceRaised->value] = $this->hex(new Color(0.24, 0.014, $hue));
            $tokens[TokenContract::SurfaceSunken->value] = $this->hex(new Color(0.09, 0.008, $hue));

            $tokens[TokenContract::BorderSubtle->value] = $this->hex(new Color(0.30, 0.015, $hue));
            $tokens[TokenContract::BorderStrong->value] = $this->hex(new Color(0.45, 0.020, $hue));

            $tokens[TokenContract::TextPrimary->value] = $this->hex(new Color(0.95, 0.010, $hue));
            $tokens[TokenContract::TextMuted->value]   = $this->hex(new Color(0.66, 0.012, $hue));
        } else {
            // surfaces slightly lighter than balanced — frosted-glass effect needs
            // a pale base so backdrop-saturation boost looks right.
            $tokens[TokenContract::SurfacePage->value]   = $this->hex(new Color(0.99, 0.003, $hue));
            $tokens[TokenContract::SurfacePanel->value]  = $this->hex(new Color(0.975, 0.006, $hue));
            $tokens[TokenContract::SurfaceRaised->value] = $this->hex(new Color(1.0, 0.0, $hue));
            $tokens[TokenContract::SurfaceSunken->value] = $this->hex(new Color(0.95, 0.008, $hue));

            $tokens[TokenContract::BorderSubtle->value] = $this->hex(new Color(0.92, 0.012, $hue));
            $tokens[TokenContract::BorderStrong->value] = $this->hex(new Color(0.80, 0.02, $hue));

            $tokens[TokenContract::TextPrimary->value] = $this->hex(new Color(0.22, 0.015, $hue));
            $tokens[TokenContract::TextMuted->value]   = $this->hex(new Color(0.52, 0.012, $hue));
        }

        $surfacePage = $tokens[TokenContract::SurfacePage->value];
        $accent = $this->ensureContrastAgainst($seed, $surfacePage, $contrastFloor, $mode);
        $accentHex = $this->hex($accent);
        $onAccent = $this->pickOnAccentText($accentHex);
        $tokens[TokenContract::AccentBrand->value]          = $accentHex;
        $tokens[TokenContract::AccentBrandContrast->value]  = $onAccent;
        $tokens[TokenContract::TextOnAccent->value]         = $onAccent;

        $stateChroma = max(0.10, min(0.15, $seed->c));
        $stateLightBump = $mode === SkinMode::Dark ? 0.08 : 0.0;
        $tokens[TokenContract::StateSuccess->value] = $this->hex(new Color(0.58 + $stateLightBump, $stateChroma, self::HUE_SUCCESS));
        $tokens[TokenContract::StateWarning->value] = $this->hex(new Color(0.74 + ($mode === SkinMode::Dark ? 0.04 : 0.0), $stateChroma, self::HUE_WARNING));
        $tokens[TokenContract::StateDanger->value]  = $this->hex(new Color(0.58 + $stateLightBump, $stateChroma + 0.02, self::HUE_DANGER));
        $tokens[TokenContract::StateInfo->value]    = $this->hex(new Color(0.60 + $stateLightBump, $stateChroma, self::HUE_INFO));

        $tokens[TokenContract::FocusRing->value] = $this->hex($accent->scaleChroma(1.2));

        $chartKeys = [
            TokenContract::Chart1, TokenContract::Chart2, TokenContract::Chart3, TokenContract::Chart4,
            TokenContract::Chart5, TokenContract::Chart6, TokenContract::Chart7, TokenContract::Chart8,
        ];
        $chartBase = $mode === SkinMode::Dark ? 0.70 : 0.65;
        foreach ($chartKeys as $i => $role) {
            $h = fmod($hue + $i * 45.0, 360.0);
            $l = $chartBase - ($i % 2) * 0.05;
            $tokens[$role->value] = $this->hex(new Color($l, 0.12, $h));
        }

        return $tokens;
    }

    /**
     * @param array<string, string> $knobs
     * @return array<string, string>
     */
    private function generateNonColorTokens(array $knobs, SkinMode $mode): array
    {
        $blurRadius = match ($knobs['blur_amount']) {
            'subtle' => '6px',
            'heavy'  => '20px',
            default  => '12px',
        };

        // Larger radii than balanced — glass feels softer
        $radii = [
            'none' => '0',
            'sm'   => '0.375rem',
            'md'   => '0.75rem',
            'lg'   => '1rem',
            'pill' => '9999px',
        ];

        // Diffuse shadows with vertical offset (light from above)
        [$spreadXs, $spreadSm, $spreadMd, $spreadLg] = match ($knobs['shadow_softness']) {
            'tight'    => [2, 6, 16, 28],
            'wide'     => [6, 16, 40, 60],
            default    => [4, 10, 24, 40],
        };

        // Dark glass needs denser shadows to register against dark backdrop.
        [$aXs, $aSm, $aMd, $aLg, $aBase] = $mode === SkinMode::Dark
            ? [0.30, 0.40, 0.50, 0.60, 0.35]
            : [0.04, 0.08, 0.12, 0.18, 0.08];

        // Saturation boost on light glass emphasizes frost; on dark it would bleach
        // the backdrop into harsh tints, so tone it down.
        $saturation = $mode === SkinMode::Dark ? '120%' : '140%';

        return [
            TokenContract::RadiusNone->value => $radii['none'],
            TokenContract::RadiusSm->value   => $radii['sm'],
            TokenContract::RadiusMd->value   => $radii['md'],
            TokenContract::RadiusLg->value   => $radii['lg'],
            TokenContract::RadiusPill->value => $radii['pill'],

            TokenContract::ShadowXs->value => "0 1px {$spreadXs}px 0 rgba(0, 0, 0, {$aXs})",
            TokenContract::ShadowSm->value => "0 4px {$spreadSm}px -2px rgba(0, 0, 0, {$aSm})",
            TokenContract::ShadowMd->value => "0 10px {$spreadMd}px -6px rgba(0, 0, 0, {$aMd})",
            TokenContract::ShadowLg->value => "0 20px {$spreadLg}px -12px rgba(0, 0, 0, {$aLg})",
            TokenContract::ShadowColor->value => "rgba(0, 0, 0, {$aBase})",

            TokenContract::MotionDurationFast->value   => '180ms',
            TokenContract::MotionDurationNormal->value => '320ms',
            TokenContract::MotionDurationSlow->value   => '600ms',
            TokenContract::MotionEasingStandard->value    => 'cubic-bezier(0.4, 0, 0.2, 1)',
            TokenContract::MotionEasingEmphasized->value  => 'cubic-bezier(0.2, 0, 0, 1)',

            TokenContract::SurfaceBlur->value       => "blur({$blurRadius})",
            TokenContract::SurfaceSaturation->value => $saturation,
        ];
    }

    private function normalizeSeed(Color $seed): Color
    {
        $l = max(0.35, min(0.70, $seed->l));
        $c = min(0.22, $seed->c);
        return new Color($l, $c, $seed->h);
    }

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
        return $mode === SkinMode::Dark
            ? Color::fromHex('#ffffff')
            : Color::fromHex('#111111');
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
