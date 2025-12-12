<?php

declare(strict_types=1);

namespace WPClaw\Tools;

/**
 * Result value object for tool execution success, errors and denials.
 */
final class ExecutionResult
{
    private function __construct(
        public readonly bool $ok,
        public readonly mixed $payload = null,
        public readonly ?string $error = null,
        public readonly ?string $code = null
    ) {
    }

    public static function success(mixed $payload): self
    {
        return new self(true, $payload, null, null);
    }

    public static function error(string $message, ?string $code = null): self
    {
        return new self(false, null, $message, $code);
    }

    public static function denied(string $reason): self
    {
        return new self(false, null, $reason, 'denied');
    }
}
