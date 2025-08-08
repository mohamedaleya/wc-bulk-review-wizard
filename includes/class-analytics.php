<?php
if (! defined('ABSPATH')) {
    exit;
}

class BRW_Analytics
{
    public static function log_event(string $action, array $data = []): void
    {
        // Placeholder for audit logging
        // error_log( 'BRW ' . $action . ': ' . wp_json_encode( $data ) );
    }
}
