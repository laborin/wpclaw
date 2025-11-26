<?php

declare(strict_types=1);

if (! defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

$delete_data = get_option('wp_native_agent_delete_data_on_uninstall', false);
if (! $delete_data) {
    return;
}

global $wpdb;
if (! $wpdb instanceof wpdb) {
    return;
}

$prefix = $wpdb->prefix . 'wpna_';
$wpdb->query("DROP TABLE IF EXISTS {$prefix}sessions");
$wpdb->query("DROP TABLE IF EXISTS {$prefix}messages");

$option_like = $wpdb->esc_like('wp_native_agent_') . '%';
$options_table = $wpdb->options;
$wpdb->query(
    $wpdb->prepare(
        "DELETE FROM {$options_table} WHERE option_name LIKE %s",
        $option_like
    )
);
