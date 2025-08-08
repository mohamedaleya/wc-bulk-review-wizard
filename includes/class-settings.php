<?php
if (! defined('ABSPATH')) {
    exit;
}

class BRW_Settings
{
    private static $instance = null;
    const OPTION = 'brw_settings';

    public static function instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        // Settings will be shown inside the SPA; no separate submenu
        add_action('admin_init', [$this, 'register']);
    }

    public function register()
    {
        register_setting(self::OPTION, self::OPTION, [$this, 'sanitize']);
    }

    public function sanitize($input)
    {
        $clean = [];
        // Provider selection: openai|anthropic|google|openrouter|custom
        $clean['provider'] = isset($input['provider']) ? sanitize_text_field($input['provider']) : 'openai';
        $clean['api_key'] = isset($input['api_key']) ? sanitize_text_field($input['api_key']) : '';
        $clean['model'] = isset($input['model']) ? sanitize_text_field($input['model']) : '';
        $clean['base_url'] = isset($input['base_url']) ? esc_url_raw($input['base_url']) : '';
        $clean['extra_headers'] = isset($input['extra_headers']) && is_array($input['extra_headers']) ? array_map('sanitize_text_field', $input['extra_headers']) : [];
        $clean['temperature'] = isset($input['temperature']) ? floatval($input['temperature']) : 0.7;
        $clean['max_tokens'] = isset($input['max_tokens']) ? absint($input['max_tokens']) : 150;
        $clean['rate_limit'] = isset($input['rate_limit']) ? absint($input['rate_limit']) : 60;
        return $clean;
    }

    // Used by SPA to fetch current settings
    public static function ajax_get_settings()
    {
        check_ajax_referer('brw_nonce', 'nonce');
        if (! current_user_can(BRW_MIN_CAP)) {
            wp_send_json_error(['message' => 'unauthorized'], 403);
        }
        $options = get_option(self::OPTION, []);
        wp_send_json_success(['settings' => $options]);
    }

    // Used by SPA to save settings
    public static function ajax_save_settings()
    {
        check_ajax_referer('brw_nonce', 'nonce');
        if (! current_user_can(BRW_MIN_CAP)) {
            wp_send_json_error(['message' => 'unauthorized'], 403);
        }
        $payload = isset($_POST['settings']) ? wp_unslash($_POST['settings']) : '';
        $settings = json_decode($payload, true);
        if (! is_array($settings)) {
            wp_send_json_error(['message' => 'invalid_settings']);
        }
        $clean = (new self())->sanitize($settings);
        update_option(self::OPTION, $clean);
        wp_send_json_success(['settings' => $clean]);
    }
}
