<?php
if (!defined('ABSPATH')) {
    exit;
}

final class WPMB_UIC_Portable_Settings {

    public static function collect() {
        global $wpdb;
        $db = WPMB_UIC_DB::instance();

        $queue_count = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$db->items} WHERE status IN ('staged', 'validated', 'running')"
        );

        $logs_count = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$db->logs}"
        );

        return array(
            'contains_queue' => $queue_count > 0,
            'queue_size' => $queue_count,
            'contains_logs' => $logs_count > 0,
            'logs_count' => $logs_count,
            'contains_runtime' => false,
        );
    }

    public static function export_settings() {
        return get_option('wpmb_uic_settings', array());
    }

    public static function import_settings($data) {
        if (!is_array($data)) {
            return new WP_Error('invalid_settings', 'ข้อมูลตั้งค่าไม่ถูกต้อง');
        }

        update_option('wpmb_uic_settings', $data, false);
        return true;
    }
}
