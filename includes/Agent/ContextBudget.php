<?php

declare(strict_types=1);

namespace WPNativeAgent\Agent;

/**
 * Context window selector with response reservation and pair-safe truncation.
 */
final class ContextBudget
{
    public function __construct(private readonly int $responseReserveTokens = 1024)
    {
    }

    /**
     * @param array<int, array<string, mixed>> $messages
     * @return array<int, array<string, mixed>>
     */
    public function select_messages(array $messages, int $contextWindow, int $systemPromptTokens = 0): array
    {
        $available = $contextWindow - $systemPromptTokens - $this->responseReserveTokens;
        if ($available <= 0) {
            return [];
        }

        $groups = $this->build_atomic_groups($messages);

        $selected = [];
        $used = 0;

        for ($index = count($groups) - 1; $index >= 0; $index--) {
            $group = $groups[$index];
            $groupTokens = $this->sum_group_tokens($group);

            if ($groupTokens <= 0) {
                continue;
            }

            if ($used + $groupTokens > $available) {
                continue;
            }

            $selected[] = $group;
            $used += $groupTokens;
        }

        $selected = array_reverse($selected);
        $flattened = [];

        foreach ($selected as $group) {
            foreach ($group as $message) {
                $flattened[] = $message;
            }
        }

        return $flattened;
    }

    /**
     * @param array<int, array<string, mixed>> $messages
     * @return array<int, array<int, array<string, mixed>>>
     */
    private function build_atomic_groups(array $messages): array
    {
        $groups = [];
        $index = 0;
        $count = count($messages);

        while ($index < $count) {
            $message = $messages[$index];
            $role = (string) ($message['role'] ?? '');

            if ($role !== 'assistant' || ! isset($message['tool_calls']) || ! is_array($message['tool_calls']) || $message['tool_calls'] === []) {
                $groups[] = [$message];
                $index++;
                continue;
            }

            $callIds = [];
            foreach ($message['tool_calls'] as $call) {
                if (is_array($call) && isset($call['id']) && is_string($call['id']) && $call['id'] !== '') {
                    $callIds[$call['id']] = true;
                }
            }

            $group = [$message];
            $next = $index + 1;
            while ($next < $count) {
                $candidate = $messages[$next];
                $candidateRole = (string) ($candidate['role'] ?? '');
                $callId = isset($candidate['tool_call_id']) ? (string) $candidate['tool_call_id'] : '';

                if ($candidateRole === 'tool' && $callId !== '' && isset($callIds[$callId])) {
                    $group[] = $candidate;
                    $next++;
                    continue;
                }

                break;
            }

            $groups[] = $group;
            $index = $next;
        }

        return $groups;
    }

    /**
     * @param array<int, array<string, mixed>> $group
     */
    private function sum_group_tokens(array $group): int
    {
        $sum = 0;
        foreach ($group as $message) {
            $sum += max(0, (int) ($message['token_estimate'] ?? 0));
        }

        return $sum;
    }
}
