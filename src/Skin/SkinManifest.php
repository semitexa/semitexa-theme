<?php

declare(strict_types=1);

namespace Semitexa\Theme\Skin;

/**
 * On-disk representation of a skin (`src/skins/<slug>/skin.json`).
 *
 * Schema 3.0 — `tokens` carries both `light` and `dark` covers of the full
 * TokenContract. The single per-skin `mode` field is gone: every skin
 * describes both modes, the active one is selected at runtime via
 * `data-skin-mode`.
 *
 * v2 manifests (single `tokens` map + top-level `mode`) are unsupported.
 * `fromArray()` throws with an explicit migration message; there is no
 * runtime fallback.
 */
final readonly class SkinManifest
{
    public const SCHEMA_VERSION = '3.0';

    /**
     * @param array<string, string>      $knobs   Resolved algorithm knobs.
     * @param list<array<string, mixed>> $history Append-only generate/refine log.
     * @param array<string, mixed>|null  $llm     LLM resolution metadata, if applicable.
     */
    public function __construct(
        public string $name,
        public string $source,
        public string $algorithm,
        public ?string $seedHex,
        public DualSkinPalette $tokens,
        public array $knobs = [],
        public array $history = [],
        public ?string $prompt = null,
        public ?array $llm = null,
        public string $generatedAt = '',
        public string $updatedAt = '',
        public ?string $description = null,
    ) {
    }

    public function emitterContext(): EmitterContext
    {
        return new EmitterContext(
            name: $this->name,
            algorithm: $this->algorithm,
            source: $this->source,
            seedHex: $this->seedHex,
            generatedAt: $this->generatedAt,
            description: $this->description,
        );
    }

    public function toArray(): array
    {
        $out = [
            'name' => $this->name,
            'schema_version' => self::SCHEMA_VERSION,
            'source' => $this->source,
            'algorithm' => $this->algorithm,
            'seed' => $this->seedHex,
            'knobs' => $this->knobs,
            'generated_at' => $this->generatedAt,
            'updated_at' => $this->updatedAt !== '' ? $this->updatedAt : $this->generatedAt,
            'history' => $this->history,
            'tokens' => [
                'light' => $this->tokens->light,
                'dark' => $this->tokens->dark,
            ],
        ];
        if ($this->description !== null && $this->description !== '') {
            $out['description'] = $this->description;
        }
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
        return json_encode($this->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $version = $data['schema_version'] ?? null;
        if ($version !== self::SCHEMA_VERSION) {
            throw new \InvalidArgumentException(sprintf(
                "skin.json schema_version '%s' is not supported. Required: '%s'. "
                . "Pre-3.0 manifests carried a single 'tokens' map and a top-level 'mode' field; "
                . "3.0 splits tokens into 'tokens.light' + 'tokens.dark' and removes 'mode'. "
                . "Re-curate or regenerate the skin and re-emit via `bin/semitexa skins:rebuild <slug>`.",
                is_string($version) || is_int($version) ? (string) $version : 'missing',
                self::SCHEMA_VERSION,
            ));
        }

        foreach (['name', 'source', 'algorithm', 'tokens'] as $field) {
            if (!array_key_exists($field, $data)) {
                throw new \InvalidArgumentException("skin.json missing required field '{$field}'.");
            }
        }
        if (!is_array($data['tokens']) || !isset($data['tokens']['light'], $data['tokens']['dark'])) {
            throw new \InvalidArgumentException(
                "skin.json 'tokens' must be an object with 'light' and 'dark' maps."
            );
        }

        $light = self::asStringMap($data['tokens']['light'], 'tokens.light');
        $dark  = self::asStringMap($data['tokens']['dark'], 'tokens.dark');

        return new self(
            name: (string) $data['name'],
            source: (string) $data['source'],
            algorithm: (string) $data['algorithm'],
            seedHex: isset($data['seed']) && $data['seed'] !== '' ? (string) $data['seed'] : null,
            tokens: new DualSkinPalette($light, $dark),
            knobs: self::asStringMap($data['knobs'] ?? [], 'knobs', allowEmpty: true),
            history: array_values((array) ($data['history'] ?? [])),
            prompt: isset($data['prompt']) ? (string) $data['prompt'] : null,
            llm: isset($data['llm']) && is_array($data['llm']) ? $data['llm'] : null,
            generatedAt: (string) ($data['generated_at'] ?? ''),
            updatedAt: (string) ($data['updated_at'] ?? ''),
            description: isset($data['description']) ? (string) $data['description'] : null,
        );
    }

    public static function fromJson(string $json): self
    {
        try {
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new \InvalidArgumentException('skin.json is not valid JSON: ' . $e->getMessage(), previous: $e);
        }
        if (!is_array($data)) {
            throw new \InvalidArgumentException('skin.json must decode to an object.');
        }
        return self::fromArray($data);
    }

    /**
     * @return array<string, string>
     */
    private static function asStringMap(mixed $raw, string $field, bool $allowEmpty = false): array
    {
        if (!is_array($raw)) {
            throw new \InvalidArgumentException("skin.json '{$field}' must be an object.");
        }
        $out = [];
        foreach ($raw as $k => $v) {
            if (!is_string($k) || !is_string($v)) {
                throw new \InvalidArgumentException("skin.json '{$field}' must be a string => string map.");
            }
            $out[$k] = $v;
        }
        if (!$allowEmpty && $out === []) {
            throw new \InvalidArgumentException("skin.json '{$field}' must not be empty.");
        }
        return $out;
    }
}
