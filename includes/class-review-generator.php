<?php
if (! defined('ABSPATH')) {
    exit;
}

class BRW_Review_Generator
{
    public static function ajax_start_generation()
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

        $validated = BRW_Validator::validate_settings($settings);
        if (is_wp_error($validated)) {
            wp_send_json_error(['message' => $validated->get_error_message()], 400);
        }

        $job_id = self::queue_job($validated);
        // Kick off processing soon so users see progress without waiting for the 5-min cron
        if (! wp_next_scheduled('brw_process_jobs_event')) {
            wp_schedule_single_event(time() + 1, 'brw_process_jobs_event');
        }
        wp_send_json_success(['job_id' => $job_id]);
    }

    public static function ajax_generation_progress()
    {
        check_ajax_referer('brw_nonce', 'nonce');
        if (! current_user_can(BRW_MIN_CAP)) {
            wp_send_json_error(['message' => 'unauthorized'], 403);
        }
        $job_id = isset($_GET['job_id']) ? absint($_GET['job_id']) : 0;
        if (! $job_id) {
            wp_send_json_error(['message' => 'missing_job_id']);
        }
        $status = self::get_job_status($job_id);
        // Proactively process this job so progress moves while user is watching
        if (in_array($status['status'], ['pending', 'running'], true)) {
            self::process_job($status);
            // refresh status after processing a batch
            $status = self::get_job_status($job_id);
        }
        $percent = 0;
        $log = [];
        $results = [];
        $queue = [];
        $settings = [];
        if (! empty($status['results'])) {
            $results = json_decode($status['results'], true);
            $total = isset($results['total']) ? max(1, (int) $results['total']) : 1;
            $processed = (int) ($results['processed'] ?? 0);
            $percent = min(100, (int) floor(($processed / $total) * 100));
            $log = $results['log'] ?? [];
        }
        if (! empty($status['settings'])) {
            $settings = json_decode($status['settings'], true);
        }
        // Build a lightweight queue view (awaiting counts per product)
        if ($settings && isset($settings['products'])) {
            $products = $settings['products'];
            $per_product = (int) ($settings['reviews_per_product'] ?? 5);
            $idx = (int) ($results['current_product_index'] ?? 0);
            $doneForCurrent = (int) ($results['created_per_product'] ?? 0);
            foreach ($products as $i => $pid) {
                $name = function_exists('wc_get_product') ? (($p = wc_get_product($pid)) ? $p->get_name() : (string)$pid) : (string)$pid;
                if ($i < $idx) {
                    $queue[] = ['product_id' => (int) $pid, 'product' => $name, 'completed' => $per_product, 'remaining' => 0];
                } elseif ($i === $idx) {
                    $remaining = max(0, $per_product - $doneForCurrent);
                    $queue[] = ['product_id' => (int) $pid, 'product' => $name, 'completed' => $doneForCurrent, 'remaining' => $remaining];
                } else {
                    $queue[] = ['product_id' => (int) $pid, 'product' => $name, 'completed' => 0, 'remaining' => $per_product];
                }
            }
        }
        $status['percent'] = $percent;
        $status['log'] = $log;
        $status['results'] = $results; // return decoded results
        $status['queue'] = $queue;
        wp_send_json_success($status);
    }

    public static function queue_job(array $settings)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'bulk_reviews_jobs';
        $products = array_values(array_map('absint', $settings['products'] ?? []));
        $per_product = isset($settings['reviews_per_product']) ? max(1, absint($settings['reviews_per_product'])) : 5;
        $total = count($products) * $per_product;
        $results = [
            'total' => $total,
            'processed' => 0,
            'current_product_index' => 0,
            'created_per_product' => 0,
            'errors' => [],
        ];
        $wpdb->insert($table, [
            'job_name' => isset($settings['job_name']) ? sanitize_text_field($settings['job_name']) : 'Bulk Review Job',
            'status' => 'pending',
            'settings' => wp_json_encode([
                'products' => $products,
                'reviews_per_product' => $per_product,
                'rating_distribution' => $settings['rating_distribution'] ?? [],
                'date_range' => $settings['date_range'] ?? [],
                'author_settings' => $settings['author_settings'] ?? [],
                'language' => $settings['language'] ?? 'en_US',
                'manual_reviews' => $settings['manual_reviews'] ?? '',
                'length' => $settings['length'] ?? 'medium',
            ]),
            'created_at' => current_time('mysql'),
            'results' => wp_json_encode($results),
        ]);
        return (int) $wpdb->insert_id;
    }

    public static function get_job_status(int $job_id)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'bulk_reviews_jobs';
        $row = $wpdb->get_row($wpdb->prepare("SELECT id, status, settings, results FROM {$table} WHERE id = %d", $job_id), ARRAY_A);
        return $row ? $row : ['id' => $job_id, 'status' => 'pending'];
    }

    public static function process_pending_jobs()
    {
        global $wpdb;
        $table = $wpdb->prefix . 'bulk_reviews_jobs';
        $jobs = $wpdb->get_results("SELECT * FROM {$table} WHERE status IN ('pending','running') ORDER BY id ASC LIMIT 1", ARRAY_A);
        foreach ($jobs as $job) {
            self::process_job($job);
        }
    }

    private static function process_job(array $job)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'bulk_reviews_jobs';
        if ($job['status'] !== 'running') {
            $wpdb->update($table, ['status' => 'running'], ['id' => (int) $job['id']]);
        }

        $settings = json_decode($job['settings'], true);
        $settings['_job_id'] = (int) $job['id'];
        $results = json_decode($job['results'], true);
        if (! is_array($results)) {
            $results = ['total' => 0, 'processed' => 0, 'current_product_index' => 0, 'created_per_product' => 0, 'errors' => []];
        }


        $batch_size = 30; // reviews per cron run
        $created_this_run = 0;
        $products = $settings['products'] ?? [];
        $per_product = (int) ($settings['reviews_per_product'] ?? 5);
        if (!isset($results['log']) || !is_array($results['log'])) {
            $results['log'] = [];
        }

        while ($created_this_run < $batch_size && $results['processed'] < $results['total']) {
            $idx = (int) $results['current_product_index'];
            if (! isset($products[$idx])) {
                break;
            }
            $product_id = (int) $products[$idx];
            if ($results['created_per_product'] >= $per_product) {
                $results['current_product_index']++;
                $results['created_per_product'] = 0;
                continue;
            }

            $res = self::create_single_review($product_id, $settings);
            $product_name = function_exists('wc_get_product') ? (($p = wc_get_product($product_id)) ? $p->get_name() : (string)$product_id) : (string)$product_id;
            if (is_wp_error($res)) {
                $results['errors'][] = $res->get_error_message();
                $results['log'][] = [
                    'product_id' => $product_id,
                    'product' => $product_name,
                    'status' => 'error',
                    'message' => $res->get_error_message(),
                ];
            } else {
                $results['processed']++;
                $results['created_per_product']++;
                $created_this_run++;
                $results['log'][] = [
                    'product_id' => $product_id,
                    'product' => $product_name,
                    'status' => 'added',
                ];
            }
        }

        $status = ($results['processed'] >= $results['total']) ? 'completed' : 'running';
        $wpdb->update($table, [
            'status' => $status,
            'results' => wp_json_encode($results),
            'completed_at' => ('completed' === $status ? current_time('mysql') : null),
        ], ['id' => (int) $job['id']]);

        if ('completed' === $status) {
            BRW_Analytics::log_event('job_completed', ['job_id' => (int) $job['id'], 'created' => (int) $results['processed']]);
        }
    }

    public static function generate_preview(array $settings)
    {
        $products = array_values(array_map('absint', $settings['products'] ?? []));
        $sample_items = [];
        for ($i = 0; $i < min(3, count($products)); $i++) {
            $product_id = $products[$i];
            $rating = self::pick_rating($settings['rating_distribution'] ?? []);
            $author = BRW_Author_Manager::random_author($settings['author_settings'] ?? []);
            $content = BRW_Content_Generator::generate($product_id, $rating, $settings);
            $sample_items[] = [
                'product_id' => $product_id,
                'rating' => $rating,
                'author' => $author['display_name'] ?? 'Anonymous',
                'content' => $content,
                'date' => self::pick_date_in_range($settings['date_range'] ?? []),
            ];
        }
        return ['items' => $sample_items];
    }

    private static function create_single_review(int $product_id, array $settings)
    {
        $rating = self::pick_rating($settings['rating_distribution'] ?? []);
        $author = BRW_Author_Manager::random_author($settings['author_settings'] ?? [], (int) ($settings['_job_id'] ?? 0));
        $content = BRW_Content_Generator::generate($product_id, $rating, $settings);

        // Duplicate detection via hash
        $hash = md5(wp_strip_all_tags($content));
        $dupe = self::find_duplicate_review($product_id, $hash);
        if ($dupe) {
            // Try once more with different rating pick to vary
            $rating = self::pick_rating($settings['rating_distribution'] ?? []);
            $content = BRW_Content_Generator::generate($product_id, $rating, $settings);
            $hash = md5(wp_strip_all_tags($content));
            if (self::find_duplicate_review($product_id, $hash)) {
                return new WP_Error('duplicate', 'Duplicate content detected');
            }
        }

        $commentdata = [
            'comment_post_ID' => $product_id,
            'comment_content' => $content,
            'comment_author' => $author['display_name'] ?? 'Anonymous',
            'comment_author_email' => $author['email'] ?? wp_generate_password(8, false) . '@example.com',
            'comment_type' => 'review',
            'comment_approved' => 1,
            'comment_date' => self::pick_date_in_range($settings['date_range'] ?? []),
        ];

        $comment_id = wp_insert_comment(wp_slash($commentdata));
        if (! $comment_id) {
            return new WP_Error('insert_failed', 'Failed to insert review');
        }
        update_comment_meta($comment_id, 'rating', $rating);
        update_comment_meta($comment_id, 'brw_hash', $hash);
        $verified_pct = (int) ($settings['author_settings']['verified_purchases'] ?? 0);
        if ($verified_pct > 0 && rand(1, 100) <= $verified_pct) {
            update_comment_meta($comment_id, 'verified', 1);
        }
        return $comment_id;
    }

    private static function find_duplicate_review(int $product_id, string $hash): bool
    {
        global $wpdb;
        return (bool) $wpdb->get_var($wpdb->prepare(
            "SELECT cm.comment_id FROM {$wpdb->commentmeta} cm INNER JOIN {$wpdb->comments} c ON c.comment_ID=cm.comment_id WHERE c.comment_post_ID=%d AND cm.meta_key='brw_hash' AND cm.meta_value=%s LIMIT 1",
            $product_id,
            $hash
        ));
    }

    private static function pick_rating(array $distribution)
    {
        $weights = [];
        for ($i = 1; $i <= 5; $i++) {
            $weights[$i] = (int) ($distribution[(string)$i] ?? 0);
        }
        if (array_sum($weights) === 0) {
            $weights = [1 => 5, 2 => 10, 3 => 15, 4 => 30, 5 => 40];
        }
        $rand = rand(1, array_sum($weights));
        $cum = 0;
        foreach ($weights as $rating => $w) {
            $cum += $w;
            if ($rand <= $cum) return (int) $rating;
        }
        return 5;
    }

    private static function pick_date_in_range(array $range)
    {
        $start = isset($range['start']) ? strtotime($range['start']) : strtotime('-90 days');
        $end   = isset($range['end']) ? strtotime($range['end']) : time();
        if ($start > $end) {
            list($start, $end) = [$end, $start];
        }
        $bias_recent = true;
        $ts = self::biased_rand($start, $end, $bias_recent ? 2.0 : 1.0);
        return gmdate('Y-m-d H:i:s', $ts);
    }

    private static function biased_rand(int $start, int $end, float $bias = 1.0): int
    {
        // bias>1 favors recent dates
        $r = pow(mt_rand() / mt_getrandmax(), 1 / $bias);
        return (int) floor($start + $r * ($end - $start));
    }
}
