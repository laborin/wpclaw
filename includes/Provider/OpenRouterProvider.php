<?php

declare(strict_types=1);

namespace WPNativeAgent\Provider;

use RuntimeException;
use WPNativeAgent\Security\CancelSignal;

/**
 * OpenRouter provider that normalizes SSE events for the agent loop.
 */
final class OpenRouterProvider implements ProviderInterface
{
    private string $apiKey;

    private string $endpoint;

    private CurlStreamer $curlStreamer;

    private StreamParser $streamParser;

    /**
     * @var array<string, int>
     */
    private array $contextWindows;

    /**
     * @param array<string, int> $contextWindows
     */
    public function __construct(
        string $apiKey,
        string $endpoint = 'https://openrouter.ai/api/v1/chat/completions',
        ?CurlStreamer $curlStreamer = null,
        ?StreamParser $streamParser = null,
        array $contextWindows = []
    ) {
        if (trim($apiKey) === '') {
            throw new RuntimeException('OpenRouter API key is required.');
        }

        $this->apiKey = $apiKey;
        $this->endpoint = $endpoint;
        $this->curlStreamer = $curlStreamer ?? new CurlStreamer();
        $this->streamParser = $streamParser ?? new StreamParser();
        $this->contextWindows = $contextWindows;
    }

    public function get_id(): string
    {
        return 'openrouter';
    }

    /**
     * @return iterable<int, array<string, mixed>>
     */
    public function stream_completion(
        array $messages,
        array $tool_schemas,
        string $model,
        ?CancelSignal $cancel = null
    ): iterable {
        $payload = [
            'model' => $model,
            'messages' => $messages,
            'stream' => true,
        ];

        if ($tool_schemas !== []) {
            $payload['tools'] = $tool_schemas;
            $payload['tool_choice'] = 'auto';
        }

        $jsonBody = json_encode($payload, JSON_THROW_ON_ERROR);

        $headers = [
            'Content-Type: application/json',
            'Accept: text/event-stream',
            'Authorization: Bearer ' . $this->apiKey,
        ];

        $chunks = $this->curlStreamer->stream(
            $this->endpoint,
            $headers,
            $jsonBody,
            $cancel !== null ? static fn (): bool => $cancel->is_cancelled() : null
        );

        /**
         * @var array<int, array{id: string, name: string, arguments: string}>
         */
        $toolCallBuffers = [];

        foreach ($chunks as $chunk) {
            foreach ($this->streamParser->push($chunk) as $event) {
                if (($event['type'] ?? '') === 'tool_call_delta') {
                    $index = (int) ($event['index'] ?? 0);
                    if (! isset($toolCallBuffers[$index])) {
                        $toolCallBuffers[$index] = [
                            'id' => '',
                            'name' => '',
                            'arguments' => '',
                        ];
                    }

                    if (is_string($event['id'] ?? null) && $event['id'] !== '') {
                        $toolCallBuffers[$index]['id'] = $event['id'];
                    }

                    if (is_string($event['name'] ?? null) && $event['name'] !== '') {
                        $toolCallBuffers[$index]['name'] = $event['name'];
                    }

                    if (is_string($event['arguments'] ?? null) && $event['arguments'] !== '') {
                        $toolCallBuffers[$index]['arguments'] .= $event['arguments'];
                    }

                    continue;
                }

                if (($event['type'] ?? '') === 'finish_reason') {
                    foreach ($this->flush_tool_calls($toolCallBuffers) as $toolCallEvent) {
                        yield $toolCallEvent;
                    }
                }

                yield $event;
            }
        }

        foreach ($this->streamParser->flush() as $event) {
            yield $event;
        }

        foreach ($this->flush_tool_calls($toolCallBuffers) as $toolCallEvent) {
            yield $toolCallEvent;
        }
    }

    public function estimate_tokens(string $text): int
    {
        return (int) ceil(strlen($text) / 4);
    }

    public function get_context_window(string $model): int
    {
        if (isset($this->contextWindows[$model]) && $this->contextWindows[$model] > 0) {
            return $this->contextWindows[$model];
        }

        return 8192;
    }

    /**
     * @param array<int, array{id: string, name: string, arguments: string}> $toolCallBuffers
     * @return array<int, array<string, mixed>>
     */
    private function flush_tool_calls(array &$toolCallBuffers): array
    {
        if ($toolCallBuffers === []) {
            return [];
        }

        $events = [];
        foreach ($toolCallBuffers as $index => $call) {
            $events[] = [
                'type' => 'tool_call',
                'id' => $call['id'] !== '' ? $call['id'] : 'call_' . $index,
                'name' => $call['name'],
                'arguments' => $call['arguments'],
            ];
        }

        ksort($events);
        $toolCallBuffers = [];

        return array_values($events);
    }
}
