<?php

/**
 * Plugin Name: Bulk Review Wizard
 * Plugin URI: https://example.com/bulk-review-wizard
 * Description: Generate realistic, localized WooCommerce product reviews in bulk via a guided wizard.
 * Version: 0.1.0
 * Author: Mohamed Aleya
 * Author URI: https://example.com
 * Text Domain: bulk-review-wizard
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * License: GPLv2 or later
 */

if (! defined('ABSPATH')) {
    exit;
}

// Define constants
if (! defined('BRW_VERSION')) {
    define('BRW_VERSION', '0.1.0');
}
if (! defined('BRW_PLUGIN_FILE')) {
    define('BRW_PLUGIN_FILE', __FILE__);
}
if (! defined('BRW_PLUGIN_DIR')) {
    define('BRW_PLUGIN_DIR', plugin_dir_path(__FILE__));
}
if (! defined('BRW_PLUGIN_URL')) {
    define('BRW_PLUGIN_URL', plugin_dir_url(__FILE__));
}

// Autoloader (kebab-case files in includes/)
spl_autoload_register(function ($class) {
    if (strpos($class, 'BRW_') !== 0) {
        return;
    }
    $short = str_replace('BRW_', '', $class);
    $file = 'class-' . strtolower(str_replace('_', '-', $short)) . '.php';
    $path = BRW_PLUGIN_DIR . 'includes/' . $file;
    if (file_exists($path)) {
        require_once $path;
    }
});

// Activation/Deactivation hooks
register_activation_hook(__FILE__, ['BRW_Plugin_Core', 'activate']);
register_deactivation_hook(__FILE__, ['BRW_Plugin_Core', 'deactivate']);

add_action('plugins_loaded', function () {
    // Ensure WooCommerce is active
    if (! class_exists('WooCommerce')) {
        add_action('admin_notices', function () {
            echo '<div class="notice notice-error"><p>' . esc_html__('Bulk Review Wizard requires WooCommerce to be installed and active.', 'bulk-review-wizard') . '</p></div>';
        });
        return;
    }

    // Initialize core
    BRW_Plugin_Core::instance();
});
