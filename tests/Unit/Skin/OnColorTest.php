<?php

declare(strict_types=1);

namespace Semitexa\Theme\Tests\Unit\Skin;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Semitexa\Theme\Application\Service\Skin\DualSkinPalette;
use Semitexa\Theme\Application\Service\Skin\Oklch\OnColor;
use Semitexa\Theme\Application\Service\Skin\TokenContract;

/**
 * A shorthand hex fill is measured like its full form. Before this, #fff
 * failed the six-digit check and got white text: white on white.
 */
final class OnColorTest extends TestCase
{
    #[Test]
    public function a_shorthand_fill_is_measured_like_its_full_form(): void
    {
        self::assertSame(OnColor::DARK, OnColor::pick('#fff'));
        self::assertSame(OnColor::pick('#ffffff'), OnColor::pick('#FFF'));
        self::assertSame(OnColor::pick('#0000ff'), OnColor::pick('#00f'));
    }

    #[Test]
    public function a_derived_state_text_reads_on_a_shorthand_fill(): void
    {
        $tokens = [];
        foreach (TokenContract::cases() as $case) {
            $tokens[$case->value] = '#808080';
        }
        $tokens['--ui-state-warning'] = '#fff';
        unset($tokens['--ui-text-on-warning']);

        $palette = new DualSkinPalette($tokens, $tokens);

        self::assertSame(OnColor::DARK, $palette->light['--ui-text-on-warning']);
    }
}
