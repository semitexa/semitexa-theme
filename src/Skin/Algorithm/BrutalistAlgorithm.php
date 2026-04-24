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
 * Bold, structural, high-contrast. Zero radius (except pill), hard
 * offset shadows, instant motion. Chroma boosted, text darker.
 *
 * Shadow-color knob pulls from --ui-accent-brand when "brand" mode is
 * chosen — produces the signature neo-brutalist offset-colored-shadow
 * look. Neutral mode keeps shadows matte-black (light skin) or matte-white
 * (dark skin, where black-on-black disappears).
 */
final class BrutalistAlgorithm implements SkinAlgorithm
{
    private const HUE_SUCCESS = 145.0;
    private const HUE_WARNING = 70.0;
    private const HUE_DANGER = 25.0;
    private const HUE_INFO = 240.0;

    public function id(): string
    {
        return 'brutalist';
    }

    public function description(): string
    {
        return 'Bold & structural. Zero radius, hard offset shadows, instant motion, high-contrast colors. Neo-brutalist / zine.';
    }

    public function knobSchema(): array
    {
        return [
            'shadow_offset' => [
                'enum' => ['sharp', 'pronounced', 'extreme'],
                'default' => 'sharp',
                'description' => 'Pixel offset of hard box-shadow.',
            ],
            'contrast_boost' => [
                'enum' => ['standard', 'high', 'extreme'],
                'default' => 'standard',
                'description' => 'How dark text + how saturated accent gets.',
            ],
            'shadow_color_mode' => [
                'enum' => ['neutral', 'brand'],
                'default' => 'neutral',
                'description' => 'Shadow is monochrome (neutral) or picks up the brand accent.',
            ],
        ];
    }

    public function generate(SkinParams $params): SkinPalette
    {
        $knobs = KnobResolver::resolve($params->knobs, $this->knobSchema());
        $seed = $this->normalizeSeed(Converter::hexToOklch($params->seedHex), $knobs['contrast_boost']);
        $hue = $seed->h;

        $tokens = $this->generateColorTokens($seed, $hue, $params->contrastFloor, $knobs['contrast_boost'], $params->mode);
        $tokens += $this->generateNonColorTokens($knobs, $params->mode);

        return new SkinPalette($tokens);
    }

    /** @return array<string, string> */
    private function generateColorTokens(Color $seed, float $hue, float $contrastFloor, string $contrastBoost, SkinMode $mode): array
    {
        $tokens = [];

        if ($mode === SkinMode::Dark) {
            // Dark brutalist — inverted text/surface map; borders are LIGHT
            // so the structural grid reads loudly against near-black canvas.
            $textPrimaryL = match ($contrastBoost) {
                'high'    => 0.94,
                'extreme' => 0.98,
                default   => 0.92,
            };

            $tokens[TokenContract::SurfacePage->value]   = $this->hex(new Color(0.10, 0.006, $hue));
            $tokens[TokenContract::SurfacePanel->value]  = $this->hex(new Color(0.15, 0.012, $hue));
            $tokens[TokenContract::SurfaceRaised->value] = $this->hex(new Color(0.20, 0.014, $hue));
            $tokens[TokenContract::SurfaceSunken->value] = $this->hex(new Color(0.06, 0.008, $hue));

            $tokens[TokenContract::BorderSubtle->value] = $this->hex(new Color(0.55, 0.020, $hue));
            $tokens[TokenContract::BorderStrong->value] = $this->hex(new Color(0.92, 0.020, $hue));

            $tokens[TokenContract::TextPrimary->value] = $this->hex(new Color($textPrimaryL, 0.010, $hue));
            $tokens[TokenContract::TextMuted->value]   = $this->hex(new Color(0.60, 0.010, $hue));
        } else {
            $textPrimaryL = match ($contrastBoost) {
                'high'    => 0.16,
                'extreme' => 0.10,
                default   => 0.20,
            };

            $tokens[TokenContract::SurfacePage->value]   = $this->hex(new Color(0.98, 0.004, $hue));
            $tokens[TokenContract::SurfacePanel->value]  = $this->hex(new Color(0.95, 0.01, $hue));
            $tokens[TokenContract::SurfaceRaised->value] = $this->hex(new Color(1.0, 0.0, $hue));
            $tokens[TokenContract::SurfaceSunken->value] = $this->hex(new Color(0.90, 0.015, $hue));

            $tokens[TokenContract::BorderSubtle->value] = $this->hex(new Color(0.70, 0.02, $hue));
            $tokens[TokenContract::BorderStrong->value] = $this->hex(new Color(0.20, 0.02, $hue));

            $tokens[TokenContract::TextPrimary->value] = $this->hex(new Color($textPrimaryL, 0.02, $hue));
            $tokens[TokenContract::TextMuted->value]   = $this->hex(new Color(0.45, 0.01, $hue));
        }

        $stateChromaBase = match ($contrastBoost) {
            'high'    => 0.16,
            'extreme' => 0.20,
            default   => 0.14,
        };

        $surfacePage = $tokens[TokenContract::SurfacePage->value];
        $accent = $this->ensureContrastAgainst($seed, $surfacePage, $contrastFloor, $mode);
        $accentHex = $this->hex($accent);
        $onAccent = $this->pickOnAccentText($accentHex);
        $tokens[TokenContract::AccentBrand->value]          = $accentHex;
        $tokens[TokenContract::AccentBrandContrast->value]  = $onAccent;
        $tokens[TokenContract::TextOnAccent->value]         = $onAccent;

        $stateChroma = max(0.12, min(0.22, $stateChromaBase));
        $stateLightBump = $mode === SkinMode::Dark ? 0.10 : 0.0;
        $tokens[TokenContract::StateSuccess->value] = $this->hex(new Color(0.50 + $stateLightBump, $stateChroma, self::HUE_SUCCESS));
        $tokens[TokenContract::StateWarning->value] = $this->hex(new Color(0.68 + ($mode === SkinMode::Dark ? 0.05 : 0.0), $stateChroma, self::HUE_WARNING));
        $tokens[TokenContract::StateDanger->value]  = $this->hex(new Color(0.50 + $stateLightBump, $stateChroma + 0.02, self::HUE_DANGER));
        $tokens[TokenContract::StateInfo->value]    = $this->hex(new Color(0.52 + $stateLightBump, $stateChroma, self::HUE_INFO));

        $tokens[TokenContract::FocusRing->value] = $this->hex($accent);

        $chartKeys = [
            TokenContract::Chart1, TokenContract::Chart2, TokenContract::Chart3, TokenContract::Chart4,
            TokenContract::Chart5, TokenContract::Chart6, TokenContract::Chart7, TokenContract::Chart8,
        ];
        $chartBase = $mode === SkinMode::Dark ? 0.65 : 0.55;
        foreach ($chartKeys as $i => $role) {
            $h = fmod($hue + $i * 45.0, 360.0);
            $l = $chartBase - ($i % 2) * 0.05;
            $tokens[$role->value] = $this->hex(new Color($l, 0.17, $h));
        }

        return $tokens;
    }

    /**
     * @param array<string, string> $knobs
     * @return array<string, string>
     */
    private function generateNonColorTokens(array $knobs, SkinMode $mode): array
    {
        $offset = match ($knobs['shadow_offset']) {
            'pronounced' => 8,
            'extreme'    => 12,
            default      => 4,
        };

        // Neutral shadow flips with mode: matte-black on light, near-white on dark.
        // Brand mode always pulls from --ui-accent-brand.
        $neutralShadow = $mode === SkinMode::Dark ? 'rgba(240, 240, 240, 1)' : 'rgba(0, 0, 0, 1)';
        $shadowColor = $knobs['shadow_color_mode'] === 'brand'
            ? 'var(--ui-accent-brand)'
            : $neutralShadow;

        // Zero radius everywhere except pill — the signature brutalist look
        $hardShadow = "{$offset}px {$offset}px 0 0 {$shadowColor}";

        return [
            TokenContract::RadiusNone->value => '0',
            TokenContract::RadiusSm->value   => '0',
            TokenContract::RadiusMd->value   => '0',
            TokenContract::RadiusLg->value   => '0',
            TokenContract::RadiusPill->value => '9999px',

            TokenContract::ShadowXs->value => "2px 2px 0 0 {$shadowColor}",
            TokenContract::ShadowSm->value => $hardShadow,
            TokenContract::ShadowMd->value => ($offset * 2) . 'px ' . ($offset * 2) . "px 0 0 {$shadowColor}",
            TokenContract::ShadowLg->value => ($offset * 3) . 'px ' . ($offset * 3) . "px 0 0 {$shadowColor}",
            TokenContract::ShadowColor->value => $shadowColor,

            TokenContract::MotionDurationFast->value   => '0ms',
            TokenContract::MotionDurationNormal->value => '0ms',
            TokenContract::MotionDurationSlow->value   => '0ms',
            TokenContract::MotionEasingStandard->value    => 'linear',
            TokenContract::MotionEasingEmphasized->value  => 'linear',

            TokenContract::SurfaceBlur->value       => 'none',
            TokenContract::SurfaceSaturation->value => '100%',
        ];
    }

    private function normalizeSeed(Color $seed, string $contrastBoost): Color
    {
        $l = max(0.30, min(0.65, $seed->l));
        // Brutalist wants more saturated accents
        $chromaMax = match ($contrastBoost) {
            'high'    => 0.28,
            'extreme' => 0.32,
            default   => 0.25,
        };
        $c = min($chromaMax, max(0.14, $seed->c));
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
