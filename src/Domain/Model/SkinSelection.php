<?php

declare(strict_types=1);

namespace Semitexa\Theme\Domain\Model;

/**
 * A theme's skin selection — either a fixed slug or a conditional block
 * with a default and per-context overrides.
 *
 * Two forms both collapse to "return skin slug for this context":
 *   - fixed: `skin: "batumi"` — same slug every time
 *   - conditional: `skin: { default: "sky", when: [{...rule...}] }` — default + overrides
 *
 * The resolver calls `resolve()` with the active ThemeContext after the
 * theme itself has been selected. Skin conditions use the same 7-level
 * specificity as theme selection; if no condition matches, `default` wins.
 */
final readonly class SkinSelection
{
    /**
     * @param list<SkinCondition> $conditions
     */
    public function __construct(
        public string $default,
        public array $conditions = [],
    ) {
    }

    public static function fixed(string $slug): self
    {
        return new self($slug, []);
    }

    public function isConditional(): bool
    {
        return $this->conditions !== [];
    }

    /** @return list<string> every skin slug this selection might produce */
    public function allPossibleSkins(): array
    {
        $slugs = [$this->default];
        foreach ($this->conditions as $c) {
            $slugs[] = $c->use;
        }
        return array_values(array_unique($slugs));
    }
}
