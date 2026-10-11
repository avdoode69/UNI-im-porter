<?php
if (!defined('ABSPATH')) {
    exit;
}

final class WPMB_UIC_Admin {
    protected static $instance = null;

    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
            self::$instance->register();
        }

        return self::$instance;
    }

    public function register() {
        add_action('admin_menu', array($this, 'register_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
    }

    public function register_menu() {
        add_menu_page(
            'UNI-IM-PORT',
            'UNI-IM-PORT',
            'manage_options',
            'wpmb-uic',
            array($this, 'render_page'),
            'dashicons-upload',
            25
        );
    }

    public function enqueue_assets() {
        wp_enqueue_style('wpmb-uic-admin', WPMB_UIC_URL . 'assets/admin-v2.css', array(), WPMB_UIC_VERSION);
        wp_enqueue_script('wpmb-uic-admin', WPMB_UIC_URL . 'assets/admin-v2.js', array('jquery'), WPMB_UIC_VERSION, true);
    }

    public function render_page() {
        $project_id = 0;
        $project = null;

        if (class_exists('WPMB_UIC_DB')) {
            $db = WPMB_UIC_DB::instance();
            $project_id = $db->create_project('Quick Project');
            $project = array('id' => $project_id, 'title' => 'Quick Project');
        }

        echo '<div class="wrap wpmb-wrap">';
        echo '<h1>UNI-IM-PORT</h1>';
        echo '<p>Universal Import Center v' . esc_html(WPMB_UIC_VERSION) . '</p>';
        echo '<p><strong>Project ID:</strong> ' . esc_html((string) $project_id) . '</p>';
        echo '<p>ระบบกำลังพร้อมรับไฟล์ CSV / JSON / ZIP และจะคัดกรองข้อมูลที่มีอยู่ก่อน Import เพื่อหลีกเลี่ยงการสร้างข้อมูลซ้ำ</p>';
        echo '</div>';
    }
}
