<?php

declare(strict_types=1);

namespace WPNativeAgent\Rest;

/**
 * History endpoint for fetching and deleting current user chat history.
 */
final class HistoryEndpoint
{
    /**
     * @var callable
     */
    private $currentUserId;

    public function __construct(
        private readonly object $sessionRepository,
        private readonly object $messageRepository,
        private readonly Guard $guard,
        ?callable $currentUserId = null
    ) {
        $this->currentUserId = $currentUserId ?? static fn (): int => (int) get_current_user_id();
    }

    /**
     * @return array<string, mixed>
     */
    public function get(mixed $request): array
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
                'session_id' => null,
                'messages' => [],
                'total_messages' => 0,
                'limit' => 0,
                'offset' => 0,
                'next_offset' => null,
                'has_more' => false,
                'order' => 'asc',
            ];
        }

        $sessionId = (int) $session['id'];
        $limit = (int) $this->request_param($request, 'limit', 200);
        if ($limit < 1) {
            $limit = 1;
        }
        if ($limit > 500) {
            $limit = 500;
        }

        $offset = (int) $this->request_param($request, 'offset', 0);
        if ($offset < 0) {
            $offset = 0;
        }

        $order = strtolower((string) $this->request_param($request, 'order', 'asc'));
        if ($order !== 'desc') {
            $order = 'asc';
        }

        $totalMessages = (int) $this->messageRepository->count_by_session_id($sessionId);
        $messages = $this->messageRepository->list_by_session_id($sessionId, $limit, $offset, $order === 'asc');
        $nextOffset = $offset + count($messages);
        $hasMore = $nextOffset < $totalMessages;

        return [
            'ok' => true,
            'session_id' => $sessionId,
            'messages' => $messages,
            'total_messages' => $totalMessages,
            'limit' => $limit,
            'offset' => $offset,
            'next_offset' => $hasMore ? $nextOffset : null,
            'has_more' => $hasMore,
            'order' => $order,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function delete(mixed $request): array
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
                'deleted_messages' => 0,
                'deleted_sessions' => 0,
            ];
        }

        $sessionId = (int) $session['id'];
        $deletedMessages = $this->messageRepository->delete_by_session_id($sessionId);
        $deletedSessions = $this->sessionRepository->delete_by_user_id($userId);

        return [
            'ok' => true,
            'deleted_messages' => $deletedMessages,
            'deleted_sessions' => $deletedSessions,
        ];
    }

    private function request_param(mixed $request, string $key, mixed $default = null): mixed
    {
        if (is_array($request)) {
            return $request[$key] ?? $default;
        }

        if (is_object($request) && method_exists($request, 'get_param')) {
            $value = $request->get_param($key);

            return $value !== null ? $value : $default;
        }

        return $default;
    }
}
