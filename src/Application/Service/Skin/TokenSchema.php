<?php

declare(strict_types=1);

namespace Semitexa\Theme\Application\Service\Skin;

/**
 * Canonical layout for the public CSS token surface.
 *
 * `TokenContract` enumerates the names; this class assigns each name to a
 * named section and freezes the in-section order. The emitter and the
 * `lint:skin-tokens` guard both read from here so the file layout stays
 * structurally identical across all skins.
 *
 * Adding a new public token = a new `TokenContract` case + an entry in
 * `LAYOUT` below. Anything in `TokenContract` that is missing from `LAYOUT`
 * fails the schema cross-check in `assertCovers()`.
 */
final class TokenSchema
{
    public const SECTION_COLOR = 'COLOR';
    public const SECTION_STATE = 'STATE';
    public const SECTION_CHART = 'CHART';
    public const SECTION_FORM = 'FORM';
    public const SECTION_DEPTH = 'DEPTH';
    public const SECTION_MOTION = 'MOTION';
    public const SECTION_EFFECT = 'EFFECT';

    /**
     * Section descriptions surfaced as CSS comments.
     *
     * @var array<string, string>
     */
    private const SECTION_DESCRIPTIONS = [
        self::SECTION_COLOR  => 'surfaces, borders, text, accent',
        self::SECTION_STATE  => 'semantic feedback colors + focus ring',
        self::SECTION_CHART  => 'categorical 8-step palette',
        self::SECTION_FORM   => 'radius scale',
        self::SECTION_DEPTH  => 'shadow scale',
        self::SECTION_MOTION => 'durations + easings',
        self::SECTION_EFFECT => 'surface filters (glass)',
    ];

    /**
     * Canonical layout — section -> ordered list of TokenContract names.
     *
     * @var array<string, list<string>>
     */
    private const LAYOUT = [
        self::SECTION_COLOR => [
            '--ui-surface-page',
            '--ui-surface-panel',
            '--ui-surface-raised',
            '--ui-surface-sunken',
            '--ui-border-subtle',
            '--ui-border-strong',
            '--ui-text-primary',
            '--ui-text-muted',
            '--ui-accent-brand',
            '--ui-accent-brand-contrast',
            '--ui-text-on-accent',
        ],
        self::SECTION_STATE => [
            '--ui-state-success',
            '--ui-state-warning',
            '--ui-state-danger',
            '--ui-state-info',
            '--ui-focus-ring',
        ],
        self::SECTION_CHART => [
            '--ui-chart-1',
            '--ui-chart-2',
            '--ui-chart-3',
            '--ui-chart-4',
            '--ui-chart-5',
            '--ui-chart-6',
            '--ui-chart-7',
            '--ui-chart-8',
        ],
        self::SECTION_FORM => [
            '--ui-radius-none',
            '--ui-radius-sm',
            '--ui-radius-md',
            '--ui-radius-lg',
            '--ui-radius-pill',
        ],
        self::SECTION_DEPTH => [
            '--ui-shadow-xs',
            '--ui-shadow-sm',
            '--ui-shadow-md',
            '--ui-shadow-lg',
            '--ui-shadow-color',
        ],
        self::SECTION_MOTION => [
            '--ui-motion-duration-fast',
            '--ui-motion-duration-normal',
            '--ui-motion-duration-slow',
            '--ui-motion-easing-standard',
            '--ui-motion-easing-emphasized',
        ],
        self::SECTION_EFFECT => [
            '--ui-surface-blur',
            '--ui-surface-saturation',
        ],
    ];

    /**
     * Sections in the canonical emit order.
     *
     * @return list<string>
     */
    public static function sections(): array
    {
        return array_keys(self::LAYOUT);
    }

    /**
     * Tokens in their canonical position within a section.
     *
     * @return list<string>
     */
    public static function tokensInSection(string $section): array
    {
        if (!isset(self::LAYOUT[$section])) {
            throw new \InvalidArgumentException("Unknown skin token section '{$section}'.");
        }
        return self::LAYOUT[$section];
    }

    public static function sectionDescription(string $section): string
    {
        return self::SECTION_DESCRIPTIONS[$section]
            ?? throw new \InvalidArgumentException("Unknown skin token section '{$section}'.");
    }

    /**
     * Every TokenContract name in canonical emit order (section, then in-section).
     *
     * @return list<string>
     */
    public static function orderedTokens(): array
    {
        $out = [];
        foreach (self::LAYOUT as $tokens) {
            foreach ($tokens as $name) {
                $out[] = $name;
            }
        }
        return $out;
    }

    public static function sectionOf(string $tokenName): string
    {
        foreach (self::LAYOUT as $section => $tokens) {
            if (in_array($tokenName, $tokens, true)) {
                return $section;
            }
        }
        throw new \InvalidArgumentException("Token '{$tokenName}' is not part of the canonical schema.");
    }

    /**
     * Cross-check: every TokenContract case must appear exactly once in LAYOUT,
     * and LAYOUT must not declare any unknown names. Run from tests/CI.
     */
    public static function assertCoversTokenContract(): void
    {
        $declared = array_map(static fn (TokenContract $t) => $t->value, TokenContract::cases());
        $laidOut  = self::orderedTokens();
        $missing  = array_values(array_diff($declared, $laidOut));
        $extra    = array_values(array_diff($laidOut, $declared));
        $duplicates = array_values(array_unique(array_diff_assoc($laidOut, array_unique($laidOut))));
        if ($missing !== [] || $extra !== [] || $duplicates !== []) {
            throw new \LogicException(sprintf(
                'TokenSchema and TokenContract are out of sync. Missing from layout: [%s]. Unknown in layout: [%s]. Duplicates: [%s].',
                implode(', ', $missing),
                implode(', ', $extra),
                implode(', ', $duplicates),
            ));
        }
    }
}
