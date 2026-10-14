<?php

declare(strict_types=1);

namespace Semitexa\Theme\Tests\Unit\Skin;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Semitexa\Theme\Application\Service\Skin\Oklch\ContrastScore;
use Semitexa\Theme\Application\Service\Skin\SkinContrastAudit;

/**
 * Every skin the framework ships clears WCAG AA on every gating pair, in both
 * modes. Before this ran, three skins put white text on an info fill at
 * 4.46:1 and ink's dark danger fill carried its text at 4.28:1; nothing
 * measured them.
 */
final class ShippedSkinContrastTest extends TestCase
{
    /** @return iterable<string, array{string}> */
    public static function shippedSkins(): iterable
    {
        $files = glob(dirname(__DIR__, 3) . '/src/Application/Static/css/skins/*/skin.json') ?: [];
        self::assertNotEmpty($files, 'no shipped skins found: the test would pass by measuring nothing');
        foreach ($files as $file) {
            yield basename(dirname($file)) => [$file];
        }
    }

    #[Test]
    #[DataProvider('shippedSkins')]
    public function a_shipped_skin_clears_aa_on_every_gating_pair(string $file): void
    {
        $skin = json_decode((string) file_get_contents($file), true, flags: JSON_THROW_ON_ERROR);
        $rows = SkinContrastAudit::audit($skin['tokens']);

        self::assertCount(2 * 16, $rows, 'both modes, every pair');
        $failures = array_map(
            static fn (array $row): string => sprintf('%s %s on %s = %s, needs %.1f', $row['mode'], $row['foreground'], $row['background'], $row['ratio'] ?? 'missing', $row['required']),
            SkinContrastAudit::failures($rows),
        );
        self::assertSame([], $failures);
    }

    #[Test]
    public function a_pair_just_under_the_line_fails_and_a_missing_token_is_not_a_pass(): void
    {
        $rows = SkinContrastAudit::audit(['light' => [
            '--ui-text-on-info' => '#ffffff',
            '--ui-state-info' => '#007cc7',
        ]]);
        $info = array_values(array_filter($rows, static fn (array $row): bool => $row['background'] === '--ui-state-info'))[0];

        self::assertSame(4.46, $info['ratio']);
        self::assertFalse($info['pass']);
        self::assertCount(15, SkinContrastAudit::failures($rows), 'every other gating pair has no tokens and fails');
    }

    #[Test]
    public function a_glass_surface_is_measured_over_the_page_and_a_missing_on_colour_is_derived(): void
    {
        $rows = SkinContrastAudit::audit(['dark' => [
            '--ui-surface-page' => '#000000',
            // 50% white over black is #808080.
            '--ui-surface-panel' => 'rgba(255, 255, 255, 0.5)',
            '--ui-text-primary' => '#ffffff',
            '--ui-state-danger' => '#c53637',
        ]]);
        $by = static fn (string $fg, string $bg): array => array_values(array_filter($rows, static fn (array $row): bool => $row['foreground'] === $fg && $row['background'] === $bg))[0];

        self::assertSame(round(ContrastScore::contrast('#ffffff', '#808080'), 2), $by('--ui-text-primary', '--ui-surface-panel')['ratio']);
        // No --ui-text-on-danger in the manifest: measured as the palette derives it.
        self::assertTrue($by('--ui-text-on-danger', '--ui-state-danger')['pass']);
    }

    #[Test]
    public function apca_matches_the_reference_values(): void
    {
        self::assertEqualsWithDelta(106.04, ContrastScore::apcaLc('#000000', '#ffffff'), 0.01);
        self::assertEqualsWithDelta(-107.88, ContrastScore::apcaLc('#ffffff', '#000000'), 0.01);
        self::assertEqualsWithDelta(63.06, ContrastScore::apcaLc('#888888', '#ffffff'), 0.01);
        self::assertSame(0.0, ContrastScore::apcaLc('#777777', '#777777'));
    }
}
