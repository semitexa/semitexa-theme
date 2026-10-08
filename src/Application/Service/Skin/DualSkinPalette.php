<?php

declare(strict_types=1);

namespace Semitexa\Theme\Application\Service\Skin;

use Semitexa\Theme\Application\Service\Skin\Oklch\OnColor;

/**
 * Canonical dual-mode palette: every skin carries both light and dark token
 * maps. Algorithms still produce single-mode SkinPalette instances; the
 * generator orchestrator zips two of them into this class, which is the only
 * input shape TokenEmitter accepts.
 */
final readonly class DualSkinPalette
{
    /**
     * Tokens computed from other tokens when a palette does not carry them.
     * Every algorithm, manifest and LLM result passes through this class, so a
     * token added here reaches all of them — and a skin.json written before it
     * existed still loads and re-emits.
     */
    private const DERIVED_ON_STATE = [
        '--ui-text-on-success' => '--ui-state-success',
        '--ui-text-on-warning' => '--ui-state-warning',
        '--ui-text-on-danger' => '--ui-state-danger',
        '--ui-text-on-info' => '--ui-state-info',
    ];

    /** @var array<string, string> */
    public array $light;

    /** @var array<string, string> */
    public array $dark;

    /**
     * @param array<string, string> $light  CSS custom property name => value, full TokenContract cover.
     * @param array<string, string> $dark   Same set of keys as $light.
     */
    public function __construct(array $light, array $dark)
    {
        $this->light = self::withDerived($light);
        $this->dark = self::withDerived($dark);
        $this->assertSameKeys();
        $this->assertCoversTokenContract();
    }

    /**
     * @param array<string, string> $tokens
     * @return array<string, string>
     */
    private static function withDerived(array $tokens): array
    {
        foreach (self::DERIVED_ON_STATE as $on => $fill) {
            if (!isset($tokens[$on]) && isset($tokens[$fill])) {
                $tokens[$on] = OnColor::pick($tokens[$fill]);
            }
        }
        return $tokens;
    }

    public static function fromPalettes(SkinPalette $light, SkinPalette $dark): self
    {
        return new self($light->tokens, $dark->tokens);
    }

    /**
     * Token names that appear identical in both modes (e.g. fixed neutrals
     * like `--ui-text-on-accent: #ffffff`). The emitter writes a single value
     * for these instead of `light-dark(x, x)`.
     *
     * @return list<string>
     */
    public function modeInvariantTokens(): array
    {
        $invariant = [];
        foreach ($this->light as $name => $value) {
            if ($this->dark[$name] === $value) {
                $invariant[] = $name;
            }
        }
        return $invariant;
    }

    private function assertSameKeys(): void
    {
        $lightKeys = array_keys($this->light);
        $darkKeys = array_keys($this->dark);
        sort($lightKeys);
        sort($darkKeys);
        if ($lightKeys !== $darkKeys) {
            $missingFromDark = array_diff($lightKeys, $darkKeys);
            $missingFromLight = array_diff($darkKeys, $lightKeys);
            throw new \InvalidArgumentException(sprintf(
                'DualSkinPalette: light and dark token sets must have identical keys. Missing from dark: [%s]. Missing from light: [%s].',
                implode(', ', $missingFromDark),
                implode(', ', $missingFromLight),
            ));
        }
    }

    private function assertCoversTokenContract(): void
    {
        $expected = array_map(static fn (TokenContract $t) => $t->value, TokenContract::cases());
        $actual = array_keys($this->light);
        $missing = array_diff($expected, $actual);
        $extra = array_diff($actual, $expected);
        if ($missing !== [] || $extra !== []) {
            throw new \InvalidArgumentException(sprintf(
                'DualSkinPalette must cover the full TokenContract surface. Missing: [%s]. Unknown: [%s].',
                implode(', ', $missing),
                implode(', ', $extra),
            ));
        }
    }
}
