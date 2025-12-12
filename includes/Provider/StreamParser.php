<?php

declare(strict_types=1);

namespace WPClaw\Provider;

/**
 * Incremental SSE parser that normalizes OpenRouter stream payloads.
 */
final class StreamParser
{
    private string $buffer = '';

    /**
     * @return array<int, array<string, mixed>>
     */
    public function push(string $chunk): array
    {
        $this->buffer .= $chunk;
        $events = [];

        while (true) {
            $delimiterPos = strpos($this->buffer, "\n\n");
            if ($delimiterPos === false) {
                break;
            }

            $frame = substr($this->buffer, 0, $delimiterPos);
            $this->buffer = substr($this->buffer, $delimiterPos + 2);

            foreach ($this->parse_frame($frame) as $event) {
                $events[] = $event;
            }
        }

        return $events;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function flush(): array
    {
        if ($this->buffer === '') {
            return [];
        }

        $frame = $this->buffer;
        $this->buffer = '';

        return $this->parse_frame($frame);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function parse_frame(string $frame): array
    {
        $lines = preg_split('/\r?\n/', trim($frame));
        if (! is_array($lines)) {
            return [];
        }

        $dataLines = [];
        foreach ($lines as $line) {
            if (! str_starts_with($line, 'data:')) {
                continue;
            }

            $dataLines[] = trim(substr($line, 5));
        }

        if ($dataLines === []) {
            return [];
        }

        $data = implode("\n", $dataLines);

        if ($data === '[DONE]') {
            return [['type' => 'done']];
        }

        try {
            $decoded = json_decode($data, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [[
                'type' => 'parse_error',
                'raw' => $data,
            ]];
        }

        if (! is_array($decoded)) {
            return [];
        }

        if (isset($decoded['error']) && is_array($decoded['error'])) {
            return [[
                'type' => 'provider_error',
                'error' => $decoded['error'],
            ]];
        }

        $events = [];
        $choice = $decoded['choices'][0] ?? null;
        if (! is_array($choice)) {
            return $events;
        }

        $delta = $choice['delta'] ?? null;
        if (is_array($delta)) {
            if (isset($delta['content']) && is_string($delta['content']) && $delta['content'] !== '') {
                $events[] = [
                    'type' => 'assistant_delta',
                    'text' => $delta['content'],
                ];
            }

            if (isset($delta['tool_calls']) && is_array($delta['tool_calls'])) {
                foreach ($delta['tool_calls'] as $call) {
                    if (! is_array($call)) {
                        continue;
                    }

                    $function = $call['function'] ?? [];
                    if (! is_array($function)) {
                        $function = [];
                    }

                    $events[] = [
                        'type' => 'tool_call_delta',
                        'index' => (int) ($call['index'] ?? 0),
                        'id' => isset($call['id']) ? (string) $call['id'] : null,
                        'name' => isset($function['name']) ? (string) $function['name'] : null,
                        'arguments' => isset($function['arguments']) ? (string) $function['arguments'] : null,
                    ];
                }
            }
        }

        if (array_key_exists('finish_reason', $choice) && $choice['finish_reason'] !== null) {
            $events[] = [
                'type' => 'finish_reason',
                'finish_reason' => (string) $choice['finish_reason'],
            ];
        }

        if (isset($decoded['usage']) && is_array($decoded['usage'])) {
            $events[] = [
                'type' => 'usage',
                'usage' => $decoded['usage'],
            ];
        }

        return $events;
    }
}
