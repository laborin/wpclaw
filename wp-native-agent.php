<?php
/**
 * Plugin Name: WP Native Agent
 * Description: Native AI agent plugin with Gutenberg blocks and server loop.
 * Version: 0.1.0
 * Author: Emmanuel Laborin
 * License: MIT
 * Text Domain: wp-native-agent
 * Requires at least: 6.5
 * Requires PHP: 8.1
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

$autoload = __DIR__ . '/vendor/autoload.php';
if (is_readable($autoload)) {
    require_once $autoload;
} else {
    spl_autoload_register(
        static function (string $class): void {
            $prefix = 'WPNativeAgent\\';
            if (! str_starts_with($class, $prefix)) {
                return;
            }

            $relative = substr($class, strlen($prefix));
            $path = __DIR__ . '/includes/' . str_replace('\\', '/', $relative) . '.php';
            if (is_readable($path)) {
                require_once $path;
            }
        }
    );
}

use WPNativeAgent\Activator;
use WPNativeAgent\Deactivator;
use WPNativeAgent\Plugin;

register_activation_hook(__FILE__, [Activator::class, 'activate']);
register_deactivation_hook(__FILE__, [Deactivator::class, 'deactivate']);

$plugin = new Plugin();
$plugin->register_hooks();
