<?php

declare(strict_types=1);

namespace Semitexa\Theme\Skin;

/**
 * Canonical dual-mode palette: every skin carries both light and dark token
 * maps. Algorithms still produce single-mode SkinPalette instances; the
 * generator orchestrator zips two of them into this class, which is the only
 * input shape TokenEmitter accepts.
 */
final readonly class DualSkinPalette
{
    /**
     * @param array<string, string> $light  CSS custom property name => value, full TokenContract cover.
     * @param array<string, string> $dark   Same set of keys as $light.
     */
    public function __construct(
        public array $light,
        public array $dark,
    ) {
        $this->assertSameKeys();
        $this->assertCoversTokenContract();
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
