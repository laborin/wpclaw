<?php

declare(strict_types=1);

namespace WPClaw\Agent;

/**
 * Builds the system prompt from global and block settings.
 */
final class SystemPromptBuilder
{
    public function build(string $globalPrompt, string $blockPrompt, string $mode = 'override'): string
    {
        $globalPrompt = trim($globalPrompt);
        $blockPrompt = trim($blockPrompt);

        if ($blockPrompt === '') {
            return $globalPrompt;
        }

        if ($mode === 'append' && $globalPrompt !== '') {
            return $globalPrompt . "\n\n" . $blockPrompt;
        }

        return $blockPrompt;
    }
}
