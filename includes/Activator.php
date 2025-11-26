<?php

declare(strict_types=1);

namespace WPNativeAgent;

use WPNativeAgent\Session\Schema;
use wpdb;

/**
 * Plugin activation entry point that creates database structure and defaults.
 */
final class Activator
{
    public static function activate(): void
    {
        global $wpdb;

        if (! $wpdb instanceof wpdb) {
            return;
        }

        $schema = new Schema($wpdb);
        $schema->create_tables();

        add_option('wp_native_agent_delete_data_on_uninstall', false);
        add_option('wp_native_agent_allowed_chat_roles', ['administrator']);
        add_option('wp_native_agent_allowed_tool_roles', ['administrator']);
        add_option('wp_native_agent_system_prompt', '');
        add_option('wp_native_agent_enabled_tools', []);
    }
}
