<?php

declare(strict_types=1);

namespace WPClaw\Rest;

use WPClaw\Security\CancelSignal;

/**
 * Cancel endpoint that flags current user session as cancelled.
 */
final class CancelEndpoint
{
    /**
     * @var callable
     */
    private $currentUserId;

    public function __construct(
        private readonly object $sessionRepository,
        private readonly Guard $guard,
        ?callable $currentUserId = null
    ) {
        $this->currentUserId = $currentUserId ?? static fn (): int => (int) get_current_user_id();
    }

    /**
     * @return array<string, mixed>
     */
    public function handle(mixed $request): array
    {
        $access = $this->guard->require_logged_in();
        if (($access['ok'] ?? false) !== true) {
            return [
                'ok' => false,
                'error' => [
                    'code' => $access['code'] ?? 'rest_forbidden',
                    'message' => $access['message'] ?? 'Access denied.',
                    'status' => $access['status'] ?? 401,
                ],
            ];
        }

        $userId = (int) call_user_func($this->currentUserId);
        $session = $this->sessionRepository->get_by_user_id($userId);
        if ($session === null) {
            return [
                'ok' => true,
                'cancelled' => false,
                'reason' => 'no_session',
            ];
        }

        $sessionId = (int) $session['id'];
        CancelSignal::request_for_session($sessionId);

        return [
            'ok' => true,
            'cancelled' => true,
            'session_id' => $sessionId,
        ];
    }
}
