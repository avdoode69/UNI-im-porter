<?php
if (!defined('ABSPATH')) {
    exit;
}

final class WPMB_UIC_Compat {

    private static $patches = array();

    public static function register() {
        add_action('plugins_loaded', array(__CLASS__, 'check_conflicts'), 15);
        add_action('admin_notices', array(__CLASS__, 'show_notices'));
        return true;
    }

    public static function check_conflicts() {
        // Check for conflicting plugins
        $conflicts = array(
            'wordpress-importer/wordpress-importer.php' => 'WordPress Importer',
        );

        foreach ($conflicts as $plugin_file => $plugin_name) {
            if (is_plugin_active($plugin_file)) {
                self::$patches['conflicts'][] = $plugin_name;
            }
        }
    }

    public static function show_notices() {
        if (!empty(self::$patches['conflicts']) && current_user_can('manage_options')) {
            foreach (self::$patches['conflicts'] as $plugin_name) {
                echo '<div class="notice notice-warning"><p>' .
                     esc_html__('UNI-IM-PORT is aware of: ', 'wp-my-boss-universal-import-center') .
                     esc_html($plugin_name) . '</p></div>';
            }
        }
    }

    public static function is_patched() {
        return true;
    }
}
