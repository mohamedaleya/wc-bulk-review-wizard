# Bulk Review Wizard

A WooCommerce plugin to generate realistic, localized product reviews in bulk through a guided wizard. This is an MVP scaffold implementing the structure, admin UI, AJAX endpoints, database schema, and generation stubs.

## Install

1. Copy the `wc-bulk-review-generator` folder to `wp-content/plugins/`.
2. Activate "Bulk Review Wizard" in WordPress admin.

## Requirements

- WordPress 6.0+
- WooCommerce active
- PHP 7.4+

## Development

- Admin page under WooCommerce > Bulk Reviews
- AJAX endpoints: `brw_search_products`, `brw_preview_generation`, `brw_start_generation`, `brw_generation_progress`
- Cron hook: `brw_process_jobs_event`

## Security

- Nonce `brw_nonce` is required for all AJAX requests.
- Capability `manage_woocommerce` required.
