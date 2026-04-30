<?php

declare(strict_types=1);

namespace Semitexa\Theme\Skin;

use Semitexa\Theme\Domain\Contract\SkinAlgorithmInterface;
use Semitexa\Theme\Skin\Algorithm\BalancedAlgorithm;
use Semitexa\Theme\Skin\Algorithm\BrutalistAlgorithm;
use Semitexa\Theme\Skin\Algorithm\GlassAlgorithm;

/**
 * Lookup for installed skin algorithms.
 *
 * v2 ships a hardcoded list (balanced + glass + brutalist). A future
 * revision switches to ClassDiscovery over `#[AsSkinAlgorithm]` so
 * third-party packages can contribute algorithms.
 *
 * Stateless — algorithms are cheap to instantiate.
 */
final class SkinAlgorithmRegistry
{
    /**
     * @return list<SkinAlgorithmInterface>
     */
    public function all(): array
    {
        return [
            new BalancedAlgorithm(),
            new GlassAlgorithm(),
            new BrutalistAlgorithm(),
        ];
    }

    /**
     * @return list<string>
     */
    public function ids(): array
    {
        return array_map(static fn (SkinAlgorithmInterface $a) => $a->id(), $this->all());
    }

    public function get(string $id): SkinAlgorithmInterface
    {
        foreach ($this->all() as $algorithm) {
            if ($algorithm->id() === $id) {
                return $algorithm;
            }
        }
        throw new \InvalidArgumentException(
            "Unknown algorithm '{$id}'. Available: " . implode(', ', $this->ids())
        );
    }

    public function has(string $id): bool
    {
        foreach ($this->all() as $algorithm) {
            if ($algorithm->id() === $id) {
                return true;
            }
        }
        return false;
    }
}
