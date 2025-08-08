<?php
if (! defined('ABSPATH')) {
    exit;
}

class BRW_Jobs_Admin
{
    private static $instance = null;

    public static function instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        // No separate menu; provide AJAX for SPA
        add_action('wp_ajax_brw_export_job', [$this, 'ajax_export_job']);
        add_action('wp_ajax_brw_list_jobs', [$this, 'ajax_list_jobs']);
    }

    public function ajax_list_jobs()
    {
        check_ajax_referer('brw_nonce', 'nonce');
        if (! current_user_can(BRW_MIN_CAP)) {
            wp_send_json_error(['message' => 'unauthorized'], 403);
        }
        global $wpdb;
        $table = $wpdb->prefix . 'bulk_reviews_jobs';
        $jobs = $wpdb->get_results("SELECT id, job_name, status, created_at, completed_at FROM {$table} ORDER BY id DESC LIMIT 100", ARRAY_A);
        wp_send_json_success(['jobs' => $jobs ?: []]);
    }

    public function ajax_export_job()
    {
        if (! current_user_can(BRW_MIN_CAP)) {
            wp_die('403');
        }
        check_admin_referer('brw_export_job');
        $job_id = isset($_GET['job_id']) ? absint($_GET['job_id']) : 0;
        if (! $job_id) {
            wp_die('Missing job_id');
        }
        global $wpdb;
        $table = $wpdb->prefix . 'bulk_reviews_jobs';
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id=%d", $job_id), ARRAY_A);
        if (! $row) {
            wp_die('Not found');
        }
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="brw-job-' . (int) $job_id . '.json"');
        echo wp_json_encode($row);
        exit;
    }
}
