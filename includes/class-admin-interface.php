<?php
if (! defined('ABSPATH')) {
    exit;
}

class BRW_Admin_Interface
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
        add_action('admin_menu', [$this, 'register_menu']);
    }

    public function register_menu()
    {
        // Create top-level menu for the plugin, not under WooCommerce
        $cap = BRW_MIN_CAP;
        add_menu_page(
            __('Bulk Review Wizard', 'bulk-review-wizard'),
            __('Bulk Review Wizard', 'bulk-review-wizard'),
            $cap,
            'brw-bulk-reviews',
            [$this, 'render_page'],
            'dashicons-star-filled',
            56
        );
    }

    public function render_page()
    {
        if (! current_user_can(BRW_MIN_CAP)) {
            wp_die(esc_html__('You do not have permission to access this page.', 'bulk-review-wizard'));
        }
        include BRW_PLUGIN_DIR . 'templates/admin/main-form.php';
    }

    // AJAX: Product search (simplified: only text and category + exclude reviewed)
    public static function ajax_search_products()
    {
        check_ajax_referer('brw_nonce', 'nonce');
        if (! current_user_can(BRW_MIN_CAP)) {
            wp_send_json_error(['message' => 'unauthorized'], 403);
        }

        $args = ['limit' => 20, 'paginate' => false, 'status' => ['publish']];
        $search = isset($_GET['q']) ? sanitize_text_field(wp_unslash($_GET['q'])) : '';
        if ($search) {
            // Use multiple strategies to improve matching by title/sku
            $args['search'] = '*' . $search . '*'; // name contains
            $args['s'] = $search; // WP core search fallback
            $args['sku'] = $search; // exact SKU match if applicable
        }

        // Category filtering (slug expected by Woo); accept id and convert
        if (! empty($_GET['category'])) {
            $cats = (array) $_GET['category'];
            $slugs = [];
            foreach ($cats as $c) {
                $c = sanitize_text_field(wp_unslash($c));
                if (is_numeric($c)) {
                    $term = get_term_by('id', (int) $c, 'product_cat');
                    if ($term && ! is_wp_error($term)) {
                        $slugs[] = $term->slug;
                    }
                } else {
                    $slugs[] = $c;
                }
            }
            if ($slugs) {
                $args['category'] = $slugs;
            }
        }

        $exclude_reviewed = ! empty($_GET['exclude_reviewed']);

        $results = [];
        if (function_exists('wc_get_products')) {
            $products = wc_get_products($args);

            // Fallback: If no matches and a search term was provided, try title and partial SKU search via WP_Query
            if (empty($products) && $search) {
                $tax_query = [];
                if (! empty($args['category'])) {
                    $tax_query[] = [
                        'taxonomy' => 'product_cat',
                        'field' => 'slug',
                        'terms' => (array) $args['category'],
                    ];
                }

                $ids = [];
                // Title/content search
                $q1 = new WP_Query([
                    'post_type' => 'product',
                    's' => $search,
                    'posts_per_page' => 20,
                    'post_status' => 'publish',
                    'fields' => 'ids',
                    'tax_query' => $tax_query,
                ]);
                if ($q1->have_posts()) {
                    $ids = array_merge($ids, $q1->posts);
                }
                // Partial SKU search
                $q2 = new WP_Query([
                    'post_type' => 'product',
                    'posts_per_page' => 20,
                    'post_status' => 'publish',
                    'fields' => 'ids',
                    'tax_query' => $tax_query,
                    'meta_query' => [
                        [
                            'key' => '_sku',
                            'value' => $search,
                            'compare' => 'LIKE',
                        ],
                    ],
                ]);
                if ($q2->have_posts()) {
                    $ids = array_merge($ids, $q2->posts);
                }
                $ids = array_values(array_unique(array_map('absint', $ids)));
                if ($ids) {
                    $products = wc_get_products([
                        'include' => $ids,
                        'limit' => 20,
                        'status' => ['publish'],
                    ]);
                }
            }

            foreach ($products as $product) {
                if ($exclude_reviewed && $product->get_review_count() > 0) {
                    continue;
                }
                $image_id = method_exists($product, 'get_image_id') ? $product->get_image_id() : 0;
                $thumb = $image_id ? wp_get_attachment_image_url($image_id, 'thumbnail') : (function_exists('wc_placeholder_img_src') ? wc_placeholder_img_src('thumbnail') : '');
                $results[] = [
                    'id' => $product->get_id(),
                    'text' => $product->get_name(),
                    'sku' => $product->get_sku(),
                    'price' => $product->get_price(),
                    'reviews' => (int) $product->get_review_count(),
                    'thumb' => $thumb,
                ];
            }
        }
        wp_send_json_success(['items' => $results]);
    }

    public static function ajax_preview_generation()
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

        $preview = BRW_Review_Generator::generate_preview($settings);
        wp_send_json_success(['preview' => $preview]);
    }
}
