<?php
if (! defined('ABSPATH')) {
    exit;
}

class BRW_Template_Manager
{
    public static function get_template_for_product(int $product_id, string $language = 'en_US'): array
    {
        $category_slug = null;
        if (function_exists('wc_get_product')) {
            $terms = get_the_terms($product_id, 'product_cat') ?: [];
            if ($terms) {
                $category_slug = $terms[0]->slug;
            }
        }
        return self::get_template_by_category($category_slug ?: 'default', $language);
    }

    public static function get_template_by_category(string $category, string $language = 'en_US'): array
    {
        $key = 'brw_tpl_' . md5($category . '|' . $language);
        $cached = get_transient($key);
        if (is_array($cached)) {
            return $cached;
        }

        // DB first
        $db = self::get_db_template($category, $language);
        if ($db) {
            set_transient($key, $db, HOUR_IN_SECONDS);
            return $db;
        }

        // File fallback
        $base_dir = trailingslashit(BRW_PLUGIN_DIR . 'templates/review-templates');
        $path = $base_dir . sanitize_title($category) . '.json';
        if (! file_exists($path)) {
            $path = $base_dir . 'default.json';
        }
        $tpl = self::load_json($path);
        $tpl = array_merge(['openers' => [], 'features' => [], 'closers' => []], $tpl);
        set_transient($key, $tpl, HOUR_IN_SECONDS);
        return $tpl;
    }

    public static function list_db_templates(): array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'bulk_reviews_templates';
        return $wpdb->get_results("SELECT id, name, category, language, is_active FROM {$table} ORDER BY id DESC", ARRAY_A) ?: [];
    }

    public static function save_db_template(string $name, string $category, string $language, array $content, array $vars = []): int
    {
        global $wpdb;
        $table = $wpdb->prefix . 'bulk_reviews_templates';
        $wpdb->insert($table, [
            'name' => $name,
            'category' => $category,
            'language' => $language,
            'template_content' => wp_json_encode($content),
            'variables' => wp_json_encode($vars),
            'is_active' => 1,
        ]);
        return (int) $wpdb->insert_id;
    }

    private static function get_db_template(string $category, string $language): ?array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'bulk_reviews_templates';
        $row = $wpdb->get_row($wpdb->prepare("SELECT template_content FROM {$table} WHERE is_active=1 AND category=%s AND language=%s ORDER BY id DESC LIMIT 1", $category, $language), ARRAY_A);
        if ($row && ! empty($row['template_content'])) {
            $data = json_decode($row['template_content'], true);
            return is_array($data) ? $data : null;
        }
        return null;
    }

    private static function load_json(string $path): array
    {
        if (! file_exists($path)) {
            return [];
        }
        $raw = file_get_contents($path);
        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }
}
