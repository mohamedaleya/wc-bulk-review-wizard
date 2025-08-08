<?php
if (! defined('ABSPATH')) {
    exit;
}

class BRW_Validator
{
    public static function validate_settings(array $settings)
    {
        $errors = new WP_Error();
        if (empty($settings['products'])) {
            $errors->add('no_products', __('No products selected.', 'bulk-review-wizard'));
        }
        if (empty($settings['reviews_per_product'])) {
            $errors->add('no_count', __('Specify reviews per product.', 'bulk-review-wizard'));
        }
        return $errors->has_errors() ? $errors : $settings;
    }
}
