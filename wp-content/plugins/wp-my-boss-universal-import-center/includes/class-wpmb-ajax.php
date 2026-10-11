<?php
if (!defined('ABSPATH')) {
    exit;
}

final class WPMB_UIC_Ajax {
    protected static $instance = null;

    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
            self::$instance->register();
        }

        return self::$instance;
    }

    public function register() {
        add_action('wp_ajax_wpmb_uic_ping', array($this, 'ping'));
        add_action('wp_ajax_wpmb_uic_status', array($this, 'status'));
    }

    public function ping() {
        wp_send_json_success(array(
            'status' => 'ok',
            'version' => WPMB_UIC_VERSION,
            'time' => current_time('mysql'),
        ));
    }

    public function status() {
        $project_id = absint($_POST['project_id'] ?? 0);
        $counts = array(
            'total' => 0,
            'staged' => 0,
            'validated' => 0,
            'done' => 0,
            'error' => 0,
        );

        if ($project_id > 0 && class_exists('WPMB_UIC_Importer')) {
            $counts = WPMB_UIC_Importer::status_counts($project_id);
        }

        wp_send_json_success($counts);
    }
}
