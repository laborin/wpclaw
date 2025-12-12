<?php

declare(strict_types=1);

namespace WPClaw\Agent;

/**
 * Normalized chat message object used between repositories and providers.
 */
final class Message
{
    /**
     * @param array<int, array<string, mixed>>|null $toolCalls
     */
    public function __construct(
        public readonly string $role,
        public readonly string $content,
        public readonly ?array $toolCalls = null,
        public readonly ?string $toolCallId = null,
        public readonly ?string $toolName = null
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function to_provider_payload(): array
    {
        $payload = [
            'role' => $this->role,
            'content' => $this->content,
        ];

        if ($this->toolCalls !== null) {
            $payload['tool_calls'] = $this->toolCalls;
        }

        if ($this->toolCallId !== null) {
            $payload['tool_call_id'] = $this->toolCallId;
        }

        if ($this->toolName !== null) {
            $payload['name'] = $this->toolName;
        }

        return $payload;
    }
}
