<?php

declare(strict_types=1);

namespace Semitexa\Theme\Tests\Unit\Skin;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Semitexa\Theme\Application\Service\Skin\Algorithm\BalancedAlgorithm;
use Semitexa\Theme\Application\Service\Skin\Algorithm\BrutalistAlgorithm;
use Semitexa\Theme\Application\Service\Skin\Algorithm\GlassAlgorithm;
use Semitexa\Theme\Application\Service\Skin\KnobResolver;
use Semitexa\Theme\Application\Service\Skin\Oklch\ContrastScore;
use Semitexa\Theme\Application\Service\Skin\SkinBuilder;
use Semitexa\Theme\Domain\Contract\SkinAlgorithmInterface;

/**
 * The label on a brand-filled button is readable in every generated skin, in
 * both modes.
 *
 * MEASURED before this guard (2026-10-04, found by the UI Workbench browser
 * audit): the default skin's dark accent #5f6eea sat in the luminance band
 * where neither white (4.28:1) nor near-black (4.41:1) clears AA, so every
 * primary button in dark mode failed. The dark accent is now lifted to a
 * light tone that near-black reads on.
 */
final class OnColorContrastTest extends TestCase
{
    /** @return iterable<string, array{SkinAlgorithmInterface, string}> */
    public static function skins(): iterable
    {
        $seeds = ['#4f5bd5', '#3f3f46', '#0369a1', '#15803d', '#ea580c', '#e11d48', '#7c3aed', '#1d4ed8', '#f0c000', '#00a3a3'];
        foreach ([new BalancedAlgorithm(), new GlassAlgorithm(), new BrutalistAlgorithm()] as $algorithm) {
            foreach ($seeds as $seed) {
                yield $algorithm->id() . ' ' . $seed => [$algorithm, $seed];
            }
        }
    }

    #[Test]
    #[DataProvider('skins')]
    public function text_on_the_accent_clears_aa_in_both_modes(SkinAlgorithmInterface $algorithm, string $seed): void
    {
        $palette = (new SkinBuilder())->buildDualPalette($algorithm, $seed, KnobResolver::resolve([], $algorithm->knobSchema()));

        foreach (['light' => $palette->light, 'dark' => $palette->dark] as $mode => $tokens) {
            $ratio = ContrastScore::contrast($tokens['--ui-accent-brand'], $tokens['--ui-text-on-accent']);
            self::assertGreaterThanOrEqual(4.5, $ratio, sprintf(
                '%s %s %s: %s on %s is %.2f:1',
                $algorithm->id(), $seed, $mode, $tokens['--ui-text-on-accent'], $tokens['--ui-accent-brand'], $ratio,
            ));
        }
    }
}
