<?php

declare(strict_types=1);

namespace WPClaw\Agent;

/**
 * Iteration telemetry accumulator used by the loop.
 */
final class IterationLog
{
    /**
     * @var array<int, array<string, mixed>>
     */
    private array $entries = [];

    /**
     * @param array<string, mixed> $entry
     */
    public function add(array $entry): void
    {
        $this->entries[] = $entry;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function all(): array
    {
        return $this->entries;
    }
}
