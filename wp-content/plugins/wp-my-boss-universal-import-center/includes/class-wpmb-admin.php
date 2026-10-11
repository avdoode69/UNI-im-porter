<?php
if (!defined('ABSPATH')) {
    exit;
}

final class WPMB_UIC_Admin {
    private static $instance;
    private $hook_suffixes = array();

    public static function instance() {
        if (!self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('admin_menu', array($this, 'menu'));
        add_action('admin_enqueue_scripts', array($this, 'assets'));
        add_filter('plugin_action_links_' . plugin_basename(WPMB_UIC_FILE), array($this, 'action_links'));
        add_action('admin_post_wpmb_uic_download_report', array($this, 'download_report'));
        add_filter('manage_posts_columns', array($this, 'add_code_column'), 20);
        add_action('manage_posts_custom_column', array($this, 'render_code_column'), 10, 2);
        add_filter('manage_edit-post_sortable_columns', array($this, 'make_code_column_sortable'));
        add_action('pre_get_posts', array($this, 'handle_code_column_order'));
        add_action('add_meta_boxes', array($this, 'add_code_meta_box'));
        add_action('admin_footer', array($this, 'render_modals'));
    }

    public function action_links($links) {
        array_unshift($links, '<a href="' . esc_url(admin_url('admin.php?page=wpmb-uic')) . '">เปิด UNI-IM-PORT</a>');
        return $links;
    }

    public function menu() {
        $icon = 'dashicons-upload';

        $this->hook_suffixes[] = add_menu_page('UNI-IM-PORT', 'UNI-IM-PORT', 'manage_options', 'wpmb-uic', array($this, 'dashboard_page'), $icon, 57.20);
        $this->hook_suffixes[] = add_submenu_page('wpmb-uic', 'UNI-IM-PORT', 'นำเข้า / ส่งออก', 'manage_options', 'wpmb-uic', array($this, 'dashboard_page'));
        $this->hook_suffixes[] = add_submenu_page('wpmb-uic', 'UNI Portable', 'Export UNI Portable', 'manage_options', 'wpmb-uic-portable', array($this, 'portable_page'));
        $this->hook_suffixes[] = add_submenu_page(null, 'Import Project', 'Import Project', 'manage_options', 'wpmb-uic-project', array($this, 'project_page'));
        $this->hook_suffixes[] = add_submenu_page('wpmb-uic', 'สแกนทั้งเว็บ', 'สแกนข้อมูลทั้งเว็บ', 'manage_options', 'wpmb-uic-scan', array($this, 'scan_page'));
        $this->hook_suffixes[] = add_submenu_page('wpmb-uic', 'Media Cleanup', 'รูปไม่ได้ใช้งาน', 'delete_posts', 'wpmb-uic-media', array($this, 'media_page'));
        $this->hook_suffixes[] = add_submenu_page('wpmb-uic', 'Taxonomy & SEO', 'Taxonomy และ Index', 'manage_categories', 'wpmb-uic-taxonomy', array($this, 'taxonomy_page'));
        $this->hook_suffixes[] = add_submenu_page('wpmb-uic', 'Settings', 'ตั้งค่าและตรวจระบบ', 'manage_options', 'wpmb-uic-settings', array($this, 'settings_page'));
    }

    public function assets($hook) {
        if (!in_array($hook, $this->hook_suffixes, true) && strpos($hook, 'wpmb-uic') === false) {
            return;
        }

        wp_enqueue_media();
        wp_enqueue_style('wpmb-uic-admin', WPMB_UIC_URL . 'assets/admin.css', array(), WPMB_UIC_VERSION);
        wp_enqueue_script('wpmb-uic-admin', WPMB_UIC_URL . 'assets/admin.js', array('jquery', 'jquery-ui-sortable'), WPMB_UIC_VERSION, true);
        wp_enqueue_style('wpmb-uic-admin-v2', WPMB_UIC_URL . 'assets/admin-v2.css', array('wpmb-uic-admin'), WPMB_UIC_VERSION);
        wp_enqueue_script('wpmb-uic-admin-v2', WPMB_UIC_URL . 'assets/admin-v2.js', array('jquery', 'wpmb-uic-admin'), WPMB_UIC_VERSION, true);
    }

    public function dashboard_page() {
        echo '<div class="wrap wpmb-wrap"><h1>UNI-IM-PORT</h1><p>Plugin structure and admin pages are available.</p></div>';
    }

    public function portable_page() { echo '<div class="wrap wpmb-wrap"><h1>Export UNI Portable</h1></div>'; }
    public function project_page() { echo '<div class="wrap wpmb-wrap"><h1>Import Project</h1></div>'; }
    public function scan_page() { echo '<div class="wrap wpmb-wrap"><h1>สแกนข้อมูลทั้งเว็บ</h1></div>'; }
    public function media_page() { echo '<div class="wrap wpmb-wrap"><h1>Media Cleanup</h1></div>'; }
    public function taxonomy_page() { echo '<div class="wrap wpmb-wrap"><h1>Taxonomy & SEO</h1></div>'; }
    public function settings_page() { echo '<div class="wrap wpmb-wrap"><h1>Settings</h1></div>'; }

    public function download_report() {}
    public function add_code_column($columns) { return $columns; }
    public function render_code_column($column, $post_id) {}
    public function make_code_column_sortable($columns) { return $columns; }
    public function handle_code_column_order($query) {}
    public function add_code_meta_box() {}
    public function render_modals() {}
}
