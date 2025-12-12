<?php

declare(strict_types=1);

namespace WPClaw\Session;

use wpdb;

/**
 * Database schema manager for session and message persistence tables.
 */
final class Schema
{
    private wpdb $wpdb;

    public function __construct(wpdb $wpdb)
    {
        $this->wpdb = $wpdb;
    }

    public function create_tables(): void
    {
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        dbDelta($this->sessions_table_sql());
        dbDelta($this->messages_table_sql());
    }

    public function sessions_table_name(): string
    {
        return $this->wpdb->prefix . 'wpclaw_sessions';
    }

    public function messages_table_name(): string
    {
        return $this->wpdb->prefix . 'wpclaw_messages';
    }

    private function sessions_table_sql(): string
    {
        $charset_collate = $this->wpdb->get_charset_collate();
        $table = $this->sessions_table_name();

        return "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            message_count int unsigned NOT NULL DEFAULT 0,
            PRIMARY KEY  (id),
            UNIQUE KEY user_id_unique (user_id)
        ) {$charset_collate};";
    }

    private function messages_table_sql(): string
    {
        $charset_collate = $this->wpdb->get_charset_collate();
        $table = $this->messages_table_name();

        return "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            session_id bigint(20) unsigned NOT NULL,
            role varchar(20) NOT NULL,
            content longtext NOT NULL,
            tool_calls longtext NULL,
            tool_call_id varchar(100) NULL,
            tool_name varchar(100) NULL,
            token_estimate int unsigned NOT NULL DEFAULT 0,
            created_at datetime NOT NULL,
            iteration_log longtext NULL,
            PRIMARY KEY  (id),
            KEY session_created (session_id, created_at),
            KEY session_id (session_id)
        ) {$charset_collate};";
    }
}
