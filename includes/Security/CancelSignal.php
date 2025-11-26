<?php

declare(strict_types=1);

namespace WPNativeAgent\Security;

/**
 * Cancel flag object used to stop long running streaming operations.
 */
final class CancelSignal
{
    /**
     * @var array<int, bool>
     */
    private static array $memoryFlags = [];

    /**
     * @var callable|null
     */
    private $checker;

    /**
     * @var callable|null
     */
    private $requester;

    private bool $cancelled = false;

    public function __construct(?callable $checker = null, ?callable $requester = null)
    {
        $this->checker = $checker;
        $this->requester = $requester;
    }

    public static function never(): self
    {
        return new self(static fn (): bool => false);
    }

    public static function for_session(int $sessionId): self
    {
        return new self(
            static fn (): bool => self::is_session_cancelled($sessionId),
            static fn (): bool => self::request_for_session($sessionId)
        );
    }

    public static function request_for_session(int $sessionId): bool
    {
        if ($sessionId < 1) {
            return false;
        }

        if (function_exists('set_transient')) {
            return (bool) set_transient(self::session_key($sessionId), 1, 15 * 60);
        }

        self::$memoryFlags[$sessionId] = true;

        return true;
    }

    public static function clear_for_session(int $sessionId): bool
    {
        if ($sessionId < 1) {
            return false;
        }

        if (function_exists('delete_transient')) {
            return (bool) delete_transient(self::session_key($sessionId));
        }

        unset(self::$memoryFlags[$sessionId]);

        return true;
    }

    public function request_cancel(): void
    {
        $this->cancelled = true;

        if ($this->requester !== null) {
            call_user_func($this->requester);
        }
    }

    public function is_cancelled(): bool
    {
        if ($this->cancelled) {
            return true;
        }

        if ($this->checker === null) {
            return false;
        }

        return (bool) call_user_func($this->checker);
    }

    private static function is_session_cancelled(int $sessionId): bool
    {
        if ($sessionId < 1) {
            return false;
        }

        if (function_exists('get_transient')) {
            return (int) get_transient(self::session_key($sessionId)) === 1;
        }

        return self::$memoryFlags[$sessionId] ?? false;
    }

    private static function session_key(int $sessionId): string
    {
        return 'wpna_cancel_' . $sessionId;
    }
}
