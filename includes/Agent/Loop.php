<?php

declare(strict_types=1);

namespace WPNativeAgent\Agent;

use RuntimeException;
use WPNativeAgent\Provider\ProviderInterface;
use WPNativeAgent\Security\CancelSignal;
use WPNativeAgent\Tools\ExecutionResult;
use WPNativeAgent\Tools\Registry;

/**
 * Server side tool-calling loop that emits normalized stream events.
 */
final class Loop
{
    private ProviderInterface $provider;

    private Registry $toolRegistry;

    private object $messageRepository;

    private int $maxIterations;

    private ?CancelSignal $cancelSignal;

    /**
     * @var callable
     */
    private $capabilityChecker;

    public function __construct(
        ProviderInterface $provider,
        Registry $toolRegistry,
        object $messageRepository,
        int $maxIterations = 8,
        ?CancelSignal $cancelSignal = null,
        ?callable $capabilityChecker = null
    ) {
        $this->provider = $provider;
        $this->toolRegistry = $toolRegistry;
        $this->messageRepository = $messageRepository;
        $this->maxIterations = max(1, $maxIterations);
        $this->cancelSignal = $cancelSignal;
        $this->capabilityChecker = $capabilityChecker ?? static fn (string $capability): bool => current_user_can($capability);
    }

    /**
     * @return iterable<int, array<string, mixed>>
     */
    public function run(Context $context, string $userMessage): iterable
    {
        $sessionId = $context->session_id();

        $userMessageId = $this->messageRepository->add_message(
            $sessionId,
            'user',
            $userMessage,
            null,
            null,
            null,
            $this->provider->estimate_tokens($userMessage)
        );

        yield [
            'type' => 'session_ready',
            'session_id' => $sessionId,
            'message_id' => $userMessageId,
        ];

        $finalMessageId = null;
        $totalIterations = 0;

        $providerMessages = $this->build_provider_messages($context);
        $toolSchemas = $context->tools_allowed()
            ? $this->toolRegistry->provider_tool_schemas($context->enabled_tools())
            : [];

        for ($iteration = 1; $iteration <= $this->maxIterations; $iteration++) {
            $totalIterations = $iteration;

            if ($this->is_cancelled()) {
                yield [
                    'type' => 'done',
                    'stop_reason' => StopReason::Cancelled->value,
                    'total_iterations' => $iteration - 1,
                    'final_message_id' => $finalMessageId,
                ];

                return;
            }

            $assistantText = '';
            $toolCalls = [];
            $iterationLog = new IterationLog();

            foreach ($this->provider->stream_completion(
                $providerMessages,
                $toolSchemas,
                $context->model(),
                $this->cancelSignal
            ) as $event) {
                if (($event['type'] ?? '') === 'assistant_delta') {
                    $chunk = (string) ($event['text'] ?? '');
                    $assistantText .= $chunk;
                    yield [
                        'type' => 'assistant_delta',
                        'text' => $chunk,
                    ];
                    continue;
                }

                if (($event['type'] ?? '') === 'tool_call') {
                    try {
                        $toolCalls[] = ToolCall::from_provider_event($event);
                    } catch (RuntimeException $exception) {
                        yield [
                            'type' => 'error',
                            'code' => 'invalid_tool_call',
                            'message' => $exception->getMessage(),
                        ];
                    }
                }
            }

            if ($toolCalls === []) {
                $finalMessageId = $this->messageRepository->add_message(
                    $sessionId,
                    'assistant',
                    $assistantText,
                    null,
                    null,
                    null,
                    $this->provider->estimate_tokens($assistantText)
                );

                yield [
                    'type' => 'done',
                    'stop_reason' => StopReason::EndTurn->value,
                    'total_iterations' => $iteration,
                    'final_message_id' => $finalMessageId,
                ];

                return;
            }

            $assistantToolCalls = [];
            foreach ($toolCalls as $toolCall) {
                $assistantToolCalls[] = $toolCall->to_array();

                yield [
                    'type' => 'tool_call_start',
                    'call_id' => $toolCall->id,
                    'tool_name' => $toolCall->name,
                    'arguments' => $toolCall->arguments,
                ];

                $executionResult = $this->execute_tool_call($toolCall, $context);
                $iterationLog->add([
                    'call_id' => $toolCall->id,
                    'tool_name' => $toolCall->name,
                    'ok' => $executionResult->ok,
                    'code' => $executionResult->code,
                ]);

                $payload = $executionResult->ok
                    ? $executionResult->payload
                    : ['error' => $executionResult->error, 'code' => $executionResult->code];

                $this->messageRepository->add_message(
                    $sessionId,
                    'tool',
                    json_encode($payload, JSON_THROW_ON_ERROR),
                    null,
                    $toolCall->id,
                    $toolCall->name,
                    $this->provider->estimate_tokens(json_encode($payload, JSON_THROW_ON_ERROR))
                );

                yield [
                    'type' => 'tool_call_result',
                    'call_id' => $toolCall->id,
                    'ok' => $executionResult->ok,
                    'payload' => $executionResult->ok ? $executionResult->payload : null,
                    'error' => $executionResult->ok ? null : $executionResult->error,
                ];
            }

            $assistantMessageId = $this->messageRepository->add_message(
                $sessionId,
                'assistant',
                $assistantText,
                $assistantToolCalls,
                null,
                null,
                $this->provider->estimate_tokens($assistantText),
                ['iterations' => $iterationLog->all()]
            );

            yield [
                'type' => 'iteration_end',
                'iteration' => $iteration,
                'stop_reason' => 'tool_calls',
            ];

            $providerMessages = $this->build_provider_messages($context);
            $finalMessageId = $assistantMessageId;
        }

        yield [
            'type' => 'done',
            'stop_reason' => StopReason::MaxIterations->value,
            'total_iterations' => $totalIterations,
            'final_message_id' => $finalMessageId,
        ];
    }

    private function is_cancelled(): bool
    {
        return $this->cancelSignal !== null && $this->cancelSignal->is_cancelled();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function build_provider_messages(Context $context): array
    {
        $sessionId = $context->session_id();
        $rows = $this->messageRepository->list_by_session_id($sessionId, 300, 0, true);
        $messages = [];

        if ($context->system_prompt() !== '') {
            $messages[] = (new Message('system', $context->system_prompt()))->to_provider_payload();
        }

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $messages[] = (new Message(
                (string) ($row['role'] ?? 'user'),
                (string) ($row['content'] ?? ''),
                isset($row['tool_calls']) && is_array($row['tool_calls']) ? $row['tool_calls'] : null,
                isset($row['tool_call_id']) ? (string) $row['tool_call_id'] : null,
                isset($row['tool_name']) ? (string) $row['tool_name'] : null
            ))->to_provider_payload();
        }

        return $messages;
    }

    private function execute_tool_call(ToolCall $toolCall, Context $context): ExecutionResult
    {
        if (! $context->tools_allowed()) {
            return ExecutionResult::denied('You do not have permission to use tools.');
        }

        if (! in_array($toolCall->name, $context->enabled_tools(), true)) {
            return ExecutionResult::error('Tool is not enabled.', 'tool_not_enabled');
        }

        $tool = $this->toolRegistry->get($toolCall->name);
        if ($tool === null) {
            return ExecutionResult::error('Tool is not registered.', 'unknown_tool');
        }

        if (! (bool) call_user_func($this->capabilityChecker, $tool->get_required_capability())) {
            return ExecutionResult::denied('You do not have permission to use this tool.');
        }

        try {
            return $tool->execute($toolCall->arguments, $context);
        } catch (\Throwable $exception) {
            return ExecutionResult::error('Tool execution failed: ' . $exception->getMessage(), 'tool_runtime_error');
        }
    }
}
