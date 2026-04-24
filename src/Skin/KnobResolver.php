<?php

declare(strict_types=1);

namespace Semitexa\Theme\Skin;

/**
 * Merges user-supplied knob values with an algorithm's declared schema.
 * Unknown knobs raise. Values outside the schema's enum raise. Missing
 * knobs fill from defaults.
 *
 * This keeps knob handling uniform across algorithms without forcing
 * each algorithm to hand-roll validation.
 */
final class KnobResolver
{
    /**
     * @param array<string, string> $provided
     * @param array<string, array{enum: list<string>, default: string, description: string}> $schema
     * @return array<string, string>
     * @throws \InvalidArgumentException
     */
    public static function resolve(array $provided, array $schema): array
    {
        $unknown = array_diff(array_keys($provided), array_keys($schema));
        if ($unknown !== []) {
            throw new \InvalidArgumentException(
                'Unknown knob(s): ' . implode(', ', $unknown)
                . '. Allowed: ' . implode(', ', array_keys($schema))
            );
        }

        $out = [];
        foreach ($schema as $name => $spec) {
            $value = $provided[$name] ?? $spec['default'];
            if (! in_array($value, $spec['enum'], true)) {
                throw new \InvalidArgumentException(
                    "Knob '{$name}' value '{$value}' not in enum [" . implode(',', $spec['enum']) . ']'
                );
            }
            $out[$name] = $value;
        }
        return $out;
    }
}
