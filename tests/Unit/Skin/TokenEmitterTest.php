<?php

declare(strict_types=1);

namespace Semitexa\Theme\Tests\Unit\Skin;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Semitexa\Theme\Application\Service\Skin\DualSkinPalette;
use Semitexa\Theme\Application\Service\Skin\EmitterContext;
use Semitexa\Theme\Application\Service\Skin\TokenContract;
use Semitexa\Theme\Application\Service\Skin\TokenEmitter;

/**
 * `light-dark()` accepts colours only. Wrapping a whole shadow in it made every
 * `--ui-shadow-*` token invalid at computed-value time, so no skin ever drew a
 * shadow. These tests pin the shapes the emitter may write.
 */
final class TokenEmitterTest extends TestCase
{
    #[Test]
    public function colours_that_differ_use_light_dark(): void
    {
        $css = $this->emit([TokenContract::AccentBrand->value => ['#3355ff', '#7799ff']]);

        self::assertStringContainsString('--ui-accent-brand:', $css);
        self::assertMatchesRegularExpression('/--ui-accent-brand:\s+light-dark\(#3355ff, #7799ff\);/', $css);
    }

    #[Test]
    public function a_shadow_switches_its_colours_not_the_whole_value(): void
    {
        $css = $this->emit([TokenContract::ShadowMd->value => [
            '0 4px 6px -1px rgba(0, 0, 0, 0.1)',
            '0 4px 6px -1px rgba(0, 0, 0, 0.3)',
        ]]);

        self::assertMatchesRegularExpression(
            '/--ui-shadow-md:\s+0 4px 6px -1px light-dark\(rgba\(0, 0, 0, 0\.1\), rgba\(0, 0, 0, 0\.3\)\);/',
            $css,
        );
        self::assertStringNotContainsString('light-dark(0 4px', $css);
    }

    #[Test]
    public function a_layered_shadow_switches_each_colour_and_keeps_shared_ones(): void
    {
        $css = $this->emit([TokenContract::ShadowLg->value => [
            '0 1px 0 #ffffff, 0 8px 16px rgba(0, 0, 0, 0.1)',
            '0 1px 0 #ffffff, 0 8px 16px rgba(0, 0, 0, 0.5)',
        ]]);

        self::assertMatchesRegularExpression(
            '/--ui-shadow-lg:\s+0 1px 0 #ffffff, 0 8px 16px light-dark\(rgba\(0, 0, 0, 0\.1\), rgba\(0, 0, 0, 0\.5\)\);/',
            $css,
        );
    }

    #[Test]
    public function a_value_whose_geometry_differs_gets_a_dark_block(): void
    {
        $css = $this->emit([TokenContract::ShadowSm->value => [
            '2px 2px 0 0 #000000',
            '4px 4px 0 0 #ffffff',
        ]]);

        self::assertMatchesRegularExpression('/--ui-shadow-sm:\s+2px 2px 0 0 #000000;/', $css);
        self::assertStringContainsString(
            '@media (prefers-color-scheme: dark) { :root:not([data-skin-mode="light"]) { --ui-shadow-sm: 4px 4px 0 0 #ffffff; } }',
            $css,
        );
        self::assertStringContainsString(':root[data-skin-mode="dark"] { --ui-shadow-sm: 4px 4px 0 0 #ffffff; }', $css);
    }

    #[Test]
    public function no_light_dark_ever_wraps_a_non_colour(): void
    {
        $css = $this->emit([
            TokenContract::ShadowXs->value => ['0 1px 2px 0 rgba(0, 0, 0, 0.05)', '0 1px 2px 0 rgba(0, 0, 0, 0.15)'],
            TokenContract::MotionDurationFast->value => ['120ms', '160ms'],
        ]);

        preg_match_all('/light-dark\(([^,()]+(?:\([^()]*\))?)/', $css, $m);
        foreach ($m[1] as $first) {
            self::assertMatchesRegularExpression('/^(#|rgba?\(|hsla?\(|oklch\(|transparent|currentColor)/i', $first);
        }
        self::assertStringContainsString(':root[data-skin-mode="dark"] { --ui-motion-duration-fast: 160ms; }', $css);
    }

    /**
     * @param array<string, array{0: string, 1: string}> $overrides token => [light, dark]
     */
    private function emit(array $overrides): string
    {
        $light = [];
        $dark = [];
        foreach (TokenContract::cases() as $case) {
            $light[$case->value] = '#808080';
            $dark[$case->value] = '#808080';
        }
        foreach ($overrides as $name => [$l, $d]) {
            $light[$name] = $l;
            $dark[$name] = $d;
        }

        return (new TokenEmitter())->emit(
            new DualSkinPalette($light, $dark),
            new EmitterContext('test', 'balanced', 'test', '#808080', '2026-10-04T00:00:00Z'),
        );
    }
}
