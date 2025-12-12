<?php

declare(strict_types=1);

namespace WPClaw;

use WPClaw\Session\Schema;
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

        add_option('wpclaw_delete_data_on_uninstall', false);
        add_option('wpclaw_allowed_chat_roles', ['administrator']);
        add_option('wpclaw_allowed_tool_roles', ['administrator']);
        add_option('wpclaw_system_prompt', '');
        add_option('wpclaw_enabled_tools', []);
    }
}
