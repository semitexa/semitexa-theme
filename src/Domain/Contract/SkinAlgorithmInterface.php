<?php

declare(strict_types=1);

namespace Semitexa\Theme\Domain\Contract;

use Semitexa\Theme\Application\Service\Skin\SkinPalette;
use Semitexa\Theme\Application\Service\Skin\SkinParams;

interface SkinAlgorithmInterface
{
    public function id(): string;

    public function generate(SkinParams $params): SkinPalette;

    /**
     * Short human-readable character description. Surfaced in LLM prompts so
     * the model can match user vibes to algorithm identity, and in CLI help.
     */
    public function description(): string;

    /**
     * Schema for this algorithm's tunable knobs. Shape:
     *   [
     *     'knob_name' => [
     *       'enum' => ['value-a', 'value-b', 'value-c'],
     *       'default' => 'value-b',
     *       'description' => 'Human hint for LLM/CLI.'
     *     ],
     *     ...
     *   ]
     * Used by the generator + LLM validator. May return [] for algorithms
     * with no tunable knobs.
     *
     * @return array<string, array{enum: list<string>, default: string, description: string}>
     */
    public function knobSchema(): array;
}
