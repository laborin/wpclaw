<?php

declare(strict_types=1);

namespace WPClaw\Agent;

/**
 * Immutable execution context with user and session related data.
 */
final class Context
{
    /**
     * @param array<int, string> $enabledTools
     */
    public function __construct(
        private readonly int $userId,
        private readonly int $sessionId,
        private readonly bool $toolsAllowed = true,
        private readonly string $providerId = 'openrouter',
        private readonly string $model = '',
        private readonly array $enabledTools = [],
        private readonly string $systemPrompt = ''
    ) {
    }

    public static function minimal(int $userId, int $sessionId): self
    {
        return new self($userId, $sessionId);
    }

    public function user_id(): int
    {
        return $this->userId;
    }

    public function session_id(): int
    {
        return $this->sessionId;
    }

    public function tools_allowed(): bool
    {
        return $this->toolsAllowed;
    }

    public function provider_id(): string
    {
        return $this->providerId;
    }

    public function model(): string
    {
        return $this->model;
    }

    /**
     * @return array<int, string>
     */
    public function enabled_tools(): array
    {
        return $this->enabledTools;
    }

    public function system_prompt(): string
    {
        return $this->systemPrompt;
    }
}
