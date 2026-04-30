<?php

declare(strict_types=1);

namespace Semitexa\Theme\Skin;

final readonly class SkinParams
{
    /**
     * @param array<string, string> $knobs Algorithm-specific tunable parameters.
     *                                     Keys + allowed values per algorithm
     *                                     are defined by SkinAlgorithmInterface::knobSchema().
     *                                     Missing knobs default from the schema.
     */
    public function __construct(
        public string $seedHex,
        public SkinMode $mode = SkinMode::Light,
        public float $contrastFloor = 4.5,
        public array $knobs = [],
    ) {
    }
}
