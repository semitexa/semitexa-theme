<?php

declare(strict_types=1);

namespace Semitexa\Theme\Application\Service\Skin;

/**
 * Per-skin metadata threaded through TokenEmitter so the canonical CSS
 * header can carry provenance without coupling the emitter to SkinManifest.
 */
final readonly class EmitterContext
{
    public function __construct(
        public string $name,
        public string $algorithm,
        public string $source,
        public ?string $seedHex,
        public string $generatedAt,
        public ?string $description = null,
    ) {
    }
}
