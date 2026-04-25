<?php

declare(strict_types=1);

namespace Semitexa\Theme\Skin;

final readonly class SkinPalette
{
    /** @param array<string, string> $tokens CSS custom property name => hex value */
    public function __construct(
        public array $tokens,
    ) {
    }

    public function get(TokenContract $role): string
    {
        return $this->tokens[$role->value]
            ?? throw new \OutOfBoundsException("Token role not in palette: {$role->value}");
    }
}
