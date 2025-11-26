<?php

declare(strict_types=1);

namespace WPNativeAgent\Rest;

use WPNativeAgent\Security\RoleGate;

/**
 * Shared access guard for REST endpoints.
 */
final class Guard
{
    /**
     * @var callable
     */
    private $isUserLoggedIn;

    public function __construct(
        private readonly RoleGate $roleGate,
        ?callable $isUserLoggedIn = null
    ) {
        $this->isUserLoggedIn = $isUserLoggedIn ?? static fn (): bool => is_user_logged_in();
    }

    /**
     * @return array{ok: bool, status?: int, code?: string, message?: string}
     */
    public function require_chat_access(): array
    {
        if (! (bool) call_user_func($this->isUserLoggedIn)) {
            return [
                'ok' => false,
                'status' => 401,
                'code' => 'rest_forbidden',
                'message' => 'You must be logged in to use chat.',
            ];
        }

        if (! $this->roleGate->can_chat_current_user()) {
            return [
                'ok' => false,
                'status' => 403,
                'code' => 'rest_forbidden',
                'message' => 'Your role is not allowed to use chat.',
            ];
        }

        return ['ok' => true];
    }

    /**
     * @return array{ok: bool, status?: int, code?: string, message?: string}
     */
    public function require_logged_in(): array
    {
        if (! (bool) call_user_func($this->isUserLoggedIn)) {
            return [
                'ok' => false,
                'status' => 401,
                'code' => 'rest_forbidden',
                'message' => 'You must be logged in.',
            ];
        }

        return ['ok' => true];
    }
}
