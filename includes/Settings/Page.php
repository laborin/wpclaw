<?php

declare(strict_types=1);

namespace WPClaw\Settings;

use WPClaw\Session\MessageRepository;
use WPClaw\Session\SessionRepository;

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
        add_action('admin_post_wpclaw_clear_all_history', [$this, 'handle_clear_all_history']);
    }

    public function register_page(): void
    {
        add_options_page(
            'WPClaw',
            'WPClaw',
            'manage_options',
            'wpclaw',
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
            'provider' => 'wpclaw_provider',
            'access' => 'wpclaw_access',
            'tools' => 'wpclaw_tools',
            'rate-limits' => 'wpclaw_rate_limits',
            'data' => 'wpclaw_data',
        ];

        if (! isset($tabs[$tab])) {
            $tab = 'provider';
        }

        echo '<div class="wrap">';
        echo '<h1>WPClaw</h1>';
        echo '<nav class="nav-tab-wrapper">';

        foreach ($tabs as $slug => $label) {
            $class = $slug === $tab ? ' nav-tab-active' : '';
            $url = admin_url('options-general.php?page=wpclaw&tab=' . $slug);
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

        check_admin_referer('wpclaw_clear_all_history');

        global $wpdb;
        $sessionRepository = new SessionRepository($wpdb);
        $messageRepository = new MessageRepository($wpdb);

        $deletedMessages = $messageRepository->delete_all();

        $sessionsTable = $wpdb->prefix . 'wpclaw_sessions';
        $deletedSessions = (int) $wpdb->query("DELETE FROM {$sessionsTable}");

        $redirectUrl = add_query_arg(
            [
                'page' => 'wpclaw',
                'tab' => 'data',
                'wpclaw_cleared' => 1,
                'wpclaw_deleted_messages' => $deletedMessages,
                'wpclaw_deleted_sessions' => $deletedSessions,
            ],
            admin_url('options-general.php')
        );

        wp_safe_redirect($redirectUrl);
        exit;
    }

    private function render_data_actions(): void
    {
        if (isset($_GET['wpclaw_cleared']) && (int) $_GET['wpclaw_cleared'] === 1) {
            $deletedMessages = (int) ($_GET['wpclaw_deleted_messages'] ?? 0);
            $deletedSessions = (int) ($_GET['wpclaw_deleted_sessions'] ?? 0);

            printf(
                '<div class="notice notice-success"><p>Cleared %1$d messages and %2$d sessions.</p></div>',
                $deletedMessages,
                $deletedSessions
            );
        }

        $actionUrl = admin_url('admin-post.php');

        echo '<form method="post" action="' . esc_url($actionUrl) . '" style="margin-top:16px;">';
        echo '<input type="hidden" name="action" value="wpclaw_clear_all_history"/>';
        wp_nonce_field('wpclaw_clear_all_history');
        submit_button('Clear All Chat History', 'delete');
        echo '</form>';
    }
}
