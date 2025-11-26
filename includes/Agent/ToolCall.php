<?php

declare(strict_types=1);

namespace WPNativeAgent\Agent;

use RuntimeException;

/**
 * Value object for a model-emitted tool call.
 */
final class ToolCall
{
    /**
     * @param array<string, mixed> $arguments
     */
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly array $arguments
    ) {
    }

    /**
     * @param array<string, mixed> $event
     */
    public static function from_provider_event(array $event): self
    {
        $id = isset($event['id']) ? trim((string) $event['id']) : '';
        $name = isset($event['name']) ? trim((string) $event['name']) : '';
        $argumentsRaw = isset($event['arguments']) ? (string) $event['arguments'] : '';

        if ($id === '' || $name === '') {
            throw new RuntimeException('Provider tool call event is missing id or name.');
        }

        if ($argumentsRaw === '') {
            return new self($id, $name, []);
        }

        try {
            $decoded = json_decode($argumentsRaw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new RuntimeException('Tool call arguments are not valid JSON.', 0, $exception);
        }

        if (! is_array($decoded)) {
            throw new RuntimeException('Tool call arguments must decode as object/array.');
        }

        return new self($id, $name, $decoded);
    }

    /**
     * @return array<string, mixed>
     */
    public function to_array(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'arguments' => $this->arguments,
        ];
    }
}
