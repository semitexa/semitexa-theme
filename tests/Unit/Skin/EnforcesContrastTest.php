<?php

declare(strict_types=1);

namespace Semitexa\Theme\Tests\Unit\Skin;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Semitexa\Theme\Application\Service\Skin\Algorithm\EnforcesContrast;
use Semitexa\Theme\Application\Service\Skin\Oklch\Color;
use Semitexa\Theme\Application\Service\Skin\Oklch\ContrastScore;
use Semitexa\Theme\Application\Service\Skin\Oklch\Converter;
use Semitexa\Theme\Application\Service\Skin\SkinMode;

/**
 * The accessibility floor shared by Balanced, Brutalist and Glass.
 *
 * These three carried this logic as three verbatim copies with no coverage on any
 * of them; ep-duplication-sweep collapsed them onto {@see EnforcesContrast}, and
 * this is what makes that collapse checkable. The properties pinned here are the
 * ones whose loss would ship unreadable text: that the walk actually reaches the
 * floor when it can, that it terminates when it cannot, and that it moves in the
 * direction the mode calls for.
 */
final class EnforcesContrastTest extends TestCase
{
    /**
     * The trait's methods are private, as they are on the algorithms that use it.
     * A local host exposes them rather than reaching in with reflection.
     */
    private function host(): object
    {
        return new class () {
            use EnforcesContrast;

            public function ensure(Color $color, string $reference, float $floor, SkinMode $mode): Color
            {
                return $this->ensureContrastAgainst($color, $reference, $floor, $mode);
            }

            public function onAccent(string $accentHex): string
            {
                return $this->pickOnAccentText($accentHex);
            }

            public function toHex(Color $color): string
            {
                return $this->hex($color);
            }
        };
    }

    #[Test]
    public function a_colour_already_over_the_floor_is_returned_untouched(): void
    {
        $host = $this->host();
        $white = Converter::hexToOklch('#ffffff');

        $result = $host->ensure($white, '#000000', 4.5, SkinMode::Dark);

        self::assertSame($white->l, $result->l);
    }

    #[Test]
    public function a_dark_skin_lightens_until_it_clears_the_floor(): void
    {
        $host = $this->host();
        // Near-black text on a black background: unreadable, and only lightening helps.
        $start = Converter::hexToOklch('#111111');

        $result = $host->ensure($start, '#000000', 4.5, SkinMode::Dark);

        self::assertGreaterThan($start->l, $result->l, 'dark mode must lighten');
        self::assertGreaterThanOrEqual(
            4.5,
            ContrastScore::contrast($host->toHex($result), '#000000'),
            'the returned colour must actually clear the floor',
        );
    }

    #[Test]
    public function a_light_skin_darkens_until_it_clears_the_floor(): void
    {
        $host = $this->host();
        $start = Converter::hexToOklch('#f4f4f4');

        $result = $host->ensure($start, '#ffffff', 4.5, SkinMode::Light);

        self::assertLessThan($start->l, $result->l, 'light mode must darken');
        self::assertGreaterThanOrEqual(
            4.5,
            ContrastScore::contrast($host->toHex($result), '#ffffff'),
            'the returned colour must actually clear the floor',
        );
    }

    #[Test]
    public function an_unreachable_floor_terminates_with_a_readable_fallback(): void
    {
        // 21:1 is above the maximum contrast any pair can reach, so the walk can
        // never succeed. What matters is that it stops — an unbounded loop here
        // hangs skin generation — and hands back something readable.
        $host = $this->host();
        $start = Converter::hexToOklch('#808080');

        $darkResult = $host->ensure($start, '#808080', 21.0, SkinMode::Dark);
        $lightResult = $host->ensure($start, '#808080', 21.0, SkinMode::Light);

        self::assertSame('#ffffff', $host->toHex($darkResult));
        self::assertSame('#111111', $host->toHex($lightResult));
    }

    /**
     * @param string $accent   background the text sits on
     * @param string $expected the colour that wins on contrast
     */
    #[Test]
    #[DataProvider('accents')]
    public function on_accent_text_picks_whichever_reads_better(string $accent, string $expected): void
    {
        self::assertSame($expected, $this->host()->onAccent($accent));
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function accents(): array
    {
        return [
            // Deliberately includes a light accent to pin that the choice is made
            // on measured contrast, not on an assumption that light takes dark text.
            'very dark accent' => ['#0b0b0b', '#ffffff'],
            'very light accent' => ['#fdfdfd', '#111111'],
        ];
    }
}
