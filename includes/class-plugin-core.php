<?php
if (! defined('ABSPATH')) {
    exit;
}

class BRW_Plugin_Core
{
    private static $instance = null;
    const DB_VERSION = '1.0.0';

    public static function instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        $this->define_constants();
        $this->init_hooks();
    }

    private function define_constants()
    {
        if (! defined('BRW_MIN_CAP')) {
            define('BRW_MIN_CAP', 'manage_woocommerce');
        }
    }

    public static function activate()
    {
        self::create_tables();
        // Ensure our custom schedule exists at activation time
        add_filter('cron_schedules', function ($schedules) {
            $schedules['brw_five_minutes'] = ['interval' => 5 * 60, 'display' => __('Every Five Minutes', 'bulk-review-wizard')];
            return $schedules;
        });
        if (! wp_next_scheduled('brw_process_jobs_event')) {
            wp_schedule_event(time() + 60, 'brw_five_minutes', 'brw_process_jobs_event');
        }
    }

    public static function deactivate()
    {
        // Clear scheduled event
        $timestamp = wp_next_scheduled('brw_process_jobs_event');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'brw_process_jobs_event');
        }
    }

    private static function create_tables()
    {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset_collate = $wpdb->get_charset_collate();

        $jobs = $wpdb->prefix . 'bulk_reviews_jobs';
        $authors = $wpdb->prefix . 'bulk_reviews_authors';
        $templates = $wpdb->prefix . 'bulk_reviews_templates';

        $sql_jobs = "CREATE TABLE {$jobs} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			job_name VARCHAR(255) NULL,
			status ENUM('pending','running','completed','failed') NOT NULL DEFAULT 'pending',
			settings LONGTEXT NULL,
			created_at DATETIME NULL,
			completed_at DATETIME NULL,
			results LONGTEXT NULL,
			PRIMARY KEY  (id)
		) {$charset_collate};";

        $sql_authors = "CREATE TABLE {$authors} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			display_name VARCHAR(100) NOT NULL,
			email VARCHAR(100) NOT NULL,
			country_code VARCHAR(2) NULL,
			gender ENUM('male','female','neutral') NULL,
			age_group VARCHAR(20) NULL,
			avatar_url VARCHAR(255) NULL,
			created_at DATETIME NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY email (email)
		) {$charset_collate};";

        $sql_templates = "CREATE TABLE {$templates} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			name VARCHAR(100) NOT NULL,
			category VARCHAR(50) NULL,
			language VARCHAR(10) NULL,
			template_content LONGTEXT NULL,
			variables LONGTEXT NULL,
			is_active TINYINT(1) NOT NULL DEFAULT 1,
			PRIMARY KEY  (id)
		) {$charset_collate};";

        dbDelta($sql_jobs);
        dbDelta($sql_authors);
        dbDelta($sql_templates);

        update_option('brw_db_version', self::DB_VERSION);
    }

    private function init_hooks()
    {
        // Load text domain
        add_action('init', [$this, 'load_textdomain']);

        // Custom cron schedule
        add_filter('cron_schedules', function ($schedules) {
            $schedules['brw_five_minutes'] = ['interval' => 5 * 60, 'display' => __('Every Five Minutes', 'bulk-review-wizard')];
            return $schedules;
        });

        // Admin (single top-level page)
        if (is_admin()) {
            BRW_Admin_Interface::instance();
            BRW_Settings::instance();
            BRW_Jobs_Admin::instance();
        }

        // Assets
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);

        // AJAX endpoints (secured by capability + nonce within handlers)
        add_action('wp_ajax_brw_search_products', ['BRW_Admin_Interface', 'ajax_search_products']);
        add_action('wp_ajax_brw_preview_generation', ['BRW_Admin_Interface', 'ajax_preview_generation']);
        add_action('wp_ajax_brw_start_generation', ['BRW_Review_Generator', 'ajax_start_generation']);
        add_action('wp_ajax_brw_generation_progress', ['BRW_Review_Generator', 'ajax_generation_progress']);
        add_action('wp_ajax_brw_get_settings', ['BRW_Settings', 'ajax_get_settings']);
        add_action('wp_ajax_brw_save_settings', ['BRW_Settings', 'ajax_save_settings']);
        add_action('wp_ajax_brw_list_jobs', ['BRW_Jobs_Admin', 'ajax_list_jobs']);

        // Cron processing
        add_action('brw_process_jobs_event', ['BRW_Review_Generator', 'process_pending_jobs']);
    }

    public function load_textdomain()
    {
        load_plugin_textdomain('bulk-review-wizard', false, dirname(plugin_basename(BRW_PLUGIN_FILE)) . '/languages');
    }

    public function enqueue_admin_assets($hook)
    {
        // Our top-level page hook contains 'toplevel_page_brw-bulk-reviews'
        if (false === strpos($hook, 'brw-bulk-reviews')) {
            return;
        }
        wp_enqueue_style('brw-admin', BRW_PLUGIN_URL . 'assets/css/admin-styles.css', [], BRW_VERSION);
        wp_enqueue_script('brw-admin', BRW_PLUGIN_URL . 'assets/js/admin-script.js', ['jquery', 'wp-util'], BRW_VERSION, true);
        wp_localize_script('brw-admin', 'BRW', [
            'ajax' => ['url' => admin_url('admin-ajax.php')],
            'nonce' => wp_create_nonce('brw_nonce'),
            'i18n' => ['processing' => __('Processing…', 'bulk-review-wizard')],
        ]);
    }
}
