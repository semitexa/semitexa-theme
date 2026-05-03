<?php

declare(strict_types=1);

namespace Semitexa\Theme\Application\Service\Skin\Oklch;

final readonly class Color
{
    public function __construct(
        public float $l,
        public float $c,
        public float $h,
    ) {
    }

    public function withLightness(float $l): self
    {
        return new self($l, $this->c, $this->h);
    }

    public function scaleChroma(float $factor): self
    {
        return new self($this->l, $this->c * $factor, $this->h);
    }
}
