<?php
/**
 * ═══════════════════════════════════════════════════════════════
 * 📜 Database Layer ของ UNI-IM-PORT v1.0.15.3
 * ═══════════════════════════════════════════════════════════════
 *
 * - สร้างตารางฐานข้อมูล (7 ตาราง)
 * - CRUD Operations
 * - Settings Defaults
 *
 * 🚫 v1.0.15.3: บังคับปิด Index/Noindex ถาวรใน 2 จุด
 *
 * ═══════════════════════════════════════════════════════════════
 */

if (!defined('ABSPATH')) {
    exit;
}

final class WPMB_UIC_DB {

    private static $instance;

    public $projects;
    public $files;
    public $items;
    public $media_index;
    public $post_index;
    public $term_index;
    public $logs;

    /* ============================================================
       🏗️ SINGLETON
       ============================================================ */
    public static function instance() {
        if (!self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        global $wpdb;

        $p = $wpdb->prefix . 'wpmb_uic_';

        $this->projects    = $p . 'projects';
        $this->files       = $p . 'files';
        $this->items       = $p . 'items';
        $this->media_index = $p . 'media_index';
        $this->post_index  = $p . 'post_index';
        $this->term_index  = $p . 'term_index';
        $this->logs        = $p . 'logs';
    }

    /* ============================================================
       🚀 ACTIVATE — สร้างตารางฐานข้อมูล
       ============================================================ */
    public static function activate() {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $db = self::instance();
        $charset = $wpdb->get_charset_collate();

        $sql = array();

        // ─── 1. Projects ───
        $sql[] = "CREATE TABLE {$db->projects} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(190) NOT NULL,
            status varchar(30) NOT NULL DEFAULT 'draft',
            mode varchar(30) NOT NULL DEFAULT 'mixed',
            settings longtext NULL,
            stats longtext NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY status (status)
        ) $charset;";

        // ─── 2. Files ───
        $sql[] = "CREATE TABLE {$db->files} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            project_id bigint(20) unsigned NOT NULL,
            parent_file_id bigint(20) unsigned NOT NULL DEFAULT 0,
            original_name text NOT NULL,
            stored_path text NOT NULL,
            file_type varchar(30) NOT NULL,
            mime varchar(100) NOT NULL DEFAULT '',
            size_bytes bigint(20) unsigned NOT NULL DEFAULT 0,
            file_hash char(64) NOT NULL DEFAULT '',
            analysis longtext NULL,
            status varchar(30) NOT NULL DEFAULT 'uploaded',
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY project_id (project_id),
            KEY parent_file_id (parent_file_id),
            KEY file_hash (file_hash)
        ) $charset;";

        // ─── 3. Items ───
        $sql[] = "CREATE TABLE {$db->items} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            project_id bigint(20) unsigned NOT NULL,
            code varchar(100) NOT NULL,
            source_key varchar(190) NOT NULL DEFAULT '',
            payload longtext NULL,
            validation longtext NULL,
            status varchar(30) NOT NULL DEFAULT 'staged',
            step varchar(60) NOT NULL DEFAULT 'รอตรวจสอบ',
            attempts smallint unsigned NOT NULL DEFAULT 0,
            error_text longtext NULL,
            wp_post_id bigint(20) unsigned NOT NULL DEFAULT 0,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY project_code (project_id,code),
            KEY project_status (project_id,status)
        ) $charset;";

        // ─── 4. Media Index ───
        $sql[] = "CREATE TABLE {$db->media_index} (
            attachment_id bigint(20) unsigned NOT NULL,
            code varchar(100) NOT NULL DEFAULT '',
            file_name text NOT NULL,
            media_url text NOT NULL,
            alt_text text NULL,
            media_title text NULL,
            width int unsigned NOT NULL DEFAULT 0,
            height int unsigned NOT NULL DEFAULT 0,
            orientation varchar(20) NOT NULL DEFAULT 'unknown',
            is_banner tinyint(1) NOT NULL DEFAULT 0,
            used_count int unsigned NOT NULL DEFAULT 0,
            scanned_at datetime NOT NULL,
            PRIMARY KEY (attachment_id),
            KEY code (code),
            KEY orientation (orientation),
            KEY unused (used_count,is_banner)
        ) $charset;";

        // ─── 5. Post Index ───
        $sql[] = "CREATE TABLE {$db->post_index} (
            post_id bigint(20) unsigned NOT NULL,
            code varchar(100) NOT NULL DEFAULT '',
            post_title text NULL,
            post_slug varchar(191) NOT NULL DEFAULT '',
            post_status varchar(30) NOT NULL DEFAULT '',
            modified_at datetime NULL,
            scanned_at datetime NOT NULL,
            PRIMARY KEY (post_id),
            KEY code (code),
            KEY post_slug (post_slug)
        ) $charset;";

        // ─── 6. Term Index ───
        $sql[] = "CREATE TABLE {$db->term_index} (
            term_taxonomy_id bigint(20) unsigned NOT NULL,
            term_id bigint(20) unsigned NOT NULL,
            taxonomy varchar(64) NOT NULL,
            term_name text NOT NULL,
            normalized_name varchar(191) NOT NULL,
            term_slug varchar(191) NOT NULL,
            object_count bigint(20) unsigned NOT NULL DEFAULT 0,
            index_state varchar(20) NOT NULL DEFAULT 'auto',
            scanned_at datetime NOT NULL,
            PRIMARY KEY (term_taxonomy_id),
            KEY tax_norm (taxonomy,normalized_name),
            KEY tax_count (taxonomy,object_count)
        ) $charset;";

        // ─── 7. Logs ───
        $sql[] = "CREATE TABLE {$db->logs} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            project_id bigint(20) unsigned NOT NULL DEFAULT 0,
            item_id bigint(20) unsigned NOT NULL DEFAULT 0,
            level varchar(20) NOT NULL DEFAULT 'info',
            action_name varchar(100) NOT NULL DEFAULT '',
            message longtext NOT NULL,
            context longtext NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY project_item (project_id,item_id),
            KEY level (level)
        ) $charset;";

        foreach ($sql as $statement) {
            dbDelta($statement);
        }

        // ─── Schedule Daily Maintenance ───
        if (!wp_next_scheduled('wpmb_uic_daily_maintenance')) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', 'wpmb_uic_daily_maintenance');
        }

        // ─── Default Settings ───
        $defaults = array(
            'title_max' => 70,
            'meta_max' => 140,
            'taxonomy_threshold' => 10,
            // 🚫 v1.0.15.3: ปิด Index/Noindex ถาวร — บังคับ 0 เสมอ
            'taxonomy_index_control_enabled' => 0,
            'taxonomy_auto_index' => 0,
            'taxonomy_auto_noindex' => 0,
            'taxonomy_types' => array('category', 'post_tag', 'actors', 'studio'),
            'force_local_gallery' => 1,
            'allow_external_missing' => 0,
            'unused_ignore_banners' => 1,
            'queue_mode' => 'one',
            'queue_batch' => 1,
            'alt_cover_template' => '{code} {actor_en}',
            'alt_poster_template' => '{code} {actor_en}',
            'alt_gallery_template' => '{code} {actor_en}',
            'media_target_format' => 'webp',
            'media_quality' => 90,
            'media_keep_original' => 0,
            'media_convert_fallback' => 'original',
            'media_gallery_fallback_cover' => 1,
            'protect_theme_meta' => 1,
            'duration_format' => 'auto',
        );

        add_option('wpmb_uic_settings', $defaults, '', false);
        update_option('wpmb_uic_db_version', WPMB_UIC_VERSION, false);
    }

    /* ============================================================
       🔧 ENSURE SETTINGS DEFAULTS
       ============================================================
       Apply screenshot-approved defaults once per plugin version.
       Existing custom values are kept; values that exactly match the old
       built-in defaults are upgraded to the new defaults.
       ============================================================ */
    public static function ensure_settings_defaults() {
        $target_version = defined('WPMB_UIC_VERSION') ? WPMB_UIC_VERSION : '1.0.15.3';
        $migrated_version = (string) get_option('wpmb_uic_settings_defaults_version', '');

        // 🚫 v1.0.15.3: บังคับปิด Index/Noindex ถาวร ทุกครั้งที่โหลด
        $force = get_option('wpmb_uic_settings', array());
        if (!is_array($force)) {
            $force = array();
        }
        $force['taxonomy_index_control_enabled'] = 0;
        $force['taxonomy_auto_index'] = 0;
        $force['taxonomy_auto_noindex'] = 0;
        update_option('wpmb_uic_settings', $force, false);

        if ($migrated_version !== '' && version_compare($migrated_version, $target_version, '>=')) {
            return;
        }

        $apply_previous_defaults = $migrated_version === ''
            || version_compare($migrated_version, '1.0.13.3', '<');

        $defaults = array(
            'alt_cover_template' => '{code} {actor_en}',
            'alt_poster_template' => '{code} {actor_en}',
            'alt_gallery_template' => '{code} {actor_en}',
            'media_target_format' => 'webp',
            'media_quality' => 90,
            'media_keep_original' => 0,
            'media_convert_fallback' => 'original',
            'media_gallery_fallback_cover' => 1,
            'protect_theme_meta' => 1,
            'duration_format' => 'auto',
            'queue_mode' => 'one',
            'queue_batch' => 1,
        );

        $previous_defaults = array(
            'alt_cover_template' => '{focus_or_code} {actor_en}',
            'alt_poster_template' => '{code} {actor_en} poster',
            'alt_gallery_template' => '{code} {actor_en} รูปที่ {number}',
            'media_target_format' => 'original',
            'media_quality' => 85,
            'media_gallery_fallback_cover' => 0,
        );

        $settings = get_option('wpmb_uic_settings', array());
        if (!is_array($settings)) {
            $settings = array();
        }

        foreach ($defaults as $key => $value) {
            if (!array_key_exists($key, $settings)) {
                $settings[$key] = $value;
                continue;
            }
            if (!$apply_previous_defaults || !array_key_exists($key, $previous_defaults)) {
                continue;
            }

            $old_value = $previous_defaults[$key];
            $matches_old_default = ($key === 'media_quality' || $key === 'media_gallery_fallback_cover')
                ? (is_numeric($settings[$key]) && (int) $settings[$key] === (int) $old_value)
                : ($settings[$key] === $old_value);

            if ($matches_old_default) {
                $settings[$key] = $value;
            }
        }

        // 🚫 บังคับปิด Index/Noindex อีกครั้ง หลัง migration
        $settings['taxonomy_index_control_enabled'] = 0;
        $settings['taxonomy_auto_index'] = 0;
        $settings['taxonomy_auto_noindex'] = 0;

        update_option('wpmb_uic_settings', $settings, false);
        update_option('wpmb_uic_settings_defaults_version', $target_version, false);
    }

    /* ============================================================
       📁 CREATE PROJECT
       ============================================================ */
    public function create_project($name, $mode = 'mixed') {
        global $wpdb;

        $now = current_time('mysql');

        $wpdb->insert($this->projects, array(
            'name'       => sanitize_text_field($name),
            'status'     => 'draft',
            'mode'       => sanitize_key($mode),
            'settings'   => wp_json_encode(array()),
            'stats'      => wp_json_encode(array()),
            'created_at' => $now,
            'updated_at' => $now,
        ), array('%s', '%s', '%s', '%s', '%s', '%s', '%s'));

        return (int) $wpdb->insert_id;
    }

    /* ============================================================
       📖 GET PROJECT
       ============================================================ */
    public function get_project($id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->projects} WHERE id=%d",
            $id
        ), ARRAY_A);
    }

    /* ============================================================
       📋 LIST PROJECTS
       ============================================================ */
    public function list_projects($limit = 50) {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->projects} ORDER BY updated_at DESC LIMIT %d",
            $limit
        ), ARRAY_A);
    }

    /* ============================================================
       🧹 CLEAR PROJECT DATA
       ============================================================ */
    public function clear_project_data($project_id) {
        return $this->purge_project((int) $project_id, false);
    }

    /* ============================================================
       🗑️ DELETE PROJECT
       ============================================================ */
    public function delete_project($project_id) {
        return $this->purge_project((int) $project_id, true);
    }

    /* ============================================================
       🔥 PURGE PROJECT (Private Helper)
       ============================================================ */
    private function purge_project($project_id, $delete_project) {
        global $wpdb;

        $project_id = (int) $project_id;
        $project = $this->get_project($project_id);

        if (!$project) {
            return new WP_Error('wpmb_project_missing', 'ไม่พบ Import Project');
        }

        $running = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->items} WHERE project_id=%d AND status='running'",
            $project_id
        ));

        if ($running > 0) {
            return new WP_Error('wpmb_project_running', 'มีรายการกำลังนำเข้าอยู่ กรุณารอให้จบ');
        }

        $counts = array(
            'files' => (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->files} WHERE project_id=%d",
                $project_id
            )),
            'items' => (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->items} WHERE project_id=%d",
                $project_id
            )),
            'logs' => (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->logs} WHERE project_id=%d",
                $project_id
            )),
        );

        $wpdb->query('START TRANSACTION');
        $ok = true;

        $ok = $wpdb->delete($this->logs, array('project_id' => $project_id), array('%d')) !== false && $ok;
        $ok = $wpdb->delete($this->items, array('project_id' => $project_id), array('%d')) !== false && $ok;
        $ok = $wpdb->delete($this->files, array('project_id' => $project_id), array('%d')) !== false && $ok;

        if ($delete_project) {
            $ok = $wpdb->delete($this->projects, array('id' => $project_id), array('%d')) !== false && $ok;
        } else {
            $ok = $wpdb->update($this->projects, array(
                'status'     => 'draft',
                'settings'   => wp_json_encode(array()),
                'stats'      => wp_json_encode(array()),
                'updated_at' => current_time('mysql'),
            ), array('id' => $project_id), array('%s', '%s', '%s', '%s'), array('%d')) !== false && $ok;
        }

        if (!$ok) {
            $wpdb->query('ROLLBACK');
            return new WP_Error('wpmb_project_purge_failed', 'ล้างข้อมูลโปรเจกต์ในฐานข้อมูลไม่สำเร็จ');
        }

        $wpdb->query('COMMIT');

        $storage = $this->remove_project_storage($project_id);

        return array(
            'project_id'       => $project_id,
            'deleted_project'  => (bool) $delete_project,
            'removed'          => $counts,
            'storage_removed'  => !is_wp_error($storage),
            'warnings'         => is_wp_error($storage) ? array($storage->get_error_message()) : array(),
        );
    }

    /* ============================================================
       🗑️ REMOVE PROJECT STORAGE (Private Helper)
       ============================================================ */
    private function remove_project_storage($project_id) {
        $uploads = wp_upload_dir();

        if (!empty($uploads['error'])) {
            return new WP_Error('wpmb_uploads_error', (string) $uploads['error']);
        }

        $base = wp_normalize_path(trailingslashit($uploads['basedir']) . 'wp-my-boss-imports/');
        $root = wp_normalize_path($base . 'project-' . (int) $project_id);

        if (strpos(trailingslashit($root), trailingslashit($base)) !== 0) {
            return new WP_Error('wpmb_unsafe_project_path', 'ปฏิเสธการลบ path ที่อยู่นอกพื้นที่ Import Center');
        }

        if (!file_exists($root) && !is_link($root)) {
            return true;
        }

        $failures = array();
        $this->remove_tree_safe($root, $failures);

        if ($failures) {
            return new WP_Error(
                'wpmb_storage_cleanup_partial',
                'ล้างไฟล์ staging ไม่ครบ: ' . implode(', ', array_slice($failures, 0, 5))
            );
        }

        return true;
    }

    /* ============================================================
       🌲 REMOVE TREE SAFE (Private Helper)
       ============================================================ */
    private function remove_tree_safe($path, array &$failures) {
        if (is_link($path) || is_file($path)) {
            if (!@unlink($path) && (file_exists($path) || is_link($path))) {
                $failures[] = wp_basename($path);
            }
            return;
        }

        if (!is_dir($path)) {
            return;
        }

        try {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST
            );

            foreach ($iterator as $entry) {
                $entry_path = $entry->getPathname();

                if ($entry->isLink() || $entry->isFile()) {
                    if (!@unlink($entry_path) && (file_exists($entry_path) || is_link($entry_path))) {
                        $failures[] = $entry->getFilename();
                    }
                } elseif (!@rmdir($entry_path) && is_dir($entry_path)) {
                    $failures[] = $entry->getFilename();
                }
            }
        } catch (Throwable $e) {
            $failures[] = $e->getMessage();
        }

        if (!@rmdir($path) && is_dir($path)) {
            $failures[] = wp_basename($path);
        }
    }

    /* ============================================================
       🔄 UPDATE PROJECT
       ============================================================ */
    public function update_project($id, array $data) {
        global $wpdb;

        $allowed = array('name', 'status', 'mode', 'settings', 'stats');
        $clean = array();

        foreach ($allowed as $key) {
            if (array_key_exists($key, $data)) {
                $clean[$key] = is_array($data[$key])
                    ? wp_json_encode($data[$key])
                    : $data[$key];
            }
        }

        $clean['updated_at'] = current_time('mysql');

        return $wpdb->update($this->projects, $clean, array('id' => (int) $id));
    }

    /* ============================================================
       💾 UPSERT ITEM
       ============================================================ */
    public function upsert_item($project_id, $code, array $incoming, $source_key = '') {
        global $wpdb;

        $code = WPMB_UIC_Code::normalize_code($code);

        if ($code === '') {
            return new WP_Error('wpmb_no_code', 'ไม่พบ CODE สำหรับรวมข้อมูล');
        }

        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->items} WHERE project_id=%d AND code=%s",
            $project_id,
            $code
        ), ARRAY_A);

        $now = current_time('mysql');

        if ($existing) {
            $payload = json_decode((string) $existing['payload'], true);
            if (!is_array($payload)) {
                $payload = array();
            }

            $payload = $this->deep_merge_payload($payload, $incoming);

            $wpdb->update($this->items, array(
                'payload'    => wp_json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'source_key' => substr(trim($existing['source_key'] . ',' . $source_key, ','), 0, 190),
                'updated_at' => $now,
            ), array('id' => (int) $existing['id']));

            return (int) $existing['id'];
        }

        $payload = $this->deep_merge_payload(array('code' => $code, 'sources' => array()), $incoming);

        $wpdb->insert($this->items, array(
            'project_id' => (int) $project_id,
            'code'       => $code,
            'source_key' => sanitize_text_field($source_key),
            'payload'    => wp_json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'validation' => wp_json_encode(array()),
            'status'     => 'staged',
            'step'       => 'รอ Preview',
            'attempts'   => 0,
            'error_text' => '',
            'wp_post_id' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ));

        return (int) $wpdb->insert_id;
    }

    /* ============================================================
       🔀 DEEP MERGE PAYLOAD (Private Helper)
       ============================================================ */
    private function deep_merge_payload(array $base, array $incoming) {
        foreach ($incoming as $key => $value) {
            if ($key === 'taxonomies' && is_array($value)) {
                $base[$key] = isset($base[$key]) && is_array($base[$key]) ? $base[$key] : array();

                foreach ($value as $taxonomy => $names) {
                    $old_names = isset($base[$key][$taxonomy]) && is_array($base[$key][$taxonomy])
                        ? $base[$key][$taxonomy]
                        : array();
                    $new_names = is_array($names) ? $names : array($names);
                    $base[$key][$taxonomy] = $this->unique_taxonomy_names(array_merge($old_names, $new_names));
                }
            } elseif ($key === 'media_hints' && is_array($value)) {
                $base[$key] = isset($base[$key]) && is_array($base[$key]) ? $base[$key] : array();

                if (!empty($value['featured'])) {
                    $base[$key]['featured'] = $value['featured'];
                }
                if (!empty($value['gallery'])) {
                    $old_gallery = isset($base[$key]['gallery']) && is_array($base[$key]['gallery'])
                        ? $base[$key]['gallery']
                        : array();
                    $base[$key]['gallery'] = $this->unique_scalar_list(
                        array_merge($old_gallery, (array) $value['gallery'])
                    );
                }
            } elseif (in_array($key, array('media', 'sources', 'gallery_source_urls', 'source_domains'), true)) {
                $old = isset($base[$key]) && is_array($base[$key]) ? $base[$key] : array();
                $new = is_array($value) ? $value : array($value);
                $merged = array_merge($old, $new);

                $seen = array();
                $out = array();

                foreach ($merged as $entry) {
                    $fingerprint = is_array($entry)
                        ? md5(wp_json_encode($entry))
                        : md5((string) $entry);

                    if (!isset($seen[$fingerprint])) {
                        $seen[$fingerprint] = true;
                        $out[] = $entry;
                    }
                }
                $base[$key] = $out;
            } elseif (is_array($value)
                && isset($base[$key])
                && is_array($base[$key])
                && $this->is_list($base[$key])
                && $this->is_list($value)
            ) {
                $base[$key] = $this->unique_scalar_list(array_merge($base[$key], $value));
            } elseif (is_array($value) && isset($base[$key]) && is_array($base[$key])) {
                $base[$key] = $this->deep_merge_payload($base[$key], $value);
            } elseif ($value !== '' && $value !== null && $value !== array()) {
                $base[$key] = $value;
            } elseif (!array_key_exists($key, $base)) {
                $base[$key] = $value;
            }
        }

        return $base;
    }

    /* ============================================================
       🔢 UNIQUE SCALAR LIST (Private Helper)
       ============================================================ */
    private function unique_scalar_list(array $values) {
        $seen = array();
        $out = array();

        foreach ($values as $value) {
            if (is_array($value) || is_object($value)) {
                $fingerprint = md5(wp_json_encode($value));
            } else {
                $fingerprint = trim((string) $value);
            }

            if ($fingerprint === '' || isset($seen[$fingerprint])) {
                continue;
            }

            $seen[$fingerprint] = true;
            $out[] = $value;
        }

        return $out;
    }

    /* ============================================================
       🏷️ UNIQUE TAXONOMY NAMES (Private Helper)
       ============================================================ */
    private function unique_taxonomy_names(array $values) {
        $seen = array();
        $out = array();

        foreach ($values as $value) {
            if (is_array($value) || is_object($value)) {
                continue;
            }

            $value = trim((string) $value);
            $fingerprint = WPMB_UIC_Code::taxonomy_key($value);

            if ($fingerprint === '' || isset($seen[$fingerprint])) {
                continue;
            }

            $seen[$fingerprint] = true;
            $out[] = $value;
        }

        return $out;
    }

    /* ============================================================
       📋 IS LIST (Private Helper)
       ============================================================ */
    private function is_list(array $value) {
        if ($value === array()) {
            return true;
        }
        return array_keys($value) === range(0, count($value) - 1);
    }

    /* ============================================================
       📝 LOG
       ============================================================ */
    public function log($project_id, $item_id, $level, $action, $message, $context = array()) {
        global $wpdb;

        $wpdb->insert($this->logs, array(
            'project_id'  => (int) $project_id,
            'item_id'     => (int) $item_id,
            'level'       => sanitize_key($level),
            'action_name' => sanitize_key($action),
            'message'     => (string) $message,
            'context'     => wp_json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'created_at'  => current_time('mysql'),
        ));
    }
}