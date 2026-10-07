<?php

declare(strict_types=1);

namespace Semitexa\Theme\Application\Service\Skin;

/**
 * Emits the canonical skin tokens.css from a dual-mode palette.
 *
 * The output layout is fixed (see TokenSchema): file header → :root with
 * `color-scheme: light dark` → section dividers in canonical order →
 * tokens via `light-dark(L, D)` (or single value if mode-invariant) →
 * data-skin-mode pinning blocks.
 *
 * `light-dark()` only accepts colours. A mode-varying token that is not a
 * colour (a shadow) is written with the colours inside it switched instead —
 * `0 4px 6px light-dark(a, b)` — which is valid wherever a colour may appear.
 * A shadow wrapped whole in `light-dark()` is invalid at computed-value time,
 * so every `box-shadow: var(--ui-shadow-*)` silently rendered no shadow at all.
 * Values whose non-colour parts also differ between modes get mode blocks. Ordering is fully deterministic; running
 * the emitter twice on the same palette + context produces byte-identical
 * output.
 *
 * Every `tokens.css` in `src/skins/*` is produced by this single emitter —
 * algorithm-generated, manual, or LLM-prompted skins all flow through here.
 */
final class TokenEmitter
{
    /** Trailing characters used between `--ui-...` and its `:` for column alignment. */
    private const NAME_PADDING = 4;

    /** One colour literal: hex, or a colour function without nested parentheses. */
    private const COLOR_PATTERN = '/#[0-9a-fA-F]{3,8}\b|\b(?:rgba?|hsla?|oklch|oklab|lab|lch|color)\([^()]*\)/';

    public function emit(DualSkinPalette $palette, EmitterContext $ctx): string
    {
        TokenSchema::assertCoversTokenContract();

        $invariant = array_flip($palette->modeInvariantTokens());
        $lines = $this->header($ctx);
        /** @var array<string, string> $darkOnly tokens whose dark value needs its own block */
        $darkOnly = [];

        $lines[] = ':root {';
        $lines[] = '    color-scheme: light dark;';

        foreach (TokenSchema::sections() as $section) {
            $tokens = TokenSchema::tokensInSection($section);
            $width = $this->columnWidth($tokens);

            $lines[] = '';
            $lines[] = '    /* ' . $section . ' — ' . TokenSchema::sectionDescription($section) . ' */';

            foreach ($tokens as $name) {
                $lightValue = $palette->light[$name];
                $darkValue  = $palette->dark[$name];
                $isInvariant = isset($invariant[$name]);
                $value = $isInvariant ? $lightValue : $this->modeValue($lightValue, $darkValue);
                if ($value === null) {
                    $value = $lightValue;
                    $darkOnly[$name] = $darkValue;
                }
                $padding = str_repeat(' ', $width - strlen($name));
                $lines[] = sprintf('    %s:%s %s;', $name, $padding, $value);
            }
        }

        $lines[] = '}';
        $lines[] = '';
        $lines[] = ':root[data-skin-mode="light"] { color-scheme: light; }';
        $lines[] = ':root[data-skin-mode="dark"]  { color-scheme: dark; }';
        $lines[] = '';

        if ($darkOnly !== []) {
            $declarations = [];
            foreach ($darkOnly as $name => $value) {
                $declarations[] = sprintf('%s: %s;', $name, $value);
            }
            $body = implode(' ', $declarations);
            $lines[] = '@media (prefers-color-scheme: dark) { :root:not([data-skin-mode="light"]) { ' . $body . ' } }';
            $lines[] = ':root[data-skin-mode="dark"] { ' . $body . ' }';
            $lines[] = '';
        }

        return implode("\n", $lines);
    }

    /**
     * The value for a token that differs between modes, or null when it can
     * only be expressed with a separate dark block.
     */
    private function modeValue(string $light, string $dark): ?string
    {
        if (self::isColor($light) && self::isColor($dark)) {
            return sprintf('light-dark(%s, %s)', $light, $dark);
        }

        // A fragment reference such as url(#fade) looks like a hex colour, and
        // light-dark() inside url() is a bad URL token. Such a value switches
        // in the dark block instead.
        if (preg_match('/\burl\s*\(/i', $light . ' ' . $dark) === 1) {
            return null;
        }

        [$lightShape, $lightColors] = self::splitColors($light);
        [$darkShape, $darkColors] = self::splitColors($dark);
        if ($lightShape !== $darkShape || $lightColors === []) {
            return null;
        }

        $i = 0;
        return (string) preg_replace_callback(
            '/\x00/',
            static function () use (&$i, $lightColors, $darkColors): string {
                $pair = $lightColors[$i] === $darkColors[$i]
                    ? $lightColors[$i]
                    : sprintf('light-dark(%s, %s)', $lightColors[$i], $darkColors[$i]);
                $i++;
                return $pair;
            },
            $lightShape,
        );
    }

    private static function isColor(string $value): bool
    {
        $value = trim($value);
        if (preg_match('/^(?:transparent|currentColor)$/i', $value) === 1) {
            return true;
        }
        return preg_match(self::COLOR_PATTERN, $value, $m) === 1 && $m[0] === $value;
    }

    /**
     * @return array{0: string, 1: list<string>} the value with each colour replaced by NUL, and the colours in order
     */
    private static function splitColors(string $value): array
    {
        $colors = [];
        $shape = (string) preg_replace_callback(
            self::COLOR_PATTERN,
            static function (array $m) use (&$colors): string {
                $colors[] = $m[0];
                return "\x00";
            },
            $value,
        );
        return [$shape, $colors];
    }

    /**
     * @return list<string>
     */
    private function header(EmitterContext $ctx): array
    {
        $description = $ctx->description !== null && $ctx->description !== ''
            ? ' — ' . rtrim($ctx->description, '.')
            : '';
        $seed = $ctx->seedHex !== null && $ctx->seedHex !== '' ? $ctx->seedHex : 'n/a';
        return [
            "/* Skin: {$ctx->name}{$description}.",
            " * algorithm={$ctx->algorithm}  source={$ctx->source}  seed={$seed}  generated={$ctx->generatedAt}.",
            ' * Generated by semitexa/theme — do not edit manually.',
            " * Regenerate via `bin/semitexa skins:rebuild {$ctx->name}`.",
            ' */',
        ];
    }

    /**
     * @param list<string> $tokens
     */
    private function columnWidth(array $tokens): int
    {
        $max = 0;
        foreach ($tokens as $name) {
            $len = strlen($name);
            if ($len > $max) {
                $max = $len;
            }
        }
        return $max + self::NAME_PADDING;
    }
}
