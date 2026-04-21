<?php

declare(strict_types=1);

namespace WPClaw\Security;

/**
 * Input sanitizer for REST payload fields.
 */
final class InputSanitizer
{
    public function sanitize_message(mixed $message, int $maxLength = 4000): string
    {
        if (! is_string($message)) {
            return '';
        }

        $message = trim($message);
        $message = function_exists('wp_strip_all_tags') ? wp_strip_all_tags($message, true) : strip_tags($message);

        if (mb_strlen($message) > $maxLength) {
            $message = mb_substr($message, 0, $maxLength);
        }

        return $message;
    }

    /**
     * @return array<int, string>
     */
    public function sanitize_enabled_tools(mixed $tools): array
    {
        if (! is_array($tools)) {
            return [];
        }

        $sanitized = [];
        foreach ($tools as $toolName) {
            if (! is_string($toolName)) {
                continue;
            }

            $toolName = trim($toolName);
            if ($toolName === '') {
                continue;
            }

            $sanitized[] = preg_replace('/[^a-z0-9_]/i', '', $toolName);
        }

        return array_values(array_unique(array_filter($sanitized, static fn (mixed $name): bool => is_string($name) && $name !== '')));
    }

    public function sanitize_model(mixed $model): string
    {
        if (! is_string($model)) {
            return '';
        }

        return trim(preg_replace('/[^a-zA-Z0-9_\/.\-:]/', '', $model) ?? '');
    }

}
