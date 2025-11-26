<?php

declare(strict_types=1);

namespace WPNativeAgent\Settings;

use WPNativeAgent\Security\KeyVault;

/**
 * Typed option access object for plugin settings.
 */
final class Options
{
    public const OPTION_API_KEY = 'wp_native_agent_openrouter_api_key';
    public const OPTION_DEFAULT_MODEL = 'wp_native_agent_default_model';
    public const OPTION_SYSTEM_PROMPT = 'wp_native_agent_system_prompt';
    public const OPTION_ALLOWED_CHAT_ROLES = 'wp_native_agent_allowed_chat_roles';
    public const OPTION_ALLOWED_TOOL_ROLES = 'wp_native_agent_allowed_tool_roles';
    public const OPTION_MAX_ITERATIONS = 'wp_native_agent_max_iterations';
    public const OPTION_MAX_RESPONSE_TOKENS = 'wp_native_agent_max_response_tokens';
    public const OPTION_ENABLED_TOOLS = 'wp_native_agent_enabled_tools';
    public const OPTION_RATE_USER_MINUTE = 'wp_native_agent_rate_user_minute';
    public const OPTION_RATE_USER_DAY = 'wp_native_agent_rate_user_day';
    public const OPTION_RATE_IP_MINUTE = 'wp_native_agent_rate_ip_minute';
    public const OPTION_CONTEXT_WINDOWS = 'wp_native_agent_context_windows';
    public const OPTION_DELETE_ON_UNINSTALL = 'wp_native_agent_delete_data_on_uninstall';

    private KeyVault $keyVault;

    public function __construct(?KeyVault $keyVault = null)
    {
        $this->keyVault = $keyVault ?? new KeyVault();
    }

    public function get_api_key(): string
    {
        $stored = trim((string) get_option(self::OPTION_API_KEY, ''));
        if ($stored === '') {
            return '';
        }

        if ($this->looks_like_openrouter_key($stored)) {
            return $stored;
        }

        $decoded = $this->decrypt_nested($stored, 4);
        if ($decoded === '') {
            return '';
        }

        if ($this->looks_like_openrouter_key($decoded)) {
            return $decoded;
        }

        if (! $this->looks_like_encrypted_blob($decoded)) {
            return $decoded;
        }

        return '';
    }

    public function set_api_key(string $apiKey): void
    {
        update_option(self::OPTION_API_KEY, $this->encode_api_key_for_storage($apiKey));
    }

    public function encode_api_key_for_storage(string $apiKey): string
    {
        $apiKey = trim($apiKey);
        if ($apiKey === '') {
            return '';
        }

        return $this->keyVault->encrypt($apiKey);
    }

    private function decrypt_nested(string $value, int $maxDepth): string
    {
        $current = trim($value);
        for ($i = 0; $i < $maxDepth; $i++) {
            $next = $this->keyVault->decrypt($current);
            if ($next === '') {
                break;
            }

            $current = trim($next);
            if ($current === '') {
                break;
            }
        }

        return $current;
    }

    private function looks_like_openrouter_key(string $value): bool
    {
        return preg_match('/^(sk-or-v1-|or-v1-|sk-)[A-Za-z0-9._-]{16,}$/', $value) === 1;
    }

    private function looks_like_encrypted_blob(string $value): bool
    {
        return strlen($value) >= 80 && preg_match('/^[A-Za-z0-9+\/=]+$/', $value) === 1;
    }

    public function get_default_model(): string
    {
        return (string) get_option(self::OPTION_DEFAULT_MODEL, 'openai/gpt-4o-mini');
    }

    public function get_system_prompt(): string
    {
        return trim((string) get_option(self::OPTION_SYSTEM_PROMPT, ''));
    }

    /**
     * @return array<int, string>
     */
    public function get_allowed_chat_roles(): array
    {
        return $this->sanitize_roles(get_option(self::OPTION_ALLOWED_CHAT_ROLES, ['administrator']));
    }

    /**
     * @return array<int, string>
     */
    public function get_allowed_tool_roles(): array
    {
        return $this->sanitize_roles(get_option(self::OPTION_ALLOWED_TOOL_ROLES, ['administrator']));
    }

    public function get_max_iterations(): int
    {
        $value = (int) get_option(self::OPTION_MAX_ITERATIONS, 8);

        return $value > 0 ? $value : 8;
    }

    public function get_max_response_tokens(): int
    {
        $value = (int) get_option(self::OPTION_MAX_RESPONSE_TOKENS, 1024);

        return $value > 0 ? $value : 1024;
    }

    /**
     * @return array<int, string>
     */
    public function get_enabled_tools(): array
    {
        $raw = get_option(self::OPTION_ENABLED_TOOLS, []);
        if (! is_array($raw)) {
            return [];
        }

        $tools = [];
        foreach ($raw as $toolName => $enabled) {
            if (! is_string($toolName)) {
                continue;
            }

            if ((bool) $enabled) {
                $tools[] = $toolName;
            }
        }

        return array_values(array_unique($tools));
    }

    public function get_rate_limit_user_minute(): int
    {
        $value = (int) get_option(self::OPTION_RATE_USER_MINUTE, 20);

        return $value > 0 ? $value : 20;
    }

    public function get_rate_limit_user_day(): int
    {
        $value = (int) get_option(self::OPTION_RATE_USER_DAY, 500);

        return $value > 0 ? $value : 500;
    }

    public function get_rate_limit_ip_minute(): int
    {
        $value = (int) get_option(self::OPTION_RATE_IP_MINUTE, 30);

        return $value > 0 ? $value : 30;
    }

    /**
     * @return array<string, int>
     */
    public function get_context_windows(): array
    {
        $raw = get_option(self::OPTION_CONTEXT_WINDOWS, []);
        if (! is_array($raw)) {
            return [];
        }

        $windows = [];
        foreach ($raw as $model => $value) {
            if (! is_string($model) || ! is_numeric($value)) {
                continue;
            }

            $intValue = (int) $value;
            if ($intValue > 0) {
                $windows[$model] = $intValue;
            }
        }

        return $windows;
    }

    public function delete_on_uninstall(): bool
    {
        return (bool) get_option(self::OPTION_DELETE_ON_UNINSTALL, false);
    }

    /**
     * @param mixed $raw
     * @return array<int, string>
     */
    private function sanitize_roles(mixed $raw): array
    {
        if (! is_array($raw)) {
            return [];
        }

        $roles = [];
        foreach ($raw as $role) {
            if (! is_string($role)) {
                continue;
            }

            $role = trim($role);
            if ($role !== '') {
                $roles[] = $role;
            }
        }

        return array_values(array_unique($roles));
    }
}
