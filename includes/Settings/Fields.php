<?php

declare(strict_types=1);

namespace WPClaw\Settings;

use WPClaw\Tools\Registry;

/**
 * Settings API registration and render callbacks for admin settings page.
 */
final class Fields
{
    public function __construct(
        private readonly Options $options,
        private readonly Registry $toolRegistry
    ) {
    }

    public function register(): void
    {
        $this->register_provider_fields();
        $this->register_access_fields();
        $this->register_tools_fields();
        $this->register_rate_limit_fields();
        $this->register_data_fields();
    }

    private function register_provider_fields(): void
    {
        register_setting('wpclaw_provider', Options::OPTION_API_KEY, [
            'type' => 'string',
            'sanitize_callback' => [$this, 'sanitize_api_key'],
            'default' => '',
        ]);

        register_setting('wpclaw_provider', Options::OPTION_DEFAULT_MODEL, [
            'type' => 'string',
            'sanitize_callback' => static fn (string $value): string => trim($value),
            'default' => 'openai/gpt-4o-mini',
        ]);

        register_setting('wpclaw_provider', Options::OPTION_SYSTEM_PROMPT, [
            'type' => 'string',
            'sanitize_callback' => [$this, 'sanitize_system_prompt'],
            'default' => '',
        ]);

        register_setting('wpclaw_provider', Options::OPTION_CONTEXT_WINDOWS, [
            'type' => 'array',
            'sanitize_callback' => [$this, 'sanitize_context_windows'],
            'default' => [],
        ]);

        add_settings_section('wpclaw_provider_section', 'Provider', static function (): void {
            echo '<p>Provider connection settings.</p>';
        }, 'wpclaw_provider');

        add_settings_field(Options::OPTION_API_KEY, 'OpenRouter API Key', [$this, 'render_api_key_field'], 'wpclaw_provider', 'wpclaw_provider_section');
        add_settings_field(Options::OPTION_DEFAULT_MODEL, 'Default Model', [$this, 'render_default_model_field'], 'wpclaw_provider', 'wpclaw_provider_section');
        add_settings_field(Options::OPTION_SYSTEM_PROMPT, 'System Prompt', [$this, 'render_system_prompt_field'], 'wpclaw_provider', 'wpclaw_provider_section');
        add_settings_field(Options::OPTION_CONTEXT_WINDOWS, 'Context Window Overrides', [$this, 'render_context_windows_field'], 'wpclaw_provider', 'wpclaw_provider_section');
    }

    private function register_access_fields(): void
    {
        register_setting('wpclaw_access', Options::OPTION_ALLOWED_CHAT_ROLES, [
            'type' => 'array',
            'sanitize_callback' => [$this, 'sanitize_roles'],
            'default' => ['administrator'],
        ]);

        register_setting('wpclaw_access', Options::OPTION_ALLOWED_TOOL_ROLES, [
            'type' => 'array',
            'sanitize_callback' => [$this, 'sanitize_roles'],
            'default' => ['administrator'],
        ]);

        register_setting('wpclaw_access', Options::OPTION_MAX_ITERATIONS, [
            'type' => 'integer',
            'sanitize_callback' => [$this, 'sanitize_positive_int'],
            'default' => 8,
        ]);

        register_setting('wpclaw_access', Options::OPTION_MAX_RESPONSE_TOKENS, [
            'type' => 'integer',
            'sanitize_callback' => [$this, 'sanitize_positive_int'],
            'default' => 1024,
        ]);

        add_settings_section('wpclaw_access_section', 'Access', static function (): void {
            echo '<p>Role and loop limits settings.</p>';
        }, 'wpclaw_access');

        add_settings_field(Options::OPTION_ALLOWED_CHAT_ROLES, 'Roles Allowed to Chat', [$this, 'render_chat_roles_field'], 'wpclaw_access', 'wpclaw_access_section');
        add_settings_field(Options::OPTION_ALLOWED_TOOL_ROLES, 'Roles Allowed to Use Tools', [$this, 'render_tool_roles_field'], 'wpclaw_access', 'wpclaw_access_section');
        add_settings_field(Options::OPTION_MAX_ITERATIONS, 'Max Iterations Per Request', [$this, 'render_max_iterations_field'], 'wpclaw_access', 'wpclaw_access_section');
        add_settings_field(Options::OPTION_MAX_RESPONSE_TOKENS, 'Max Response Tokens', [$this, 'render_max_response_tokens_field'], 'wpclaw_access', 'wpclaw_access_section');
    }

    private function register_tools_fields(): void
    {
        register_setting('wpclaw_tools', Options::OPTION_ENABLED_TOOLS, [
            'type' => 'array',
            'sanitize_callback' => [$this, 'sanitize_enabled_tools'],
            'default' => [],
        ]);

        add_settings_section('wpclaw_tools_section', 'Tools', static function (): void {
            echo '<p>Enable or disable tools available to the agent.</p>';
        }, 'wpclaw_tools');
        add_settings_field(Options::OPTION_ENABLED_TOOLS, 'Enabled Tools', [$this, 'render_enabled_tools_field'], 'wpclaw_tools', 'wpclaw_tools_section');
    }

    private function register_rate_limit_fields(): void
    {
        register_setting('wpclaw_rate_limits', Options::OPTION_RATE_USER_MINUTE, [
            'type' => 'integer',
            'sanitize_callback' => [$this, 'sanitize_positive_int'],
            'default' => 20,
        ]);

        register_setting('wpclaw_rate_limits', Options::OPTION_RATE_USER_DAY, [
            'type' => 'integer',
            'sanitize_callback' => [$this, 'sanitize_positive_int'],
            'default' => 500,
        ]);

        register_setting('wpclaw_rate_limits', Options::OPTION_RATE_IP_MINUTE, [
            'type' => 'integer',
            'sanitize_callback' => [$this, 'sanitize_positive_int'],
            'default' => 30,
        ]);

        add_settings_section('wpclaw_rate_limits_section', 'Rate Limits', static function (): void {
            echo '<p>Request limits for users and fallback IP.</p>';
        }, 'wpclaw_rate_limits');
        add_settings_field(Options::OPTION_RATE_USER_MINUTE, 'Requests per User per Minute', [$this, 'render_rate_user_minute_field'], 'wpclaw_rate_limits', 'wpclaw_rate_limits_section');
        add_settings_field(Options::OPTION_RATE_USER_DAY, 'Requests per User per Day', [$this, 'render_rate_user_day_field'], 'wpclaw_rate_limits', 'wpclaw_rate_limits_section');
        add_settings_field(Options::OPTION_RATE_IP_MINUTE, 'Requests per IP per Minute', [$this, 'render_rate_ip_minute_field'], 'wpclaw_rate_limits', 'wpclaw_rate_limits_section');
    }

    private function register_data_fields(): void
    {
        register_setting('wpclaw_data', Options::OPTION_DELETE_ON_UNINSTALL, [
            'type' => 'boolean',
            'sanitize_callback' => static fn (mixed $value): bool => (bool) $value,
            'default' => false,
        ]);

        add_settings_section('wpclaw_data_section', 'Data', static function (): void {
            echo '<p>Data retention and destructive options.</p>';
        }, 'wpclaw_data');
        add_settings_field(Options::OPTION_DELETE_ON_UNINSTALL, 'Delete Data on Uninstall', [$this, 'render_delete_on_uninstall_field'], 'wpclaw_data', 'wpclaw_data_section');
    }

    public function render_api_key_field(): void
    {
        printf(
            '<input type="password" name="%1$s" value="%2$s" class="regular-text" autocomplete="off"/>',
            esc_attr(Options::OPTION_API_KEY),
            esc_attr($this->options->get_api_key())
        );
    }

    public function sanitize_api_key(mixed $value): string
    {
        if (! is_string($value)) {
            return '';
        }

        return $this->options->encode_api_key_for_storage($value);
    }

    public function sanitize_system_prompt(mixed $value): string
    {
        if (! is_string($value)) {
            return '';
        }

        if (function_exists('sanitize_textarea_field')) {
            return sanitize_textarea_field($value);
        }

        return trim(strip_tags($value));
    }

    public function render_default_model_field(): void
    {
        printf(
            '<input type="text" name="%1$s" value="%2$s" class="regular-text" placeholder="openai/gpt-4o-mini"/>',
            esc_attr(Options::OPTION_DEFAULT_MODEL),
            esc_attr($this->options->get_default_model())
        );
    }

    public function render_system_prompt_field(): void
    {
        printf(
            '<textarea name="%1$s" rows="6" cols="70" class="large-text code" placeholder="You are a helpful WordPress assistant.">%2$s</textarea>',
            esc_attr(Options::OPTION_SYSTEM_PROMPT),
            esc_textarea($this->options->get_system_prompt())
        );
    }

    public function render_context_windows_field(): void
    {
        $encoded = wp_json_encode($this->options->get_context_windows(), JSON_PRETTY_PRINT);
        printf(
            '<textarea name="%1$s" rows="5" cols="60" class="large-text code" placeholder="{\"openai/gpt-4o-mini\": 128000}">%2$s</textarea>',
            esc_attr(Options::OPTION_CONTEXT_WINDOWS),
            esc_textarea(is_string($encoded) ? $encoded : '{}')
        );
    }

    public function render_chat_roles_field(): void
    {
        $this->render_roles_multiselect(Options::OPTION_ALLOWED_CHAT_ROLES, $this->options->get_allowed_chat_roles());
    }

    public function render_tool_roles_field(): void
    {
        $this->render_roles_multiselect(Options::OPTION_ALLOWED_TOOL_ROLES, $this->options->get_allowed_tool_roles());
    }

    public function render_max_iterations_field(): void
    {
        printf('<input type="number" min="1" step="1" name="%1$s" value="%2$d"/>', esc_attr(Options::OPTION_MAX_ITERATIONS), $this->options->get_max_iterations());
    }

    public function render_max_response_tokens_field(): void
    {
        printf('<input type="number" min="1" step="1" name="%1$s" value="%2$d"/>', esc_attr(Options::OPTION_MAX_RESPONSE_TOKENS), $this->options->get_max_response_tokens());
    }

    public function render_enabled_tools_field(): void
    {
        $enabled = array_fill_keys($this->options->get_enabled_tools(), true);

        foreach ($this->toolRegistry->all() as $tool) {
            $checked = isset($enabled[$tool->get_name()]) ? 'checked' : '';
            printf(
                '<label style="display:block;margin-bottom:8px;"><input type="checkbox" name="%1$s[%2$s]" value="1" %3$s/> <strong>%2$s</strong> <span style="opacity:0.8;">(%4$s)</span><br/><span style="opacity:0.8;">%5$s</span></label>',
                esc_attr(Options::OPTION_ENABLED_TOOLS),
                esc_attr($tool->get_name()),
                $checked,
                esc_html($tool->get_required_capability()),
                esc_html($tool->get_description())
            );
        }
    }

    public function render_rate_user_minute_field(): void
    {
        printf('<input type="number" min="1" step="1" name="%1$s" value="%2$d"/>', esc_attr(Options::OPTION_RATE_USER_MINUTE), $this->options->get_rate_limit_user_minute());
    }

    public function render_rate_user_day_field(): void
    {
        printf('<input type="number" min="1" step="1" name="%1$s" value="%2$d"/>', esc_attr(Options::OPTION_RATE_USER_DAY), $this->options->get_rate_limit_user_day());
    }

    public function render_rate_ip_minute_field(): void
    {
        printf('<input type="number" min="1" step="1" name="%1$s" value="%2$d"/>', esc_attr(Options::OPTION_RATE_IP_MINUTE), $this->options->get_rate_limit_ip_minute());
    }

    public function render_delete_on_uninstall_field(): void
    {
        printf(
            '<label><input type="checkbox" name="%1$s" value="1" %2$s/> Delete tables and options when uninstalling plugin</label>',
            esc_attr(Options::OPTION_DELETE_ON_UNINSTALL),
            checked($this->options->delete_on_uninstall(), true, false)
        );
    }

    /**
     * @param mixed $value
     * @return array<int, string>
     */
    public function sanitize_roles(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $roles = [];
        foreach ($value as $role) {
            if (! is_string($role)) {
                continue;
            }

            $role = sanitize_text_field($role);
            if ($role !== '') {
                $roles[] = $role;
            }
        }

        return array_values(array_unique($roles));
    }

    /**
     * @param mixed $value
     */
    public function sanitize_positive_int(mixed $value): int
    {
        $intValue = (int) $value;

        return $intValue > 0 ? $intValue : 1;
    }

    /**
     * @param mixed $value
     * @return array<string, int>
     */
    public function sanitize_context_windows(mixed $value): array
    {
        if (is_string($value)) {
            try {
                $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
                $value = $decoded;
            } catch (\JsonException) {
                return [];
            }
        }

        if (! is_array($value)) {
            return [];
        }

        $windows = [];
        foreach ($value as $model => $window) {
            if (! is_string($model) || ! is_numeric($window)) {
                continue;
            }

            $window = (int) $window;
            if ($window > 0) {
                $windows[sanitize_text_field($model)] = $window;
            }
        }

        return $windows;
    }

    /**
     * @param mixed $value
     * @return array<string, int>
     */
    public function sanitize_enabled_tools(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $known = [];
        foreach ($this->toolRegistry->all() as $tool) {
            $known[$tool->get_name()] = true;
        }

        $enabled = [];
        foreach ($value as $toolName => $on) {
            if (! is_string($toolName) || ! isset($known[$toolName])) {
                continue;
            }

            if ((bool) $on) {
                $enabled[$toolName] = 1;
            }
        }

        return $enabled;
    }

    /**
     * @param array<int, string> $selected
     */
    private function render_roles_multiselect(string $optionName, array $selected): void
    {
        $rolesObject = wp_roles();
        $roles = is_object($rolesObject) && isset($rolesObject->roles) && is_array($rolesObject->roles)
            ? $rolesObject->roles
            : [];

        printf('<select name="%1$s[]" multiple size="6" style="min-width:260px;">', esc_attr($optionName));

        $selectedMap = array_fill_keys($selected, true);
        foreach ($roles as $roleName => $roleData) {
            if (! is_string($roleName) || ! is_array($roleData)) {
                continue;
            }

            $label = isset($roleData['name']) && is_string($roleData['name']) ? $roleData['name'] : $roleName;
            printf(
                '<option value="%1$s" %2$s>%3$s</option>',
                esc_attr($roleName),
                selected(isset($selectedMap[$roleName]), true, false),
                esc_html($label)
            );
        }

        print '</select>';
    }
}
