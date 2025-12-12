<?php

declare(strict_types=1);

namespace WPClaw\Tools;

/**
 * Minimal json schema validator for tool input payloads.
 */
final class SchemaValidator
{
    /**
     * @return array<int, string>
     */
    public function validate(array $schema, mixed $value): array
    {
        $errors = [];
        $this->validate_value($schema, $value, '$', $errors);

        return $errors;
    }

    /**
     * @param array<int, string> $errors
     */
    private function validate_value(array $schema, mixed $value, string $path, array &$errors): void
    {
        $expectedType = $schema['type'] ?? null;

        if (is_string($expectedType)) {
            if (! $this->matches_type($expectedType, $value)) {
                $errors[] = "{$path} must be of type {$expectedType}.";
                return;
            }
        }

        if (array_key_exists('enum', $schema) && is_array($schema['enum'])) {
            if (! in_array($value, $schema['enum'], true)) {
                $errors[] = "{$path} must be one of enum values.";
                return;
            }
        }

        if ($expectedType === 'object' && is_array($value)) {
            $this->validate_object($schema, $value, $path, $errors);
            return;
        }

        if ($expectedType === 'array' && is_array($value)) {
            $this->validate_array($schema, $value, $path, $errors);
            return;
        }

        if ($expectedType === 'string' && is_string($value)) {
            $this->validate_string($schema, $value, $path, $errors);
            return;
        }

        if (($expectedType === 'integer' || $expectedType === 'number') && is_int($value)) {
            $this->validate_number($schema, $value, $path, $errors);
            return;
        }

        if ($expectedType === 'number' && (is_float($value) || is_int($value))) {
            $this->validate_number($schema, (float) $value, $path, $errors);
        }
    }

    /**
     * @param array<int, string> $errors
     * @param array<string, mixed> $value
     */
    private function validate_object(array $schema, array $value, string $path, array &$errors): void
    {
        $required = $schema['required'] ?? [];
        if (is_array($required)) {
            foreach ($required as $requiredKey) {
                if (is_string($requiredKey) && ! array_key_exists($requiredKey, $value)) {
                    $errors[] = "{$path}.{$requiredKey} is required.";
                }
            }
        }

        $properties = $schema['properties'] ?? [];
        $allowAdditional = ($schema['additionalProperties'] ?? true) !== false;
        if (! is_array($properties)) {
            $properties = [];
        }

        foreach ($value as $key => $itemValue) {
            if (! is_string($key)) {
                continue;
            }

            if (! array_key_exists($key, $properties)) {
                if (! $allowAdditional) {
                    $errors[] = "{$path}.{$key} is not allowed.";
                }
                continue;
            }

            if (! is_array($properties[$key])) {
                continue;
            }

            $this->validate_value($properties[$key], $itemValue, $path . '.' . $key, $errors);
        }
    }

    /**
     * @param array<int, string> $errors
     * @param array<int|string, mixed> $value
     */
    private function validate_array(array $schema, array $value, string $path, array &$errors): void
    {
        if (! array_is_list($value)) {
            $errors[] = "{$path} must be a list array.";
            return;
        }

        if (array_key_exists('minItems', $schema) && is_int($schema['minItems']) && count($value) < $schema['minItems']) {
            $errors[] = "{$path} must contain at least {$schema['minItems']} items.";
        }

        if (array_key_exists('maxItems', $schema) && is_int($schema['maxItems']) && count($value) > $schema['maxItems']) {
            $errors[] = "{$path} must contain at most {$schema['maxItems']} items.";
        }

        if (! array_key_exists('items', $schema) || ! is_array($schema['items'])) {
            return;
        }

        foreach ($value as $index => $itemValue) {
            $this->validate_value($schema['items'], $itemValue, $path . '[' . $index . ']', $errors);
        }
    }

    /**
     * @param array<int, string> $errors
     */
    private function validate_string(array $schema, string $value, string $path, array &$errors): void
    {
        if (array_key_exists('minLength', $schema) && is_int($schema['minLength']) && mb_strlen($value) < $schema['minLength']) {
            $errors[] = "{$path} is shorter than minLength {$schema['minLength']}.";
        }

        if (array_key_exists('maxLength', $schema) && is_int($schema['maxLength']) && mb_strlen($value) > $schema['maxLength']) {
            $errors[] = "{$path} is longer than maxLength {$schema['maxLength']}.";
        }
    }

    /**
     * @param array<int, string> $errors
     */
    private function validate_number(array $schema, int|float $value, string $path, array &$errors): void
    {
        if (array_key_exists('minimum', $schema) && is_numeric($schema['minimum']) && $value < (float) $schema['minimum']) {
            $errors[] = "{$path} must be greater or equal than {$schema['minimum']}.";
        }

        if (array_key_exists('maximum', $schema) && is_numeric($schema['maximum']) && $value > (float) $schema['maximum']) {
            $errors[] = "{$path} must be lower or equal than {$schema['maximum']}.";
        }
    }

    private function matches_type(string $type, mixed $value): bool
    {
        return match ($type) {
            'object' => is_array($value),
            'array' => is_array($value),
            'string' => is_string($value),
            'integer' => is_int($value),
            'number' => is_int($value) || is_float($value),
            'boolean' => is_bool($value),
            default => true,
        };
    }
}
