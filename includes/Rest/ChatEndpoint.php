<?php

declare(strict_types=1);

namespace WPClaw\Rest;

use WPClaw\Agent\Context;
use WPClaw\Agent\SystemPromptBuilder;
use WPClaw\Security\CancelSignal;
use WPClaw\Security\InputSanitizer;
use WPClaw\Security\RateLimiter;

/**
 * Chat endpoint handler that runs one agent loop turn.
 */
final class ChatEndpoint
{
    /**
     * @var callable
     */
    private $currentUserId;

    /**
     * @var callable
     */
    private $remoteIpResolver;

    /**
     * @var callable
     */
    private $runner;

    public function __construct(
        private readonly object $sessionRepository,
        private readonly Guard $guard,
        private readonly object $roleGate,
        callable $runner,
        ?callable $currentUserId = null,
        private readonly ?RateLimiter $rateLimiter = null,
        private readonly ?object $options = null,
        ?InputSanitizer $inputSanitizer = null,
        ?callable $remoteIpResolver = null,
        ?SystemPromptBuilder $systemPromptBuilder = null
    ) {
        $this->runner = $runner;
        $this->currentUserId = $currentUserId ?? static fn (): int => (int) get_current_user_id();
        $this->inputSanitizer = $inputSanitizer ?? new InputSanitizer();
        $this->remoteIpResolver = $remoteIpResolver ?? static fn (): string => isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
        $this->systemPromptBuilder = $systemPromptBuilder ?? new SystemPromptBuilder();
    }

    private readonly InputSanitizer $inputSanitizer;

    private readonly SystemPromptBuilder $systemPromptBuilder;

    /**
     * @return array<string, mixed>
     */
    public function handle(mixed $request): array
    {
        $run = $this->prepare_run($request);
        if (($run['ok'] ?? false) !== true) {
            return $run;
        }

        $events = [];
        try {
            foreach ($this->run_events($run['context'], (string) $run['message']) as $event) {
                $events[] = $event;
            }
        } catch (\Throwable $exception) {
            return $this->runner_exception_response($exception);
        }

        return [
            'ok' => true,
            'session_id' => (int) $run['session_id'],
            'events' => $events,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function handle_stream(mixed $request): array
    {
        $run = $this->prepare_run($request);
        if (($run['ok'] ?? false) !== true) {
            return $run;
        }

        return [
            'ok' => true,
            'session_id' => (int) $run['session_id'],
            'events' => $this->stream_events($run['context'], (string) $run['message']),
        ];
    }

    /**
     * @return iterable<int, array<string, mixed>>
     */
    private function run_events(Context $context, string $message): iterable
    {
        foreach (call_user_func($this->runner, $context, $message) as $event) {
            if (is_array($event)) {
                yield $event;
            }
        }
    }

    /**
     * @return iterable<int, array<string, mixed>>
     */
    private function stream_events(Context $context, string $message): iterable
    {
        try {
            yield from $this->run_events($context, $message);
        } catch (\Throwable $exception) {
            $response = $this->runner_exception_response($exception);
            $error = isset($response['error']) && is_array($response['error'])
                ? $response['error']
                : [];

            yield [
                'type' => 'error',
                'code' => (string) ($error['code'] ?? 'chat_runtime_error'),
                'message' => (string) ($error['message'] ?? 'Chat request failed.'),
            ];

            yield [
                'type' => 'done',
                'stop_reason' => 'error',
                'total_iterations' => 0,
            ];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function prepare_run(mixed $request): array
    {
        $access = $this->guard->require_chat_access();
        if (($access['ok'] ?? false) !== true) {
            return [
                'ok' => false,
                'error' => [
                    'code' => $access['code'] ?? 'rest_forbidden',
                    'message' => $access['message'] ?? 'Access denied.',
                    'status' => $access['status'] ?? 403,
                ],
            ];
        }

        $userId = (int) call_user_func($this->currentUserId);

        if ($this->rateLimiter !== null) {
            $perMinute = $this->read_int_option('get_rate_limit_user_minute', 20);
            $perDay = $this->read_int_option('get_rate_limit_user_day', 500);
            $perIpMinute = $this->read_int_option('get_rate_limit_ip_minute', 30);

            $userRate = $this->rateLimiter->check_user_limits($userId, $perMinute, $perDay);
            if (! $userRate['allowed']) {
                return [
                    'ok' => false,
                    'error' => [
                        'code' => 'rate_limited',
                        'message' => 'User request rate limit exceeded.',
                        'status' => 429,
                    ],
                ];
            }

            $ipRate = $this->rateLimiter->check_ip_limit((string) call_user_func($this->remoteIpResolver), $perIpMinute);
            if (! $ipRate['allowed']) {
                return [
                    'ok' => false,
                    'error' => [
                        'code' => 'rate_limited',
                        'message' => 'IP request rate limit exceeded.',
                        'status' => 429,
                    ],
                ];
            }
        }

        $message = $this->inputSanitizer->sanitize_message($this->request_param($request, 'message', ''));
        if ($message === '') {
            return [
                'ok' => false,
                'error' => [
                    'code' => 'invalid_message',
                    'message' => 'Message is required.',
                    'status' => 400,
                ],
            ];
        }

        $enabledTools = $this->resolve_enabled_tools();
        $globalSystemPrompt = $this->read_string_option('get_system_prompt', '');
        $systemPrompt = $this->systemPromptBuilder->build($globalSystemPrompt);
        $session = $this->sessionRepository->get_or_create_by_user_id($userId);
        $sessionId = (int) ($session['id'] ?? 0);

        CancelSignal::clear_for_session($sessionId);

        $context = new Context(
            $userId,
            $sessionId,
            $this->roleGate->can_use_tools_current_user() && $enabledTools !== [],
            'openrouter',
            $this->inputSanitizer->sanitize_model($this->request_param($request, 'model', 'openai/gpt-4o-mini')),
            $enabledTools,
            $systemPrompt
        );

        return [
            'ok' => true,
            'session_id' => $sessionId,
            'context' => $context,
            'message' => $message,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function runner_exception_response(\Throwable $exception): array
    {
        $message = trim($exception->getMessage());

        if (preg_match('/Provider returned HTTP (\d{3})/', $message, $matches) === 1) {
            $httpCode = isset($matches[1]) ? (int) $matches[1] : 0;

            $friendlyMessage = match ($httpCode) {
                401 => 'Provider authentication failed. Check your API key.',
                402 => 'Provider request rejected by billing or credits.',
                403 => 'Provider denied access to this model or account.',
                default => $httpCode > 0
                    ? "Provider request failed with HTTP {$httpCode}."
                    : 'Provider request failed.',
            };

            return [
                'ok' => false,
                'error' => [
                    'code' => 'provider_http_error',
                    'message' => $friendlyMessage,
                    'status' => 502,
                ],
            ];
        }

        if (str_starts_with($message, 'cURL streaming failed:')) {
            return [
                'ok' => false,
                'error' => [
                    'code' => 'provider_network_error',
                    'message' => 'Provider network request failed.',
                    'status' => 502,
                ],
            ];
        }

        return [
            'ok' => false,
            'error' => [
                'code' => 'chat_runtime_error',
                'message' => 'Chat request failed due internal runtime error.',
                'status' => 500,
            ],
        ];
    }

    private function request_param(mixed $request, string $key, mixed $default = null): mixed
    {
        if (is_array($request)) {
            return $request[$key] ?? $default;
        }

        if (is_object($request)) {
            if (method_exists($request, 'get_param')) {
                $value = $request->get_param($key);

                return $value !== null ? $value : $default;
            }

            if (isset($request->{$key})) {
                return $request->{$key};
            }
        }

        return $default;
    }

    private function read_int_option(string $method, int $default): int
    {
        if ($this->options === null || ! method_exists($this->options, $method)) {
            return $default;
        }

        $value = (int) $this->options->{$method}();

        return $value > 0 ? $value : $default;
    }

    /**
     * @return array<int, string>
     */
    private function resolve_enabled_tools(): array
    {
        $globalTools = $this->read_enabled_tools_option();
        if ($globalTools === null) {
            return [];
        }

        return $globalTools;
    }

    /**
     * @return array<int, string>|null
     */
    private function read_enabled_tools_option(): ?array
    {
        if ($this->options === null || ! method_exists($this->options, 'get_enabled_tools')) {
            return null;
        }

        $value = $this->options->get_enabled_tools();
        if (! is_array($value)) {
            return [];
        }

        return $this->inputSanitizer->sanitize_enabled_tools($value);
    }

    private function read_string_option(string $method, string $default): string
    {
        if ($this->options === null || ! method_exists($this->options, $method)) {
            return $default;
        }

        $value = $this->options->{$method}();

        return is_string($value) ? $value : $default;
    }

}
