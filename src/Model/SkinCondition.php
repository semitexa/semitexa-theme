<?php

declare(strict_types=1);

namespace Semitexa\Theme\Model;

/**
 * One entry in a theme's conditional-skin block.
 *
 * Reuses ActiveWhen's match semantics so skin selection within a theme
 * uses the same specificity math as theme selection across the manifest
 * set. Consistent UX: same axes (tenant/domain/locale), same fallback
 * order, two phases.
 */
final readonly class SkinCondition
{
    public function __construct(
        public ActiveWhen $when,
        public string $use,
    ) {
    }
}
