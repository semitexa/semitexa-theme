<?php

declare(strict_types=1);

namespace Semitexa\Theme\Skin;

use Semitexa\Theme\Domain\Contract\SkinAlgorithmInterface;

/**
 * Drives the dual-mode generation flow shared by skins:generate and
 * skins:refine. An algorithm still produces one mode at a time; this helper
 * runs it twice (light + dark) on the same seed/knobs and zips the two
 * single-mode SkinPalettes into a DualSkinPalette — the only shape
 * TokenEmitter accepts.
 */
final class SkinBuilder
{
    /**
     * @param array<string, string> $knobs Resolved (full schema with defaults applied).
     */
    public function buildDualPalette(
        SkinAlgorithmInterface $algorithm,
        string $seedHex,
        array $knobs,
        float $contrastFloor = 4.5,
    ): DualSkinPalette {
        $light = $algorithm->generate(new SkinParams(
            seedHex: $seedHex,
            mode: SkinMode::Light,
            contrastFloor: $contrastFloor,
            knobs: $knobs,
        ));
        $dark = $algorithm->generate(new SkinParams(
            seedHex: $seedHex,
            mode: SkinMode::Dark,
            contrastFloor: $contrastFloor,
            knobs: $knobs,
        ));
        return DualSkinPalette::fromPalettes($light, $dark);
    }
}
