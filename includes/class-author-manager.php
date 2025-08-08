<?php
if (! defined('ABSPATH')) {
    exit;
}

class BRW_Author_Manager
{
    public static function random_author(array $settings = [], int $job_id = 0): array
    {
        // If manual authors are provided (one per line), pick from them
        if (! empty($settings['manual_authors'])) {
            $list = array_filter(array_map('trim', preg_split('/\r?\n/', (string) $settings['manual_authors'])));
            if (! empty($list)) {
                $name = $list[array_rand($list)];
                $email = sanitize_title($name) . '+' . substr(md5($job_id . $name), 0, 6) . '@example.com';
                $avatar = 'https://www.gravatar.com/avatar/' . md5(strtolower(trim($email))) . '?d=identicon';
                self::ensure_author_record($name, $email, null, $avatar);
                return [
                    'display_name' => $name,
                    'email' => $email,
                    'country_code' => null,
                    'avatar_url' => $avatar,
                ];
            }
        }

        $country = self::pick_country($settings['countries'] ?? []);
        // Try to reuse/create a virtual author for the job to keep consistency
        $base_name = self::random_name($country);
        $name = $base_name . ' ' . substr(md5($job_id . wp_generate_password(4, false)), 0, 3);
        $email = sanitize_title($base_name) . '+' . substr(md5($job_id . $name), 0, 6) . '@example.com';
        $avatar = 'https://www.gravatar.com/avatar/' . md5(strtolower(trim($email))) . '?d=identicon';

        self::ensure_author_record($name, $email, $country, $avatar);

        return [
            'display_name' => $name,
            'email' => $email,
            'country_code' => $country,
            'avatar_url' => $avatar,
        ];
    }

    public static function random_name($country_code = 'US'): string
    {
        $defaults = ['Alex', 'Sam', 'Taylor', 'Jordan', 'Casey', 'Riley', 'Morgan', 'Avery', 'Jamie', 'Cameron', 'Harper', 'Quinn'];
        // Later: load country specific from data/names
        return $defaults[array_rand($defaults)];
    }

    private static function pick_country(array $countries): string
    {
        if (empty($countries)) {
            return 'US';
        }
        return $countries[array_rand($countries)];
    }

    private static function ensure_author_record(string $name, string $email, ?string $country, string $avatar): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'bulk_reviews_authors';
        $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE email=%s LIMIT 1", $email));
        if ($exists) {
            return;
        }
        $wpdb->insert($table, [
            'display_name' => $name,
            'email' => $email,
            'country_code' => $country,
            'gender' => null,
            'age_group' => null,
            'avatar_url' => $avatar,
            'created_at' => current_time('mysql'),
        ]);
    }

    public static function author_has_review_for_product(string $email, int $product_id): bool
    {
        global $wpdb;
        return (bool) $wpdb->get_var($wpdb->prepare(
            "SELECT comment_ID FROM {$wpdb->comments} WHERE comment_post_ID=%d AND comment_author_email=%s AND comment_type='review' LIMIT 1",
            $product_id,
            $email
        ));
    }
}
