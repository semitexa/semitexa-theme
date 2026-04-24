<?php

declare(strict_types=1);

namespace Semitexa\Theme\Skin;

final readonly class SkinManifest
{
    public const SCHEMA_VERSION = '2.0';

    /**
     * @param array<string, string> $tokens
     * @param array<string, string> $knobs Resolved algorithm knobs (full schema,
     *                                     defaults applied). Empty for v1 legacy.
     * @param list<array<string, mixed>> $history Append-only log of generate
     *                                            + refine events. Mutated only
     *                                            by the commands.
     * @param array<string, mixed>|null $llm
     */
    public function __construct(
        public string $name,
        public string $source,
        public string $algorithm,
        public string $seedHex,
        public array $tokens,
        public array $knobs = [],
        public array $history = [],
        public ?string $prompt = null,
        public ?array $llm = null,
        public string $generatedAt = '',
        public string $updatedAt = '',
        public SkinMode $mode = SkinMode::Light,
    ) {
    }

    public function toArray(): array
    {
        $out = [
            'name' => $this->name,
            'schema_version' => self::SCHEMA_VERSION,
            'source' => $this->source,
            'algorithm' => $this->algorithm,
            'mode' => $this->mode->value,
            'seed' => $this->seedHex,
            'knobs' => $this->knobs,
            'generated_at' => $this->generatedAt,
            'updated_at' => $this->updatedAt !== '' ? $this->updatedAt : $this->generatedAt,
            'history' => $this->history,
            'tokens' => $this->tokens,
        ];
        if ($this->prompt !== null) {
            $out['prompt'] = $this->prompt;
        }
        if ($this->llm !== null) {
            $out['llm'] = $this->llm;
        }
        return $out;
    }

    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
    }
}
