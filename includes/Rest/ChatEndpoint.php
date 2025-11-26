<?php

declare(strict_types=1);

namespace WPNativeAgent\Rest;

use WPNativeAgent\Agent\Context;
use WPNativeAgent\Agent\SystemPromptBuilder;
use WPNativeAgent\Security\CancelSignal;
use WPNativeAgent\Security\InputSanitizer;
use WPNativeAgent\Security\RateLimiter;

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

        $requestedTools = $this->inputSanitizer->sanitize_enabled_tools($this->request_param($request, 'enabled_tools', []));
        $enabledTools = $this->resolve_enabled_tools($requestedTools);
        $globalSystemPrompt = $this->read_string_option('get_system_prompt', '');
        $systemPromptOverride = $this->inputSanitizer->sanitize_system_prompt(
            $this->request_param($request, 'system_prompt_override', '')
        );
        $systemPromptMode = $this->sanitize_system_prompt_mode(
            $this->request_param($request, 'system_prompt_mode', 'override')
        );
        $systemPrompt = $this->systemPromptBuilder->build(
            $globalSystemPrompt,
            $systemPromptOverride,
            $systemPromptMode
        );
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

        $events = [];
        try {
            foreach (call_user_func($this->runner, $context, $message) as $event) {
                if (is_array($event)) {
                    $events[] = $event;
                }
            }
        } catch (\Throwable $exception) {
            return $this->runner_exception_response($exception);
        }

        return [
            'ok' => true,
            'session_id' => $sessionId,
            'events' => $events,
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
     * @param array<int, string> $requestedTools
     * @return array<int, string>
     */
    private function resolve_enabled_tools(array $requestedTools): array
    {
        $globalTools = $this->read_enabled_tools_option();
        if ($globalTools === null) {
            return $requestedTools;
        }

        if ($globalTools === []) {
            return [];
        }

        if ($requestedTools === []) {
            return $globalTools;
        }

        $globalIndex = array_fill_keys($globalTools, true);

        return array_values(array_filter(
            $requestedTools,
            static fn (string $toolName): bool => isset($globalIndex[$toolName])
        ));
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

    private function sanitize_system_prompt_mode(mixed $value): string
    {
        return $value === 'append' ? 'append' : 'override';
    }
}
