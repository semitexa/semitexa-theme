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
use Semitexa\Theme\Application\Service\Skin\SkinBuilder;
use Semitexa\Theme\Application\Service\Skin\SkinContrastAudit;
use Semitexa\Theme\Domain\Contract\SkinAlgorithmInterface;

/**
 * A skin generated from any seed clears every gating pair of the contrast
 * audit, not only the label on the accent (OnColorContrastTest).
 *
 * MEASURED before this guard (2026-10-14): every algorithm drew the strong
 * border, the only outline of an unchecked checkbox, radio or switch, at 1.8
 * to 2.7:1 against the page; WCAG 1.4.11 asks 3:1.
 */
final class GeneratedSkinContrastTest extends TestCase
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
    public function a_generated_skin_clears_every_gating_pair(SkinAlgorithmInterface $algorithm, string $seed): void
    {
        $palette = (new SkinBuilder())->buildDualPalette($algorithm, $seed, KnobResolver::resolve([], $algorithm->knobSchema()));
        $rows = SkinContrastAudit::audit(['light' => $palette->light, 'dark' => $palette->dark]);

        $failures = array_map(
            static fn (array $row): string => sprintf('%s %s on %s = %s, needs %.1f', $row['mode'], $row['foreground'], $row['background'], $row['ratio'] ?? 'missing', $row['required']),
            SkinContrastAudit::failures($rows),
        );
        self::assertSame([], $failures);
    }
}
