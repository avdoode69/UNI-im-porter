<?php
if (!defined('ABSPATH')) {
    exit;
}

final class WPMB_UIC_DB {
    protected static $instance = null;

    public $projects;
    public $items;
    public $logs;

    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function __construct() {
        global $wpdb;
        $this->projects = $wpdb->prefix . 'wpmb_uic_projects';
        $this->items = $wpdb->prefix . 'wpmb_uic_items';
        $this->logs = $wpdb->prefix . 'wpmb_uic_logs';
    }

    public static function activate() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();
        $projects = $wpdb->prefix . 'wpmb_uic_projects';
        $items = $wpdb->prefix . 'wpmb_uic_items';
        $logs = $wpdb->prefix . 'wpmb_uic_logs';

        $sql = array();

        $sql[] = "CREATE TABLE IF NOT EXISTS {$projects} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            title varchar(255) NOT NULL,
            status varchar(32) NOT NULL DEFAULT 'draft',
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id)
        ) {$charset_collate};";

        $sql[] = "CREATE TABLE IF NOT EXISTS {$items} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            project_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            code varchar(120) NOT NULL,
            payload longtext NOT NULL,
            status varchar(32) NOT NULL DEFAULT 'staged',
            validation longtext NULL,
            error_text longtext NULL,
            wp_post_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY project_id (project_id),
            KEY code (code),
            KEY status (status)
        ) {$charset_collate};";

        $sql[] = "CREATE TABLE IF NOT EXISTS {$logs} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            project_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            item_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            level varchar(32) NOT NULL DEFAULT 'info',
            message longtext NOT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY project_id (project_id),
            KEY item_id (item_id)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    public static function ensure_settings_defaults() {
        $defaults = array(
            'readers' => array('csv', 'json', 'xml', 'zip'),
            'preserve_existing' => true,
            'allow_duplicate_check' => true,
            'default_status' => 'draft',
        );

        $current = get_option('wpmb_uic_settings', array());
        $updated = wp_parse_args($current, $defaults);
        update_option('wpmb_uic_settings', $updated, false);
    }

    public function create_project($title = '') {
        global $wpdb;

        $title = trim((string) $title);
        if ($title === '') {
            $title = 'Project ' . gmdate('Y-m-d H:i:s');
        }

        $wpdb->insert($this->projects, array(
            'title' => $title,
            'status' => 'draft',
        ), array('%s', '%s'));

        return (int) $wpdb->insert_id;
    }

    public function upsert_item($project_id, $code, $payload = array()) {
        global $wpdb;

        $project_id = absint($project_id);
        $code = trim((string) $code);
        if ($project_id <= 0 || $code === '') {
            return new WP_Error('invalid_item', 'project_id หรือ code ไม่ถูกต้อง');
        }

        $normalized = self::normalize_payload($payload);
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM {$this->items} WHERE project_id=%d AND code=%s LIMIT 1",
            $project_id,
            $code
        ), ARRAY_A);

        if ($existing) {
            $wpdb->update(
                $this->items,
                array(
                    'payload' => wp_json_encode($normalized),
                    'updated_at' => current_time('mysql'),
                ),
                array('id' => (int) $existing['id']),
                array('%s', '%s'),
                array('%d')
            );
            return (int) $existing['id'];
        }

        $wpdb->insert(
            $this->items,
            array(
                'project_id' => $project_id,
                'code' => $code,
                'payload' => wp_json_encode($normalized),
                'status' => 'staged',
            ),
            array('%d', '%s', '%s', '%s')
        );

        return (int) $wpdb->insert_id;
    }

    public function log_event($project_id, $item_id, $level, $message) {
        global $wpdb;
        $wpdb->insert(
            $this->logs,
            array(
                'project_id' => absint($project_id),
                'item_id' => absint($item_id),
                'level' => sanitize_key((string) $level),
                'message' => (string) $message,
            ),
            array('%d', '%d', '%s', '%s')
        );
    }

    public static function normalize_payload($payload) {
        if (!is_array($payload)) {
            return array();
        }

        $payload = array_map(function ($value) {
            if (is_array($value)) {
                return array_map(function ($v) {
                    return is_scalar($v) ? $v : wp_json_encode($v);
                }, $value);
            }

            return is_scalar($value) ? $value : wp_json_encode($value);
        }, $payload);

        return $payload;
    }
}

