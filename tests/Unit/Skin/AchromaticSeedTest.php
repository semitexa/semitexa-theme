<?php

declare(strict_types=1);

namespace Semitexa\Theme\Tests\Unit\Skin;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Semitexa\Theme\Application\Service\Skin\Algorithm\BalancedAlgorithm;
use Semitexa\Theme\Application\Service\Skin\Algorithm\BrutalistAlgorithm;
use Semitexa\Theme\Application\Service\Skin\Algorithm\GlassAlgorithm;
use Semitexa\Theme\Application\Service\Skin\Oklch\Converter;
use Semitexa\Theme\Application\Service\Skin\SkinMode;
use Semitexa\Theme\Application\Service\Skin\SkinParams;
use Semitexa\Theme\Domain\Contract\SkinAlgorithmInterface;

/**
 * A black, white or grey seed is a brand without a hue. OKLCH still reports
 * one for it (~89.9, a yellow), and the algorithms built on it: Brutalist
 * boosted the chroma and turned '#111111' into a brown accent, and every
 * algorithm tinted the surfaces and text yellow.
 */
final class AchromaticSeedTest extends TestCase
{
    private const NEUTRAL_TOKENS = [
        '--ui-accent-brand', '--ui-surface-page', '--ui-surface-panel', '--ui-surface-sunken',
        '--ui-border-subtle', '--ui-border-strong', '--ui-text-primary', '--ui-text-muted',
    ];

    /** @return iterable<string, array{SkinAlgorithmInterface, string, SkinMode}> */
    public static function achromaticSeeds(): iterable
    {
        foreach (['brutalist' => new BrutalistAlgorithm(), 'balanced' => new BalancedAlgorithm(), 'glass' => new GlassAlgorithm()] as $id => $algorithm) {
            foreach (['#111111', '#ffffff', '#808080'] as $seed) {
                foreach ([SkinMode::Light, SkinMode::Dark] as $mode) {
                    yield "{$id} {$seed} {$mode->value}" => [$algorithm, $seed, $mode];
                }
            }
        }
    }

    #[Test]
    #[DataProvider('achromaticSeeds')]
    public function an_achromatic_seed_gives_grey_neutrals_and_a_grey_accent(SkinAlgorithmInterface $algorithm, string $seed, SkinMode $mode): void
    {
        $tokens = $algorithm->generate(new SkinParams($seed, mode: $mode))->tokens;

        foreach (self::NEUTRAL_TOKENS as $token) {
            self::assertLessThan(0.005, Converter::hexToOklch($tokens[$token])->c, "{$token} = {$tokens[$token]} is tinted");
        }
    }

    #[Test]
    public function a_chromatic_seed_keeps_its_hue(): void
    {
        $tokens = (new BrutalistAlgorithm())->generate(new SkinParams('#2563eb'))->tokens;

        self::assertGreaterThan(0.1, Converter::hexToOklch($tokens['--ui-accent-brand'])->c);
        self::assertGreaterThan(0.0, Converter::hexToOklch($tokens['--ui-surface-panel'])->c);
    }
}
