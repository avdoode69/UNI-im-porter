<?php
if (!defined('ABSPATH')) {
    exit;
}

final class WPMB_UIC_Ajax {
    private static $instance;

    public static function instance() {
        if (!self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $actions = array(
            'create_project', 'upload_files', 'analyze_file', 'pending_files',
            'project_summary', 'clear_project', 'delete_project', 'current_status',
            'get_items', 'save_item', 'validate_item',
            'exclude_item', 'restore_item', 'bulk_item_status', 'bulk_fill_alt',
            'apply_field_rules', 'delete_staged_file', 'mark_ready',
            'import_next', 'reset_errors', 'match_library_media',
            'scan_site', 'scan_unused', 'trash_unused',
            'meta_scan', 'meta_save', 'meta_reset',
            'taxonomy_duplicates', 'taxonomy_merge', 'taxonomy_auto_index',
            'get_terms_browse',
            'save_settings',
            'get_item_preview', 'get_item_edit_form',
        );

        foreach ($actions as $action) {
            add_action('wp_ajax_wpmb_' . $action, array($this, $action));
        }
    }

    private function guard($capability = 'manage_options') {
        error_reporting(0);
        @ini_set('display_errors', 0);

        check_ajax_referer('wpmb_uic_admin', 'nonce');

        if (!current_user_can($capability)) {
            wp_send_json_error(array('message' => 'คุณไม่มีสิทธิ์ใช้งานส่วนนี้'), 403);
        }
    }

    public function create_project() {
        $this->guard('manage_options');

        $name = isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '';
        $mode = isset($_POST['mode']) ? sanitize_key($_POST['mode']) : 'mixed';

        if ($name === '') {
            $name = 'Import Project ' . current_time('Y-m-d H:i');
        }

        if (!in_array($mode, array('fresh', 'update', 'mixed'), true)) {
            $mode = 'mixed';
        }

        $id = WPMB_UIC_DB::instance()->create_project($name, $mode);

        wp_send_json_success(array(
            'project_id' => $id,
            'url' => admin_url('admin.php?page=wpmb-uic-project&project_id=' . $id),
        ));
    }

    public function save_settings() {
        $this->guard('manage_options');

        $old = get_option('wpmb_uic_settings', array());

        $field_enabled = array();
        $field_source = array();
        $field_lock = array();
        $preview_visibility = array();

        $lock_options = WPMB_UIC_Field_Rules::lock_options();

        foreach (WPMB_UIC_Field_Rules::definitions() as $field => $definition) {
            $field_enabled[$field] = !empty($_POST['field_enabled_' . $field]) ? 1 : 0;

            $source = sanitize_key($_POST['field_source_' . $field] ?? 'import');
            $field_source[$field] = array_key_exists($source, $definition['sources']) ? $source : 'import';

            $lock = sanitize_key($_POST['field_lock_' . $field] ?? 'overwrite');
            $field_lock[$field] = array_key_exists($lock, $lock_options) ? $lock : 'overwrite';
        }

        foreach (array_merge(
            array_keys(WPMB_UIC_Field_Rules::definitions()),
            array('category', 'post_tag', 'actors', 'studio', 'media')
        ) as $preview_field) {
            $visibility = sanitize_key($_POST['preview_visibility_' . $preview_field] ?? 'show');
            $preview_visibility[$preview_field] = in_array($visibility, array('show', 'readonly', 'hide'), true)
                ? $visibility
                : 'show';
        }

        $new = array(
            'title_max' => max(1, absint($_POST['title_max'] ?? 70)),
            'meta_max' => max(1, absint($_POST['meta_max'] ?? 140)),
            'taxonomy_threshold' => max(1, absint($_POST['taxonomy_threshold'] ?? 10)),
            'taxonomy_index_control_enabled' => 0,
            'taxonomy_auto_index' => 0,
            'taxonomy_auto_noindex' => 0,
            'taxonomy_types' => isset($_POST['taxonomy_types'])
                ? array_values(array_filter(array_map('sanitize_key', (array) $_POST['taxonomy_types'])))
                : array('category', 'post_tag', 'actors', 'studio'),
            'force_local_gallery' => !empty($_POST['force_local_gallery']) ? 1 : 0,
            'allow_external_missing' => !empty($_POST['allow_external_missing']) ? 1 : 0,
            'unused_ignore_banners' => !empty($_POST['unused_ignore_banners']) ? 1 : 0,
            'queue_mode' => sanitize_key($_POST['queue_mode'] ?? 'one'),
            'queue_batch' => max(1, absint($_POST['queue_batch'] ?? 1)),
            'alt_cover_template' => sanitize_text_field(wp_unslash($_POST['alt_cover_template'] ?? '{code} {actor_en}')),
            'alt_poster_template' => sanitize_text_field(wp_unslash($_POST['alt_poster_template'] ?? '{code} {actor_en}')),
            'alt_gallery_template' => sanitize_text_field(wp_unslash($_POST['alt_gallery_template'] ?? '{code} {actor_en}')),
            'media_target_format' => in_array(($_POST['media_target_format'] ?? 'webp'), array('original', 'webp', 'avif'), true)
                ? $_POST['media_target_format']
                : 'webp',
            'media_quality' => max(40, min(100, absint($_POST['media_quality'] ?? 90))),
            'media_keep_original' => !empty($_POST['media_keep_original']) ? 1 : 0,
            'media_convert_fallback' => (($_POST['media_convert_fallback'] ?? 'original') === 'stop') ? 'stop' : 'original',
            'media_gallery_fallback_cover' => !empty($_POST['media_gallery_fallback_cover']) ? 1 : 0,
            'protect_theme_meta' => !empty($_POST['protect_theme_meta']) ? 1 : 0,
            'duration_format' => in_array(($_POST['duration_format'] ?? 'auto'), array('auto', 'seconds', 'hms', 'ms'), true)
                ? $_POST['duration_format']
                : 'auto',
            'field_enabled' => $field_enabled,
            'field_source' => $field_source,
            'field_lock' => $field_lock,
            'preview_visibility' => $preview_visibility,
        );

        update_option('wpmb_uic_settings', array_merge(is_array($old) ? $old : array(), $new), false);

        wp_send_json_success(array('settings' => get_option('wpmb_uic_settings')));
    }

    public function current_status() {
        $this->guard('edit_posts');
        global $wpdb;
        $db = WPMB_UIC_DB::instance();

        $project_id = absint($_POST['project_id'] ?? 0);

        $item = $wpdb->get_row($wpdb->prepare(
            "SELECT id,code,status,step,error_text,updated_at FROM {$db->items} WHERE project_id=%d AND status='running' ORDER BY updated_at DESC LIMIT 1",
            $project_id
        ), ARRAY_A);

        wp_send_json_success(array(
            'item' => $item ?: null,
            'counts' => WPMB_UIC_Importer::status_counts($project_id),
        ));
    }
}
