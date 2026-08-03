<?php

declare(strict_types=1);

namespace Semitexa\Theme\Application\Service\Skin\Algorithm;

use Semitexa\Theme\Application\Service\Skin\Oklch\Color;
use Semitexa\Theme\Application\Service\Skin\Oklch\ContrastScore;
use Semitexa\Theme\Application\Service\Skin\Oklch\Converter;
use Semitexa\Theme\Application\Service\Skin\SkinMode;

/**
 * The accessibility floor every skin algorithm has to respect, whatever look it
 * is going for.
 *
 * Balanced, Brutalist and Glass differ entirely in how they choose colour — and
 * agreed, verbatim, on what to do when a chosen colour is not readable against
 * its background. That agreement is not a coincidence to be deduplicated for
 * tidiness: a skin that renders unreadable text is broken no matter how good it
 * looks, so this is the one rule none of them is allowed to reinterpret.
 *
 * A trait rather than a base class because all three are `final` and implement
 * SkinAlgorithmInterface directly; none of them carries injected state, so
 * flattening these three stateless helpers in has no container implications.
 */
trait EnforcesContrast
{
    /**
     * Walk a colour's lightness until it clears the contrast floor against a
     * reference, then give up gracefully.
     *
     * Direction follows the mode — lighten on dark skins, darken on light ones —
     * toward a bound short of pure white/black so the nudge stays within the
     * palette's character. The 20-step cap matters: contrast is not guaranteed to
     * be reachable (a mid-grey reference can defeat both directions), and an
     * unbounded loop here would hang skin generation. When the walk runs out,
     * plain white or near-black is returned — ugly against some palettes, but
     * readable, which is the property being defended.
     *
     * Note that the cap and the bound do not quite line up. Contrast is checked
     * before each step, so the walk evaluates the seed plus 19 steps of 0.03 —
     * 0.57 of lightness — against seeds normalizeSeed() clamps to roughly
     * [0.35, 0.70]. That reaches the bound from most of the range, but not from
     * its far end: a dark-mode seed below ~0.38 tops out around 0.92 and a
     * light-mode seed above ~0.67 bottoms out around 0.13, so those never test the
     * bound itself and fall through to white/near-black one step early. Carried
     * over verbatim from the three copies this trait replaced; driving the loop
     * from the range rather than a fixed count would close it, at the cost of
     * changing generated skins.
     */
    private function ensureContrastAgainst(Color $color, string $referenceHex, float $floor, SkinMode $mode): Color
    {
        $attempt = $color;
        $step = $mode === SkinMode::Dark ? 0.03 : -0.03;
        $bound = $mode === SkinMode::Dark ? 0.95 : 0.10;

        for ($i = 0; $i < 20; $i++) {
            if (ContrastScore::contrast($this->hex($attempt), $referenceHex) >= $floor) {
                return $attempt;
            }

            $next = $attempt->l + $step;
            $next = $mode === SkinMode::Dark ? min($bound, $next) : max($bound, $next);
            $attempt = $attempt->withLightness($next);
        }

        return $mode === SkinMode::Dark
            ? Converter::hexToOklch('#ffffff')
            : Converter::hexToOklch('#111111');
    }

    /**
     * Choose the text colour that reads best on an accent — whichever of white
     * or near-black wins on contrast, rather than assuming light accents take
     * dark text.
     */
    private function pickOnAccentText(string $accentHex): string
    {
        return ContrastScore::contrast($accentHex, '#ffffff') >= ContrastScore::contrast($accentHex, '#111111')
            ? '#ffffff'
            : '#111111';
    }

    private function hex(Color $color): string
    {
        return Converter::oklchToHex($color);
    }
}
