<?php
if (! defined('ABSPATH')) {
    exit;
}

class BRW_Localization_Handler
{
    public static function get_languages(): array
    {
        return [
            'en_US' => 'English (US)',
            'en_GB' => 'English (UK)',
            'fr_FR' => 'Français',
            'es_ES' => 'Español',
            'de_DE' => 'Deutsch',
            'ar'    => 'العربية',
        ];
    }
}
