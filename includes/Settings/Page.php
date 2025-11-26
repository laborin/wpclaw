<?php

declare(strict_types=1);

namespace WPNativeAgent\Settings;

use WPNativeAgent\Session\MessageRepository;
use WPNativeAgent\Session\SessionRepository;

/**
 * Admin settings page and tab renderer.
 */
final class Page
{
    public function __construct(
        private readonly Fields $fields
    ) {
    }

    public function register_hooks(): void
    {
        add_action('admin_menu', [$this, 'register_page']);
        add_action('admin_init', [$this->fields, 'register']);
        add_action('admin_post_wpna_clear_all_history', [$this, 'handle_clear_all_history']);
    }

    public function register_page(): void
    {
        add_options_page(
            'WP Native Agent',
            'WP Native Agent',
            'manage_options',
            'wp-native-agent',
            [$this, 'render_page']
        );
    }

    public function render_page(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        $tab = isset($_GET['tab']) ? sanitize_key((string) $_GET['tab']) : 'provider';
        $tabs = [
            'provider' => 'Provider',
            'access' => 'Access',
            'tools' => 'Tools',
            'rate-limits' => 'Rate Limits',
            'data' => 'Data',
        ];

        $groups = [
            'provider' => 'wpna_provider',
            'access' => 'wpna_access',
            'tools' => 'wpna_tools',
            'rate-limits' => 'wpna_rate_limits',
            'data' => 'wpna_data',
        ];

        if (! isset($tabs[$tab])) {
            $tab = 'provider';
        }

        echo '<div class="wrap">';
        echo '<h1>WP Native Agent</h1>';
        echo '<nav class="nav-tab-wrapper">';

        foreach ($tabs as $slug => $label) {
            $class = $slug === $tab ? ' nav-tab-active' : '';
            $url = admin_url('options-general.php?page=wp-native-agent&tab=' . $slug);
            printf('<a href="%1$s" class="nav-tab%2$s">%3$s</a>', esc_url($url), esc_attr($class), esc_html($label));
        }

        echo '</nav>';

        if ($tab === 'data') {
            $this->render_data_actions();
        }

        echo '<form method="post" action="options.php" style="margin-top:16px;">';
        settings_fields($groups[$tab]);
        do_settings_sections($groups[$tab]);
        submit_button('Save Settings');
        echo '</form>';
        echo '</div>';
    }

    public function handle_clear_all_history(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die('Not allowed.');
        }

        check_admin_referer('wpna_clear_all_history');

        global $wpdb;
        $sessionRepository = new SessionRepository($wpdb);
        $messageRepository = new MessageRepository($wpdb);

        $deletedMessages = $messageRepository->delete_all();

        $sessionsTable = $wpdb->prefix . 'wpna_sessions';
        $deletedSessions = (int) $wpdb->query("DELETE FROM {$sessionsTable}");

        $redirectUrl = add_query_arg(
            [
                'page' => 'wp-native-agent',
                'tab' => 'data',
                'wpna_cleared' => 1,
                'wpna_deleted_messages' => $deletedMessages,
                'wpna_deleted_sessions' => $deletedSessions,
            ],
            admin_url('options-general.php')
        );

        wp_safe_redirect($redirectUrl);
        exit;
    }

    private function render_data_actions(): void
    {
        if (isset($_GET['wpna_cleared']) && (int) $_GET['wpna_cleared'] === 1) {
            $deletedMessages = (int) ($_GET['wpna_deleted_messages'] ?? 0);
            $deletedSessions = (int) ($_GET['wpna_deleted_sessions'] ?? 0);

            printf(
                '<div class="notice notice-success"><p>Cleared %1$d messages and %2$d sessions.</p></div>',
                $deletedMessages,
                $deletedSessions
            );
        }

        $actionUrl = admin_url('admin-post.php');

        echo '<form method="post" action="' . esc_url($actionUrl) . '" style="margin-top:16px;">';
        echo '<input type="hidden" name="action" value="wpna_clear_all_history"/>';
        wp_nonce_field('wpna_clear_all_history');
        submit_button('Clear All Chat History', 'delete');
        echo '</form>';
    }
}
