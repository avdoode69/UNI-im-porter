<?php
/**
 * ═══════════════════════════════════════════════════════════════
 * 📜 นโยบาย AJAX ของ UNI-IM-PORT v1.0.15.3
 * ═══════════════════════════════════════════════════════════════
 *
 * - AJAX Endpoints ทั้งหมดของระบบ
 * - เพิ่ม 2 Endpoints ใหม่สำหรับ Modal:
 *   • get_item_preview     — โหลดข้อมูล Preview
 *   • get_item_edit_form   — โหลดฟอร์มแก้ไข
 *
 * 🚫 ปิดระบบ Index/Noindex ถาวร — บังคับ 0 ใน save_settings
 *
 * ═══════════════════════════════════════════════════════════════
 */

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
            // Project
            'create_project', 'upload_files', 'analyze_file', 'pending_files',
            'project_summary', 'clear_project', 'delete_project', 'current_status',

            // Items
            'get_items', 'save_item', 'validate_item',
            'exclude_item', 'restore_item', 'bulk_item_status', 'bulk_fill_alt',
            'apply_field_rules', 'delete_staged_file', 'mark_ready',

            // Import
            'import_next', 'reset_errors', 'match_library_media',

            // Scanner
            'scan_site', 'scan_unused', 'trash_unused',
            'meta_scan', 'meta_save', 'meta_reset',

            // Taxonomy
            'taxonomy_duplicates', 'taxonomy_merge', 'taxonomy_auto_index',
            'get_terms_browse',

            // Settings
            'save_settings',

            // 🎯 v1.0.15.3: Modal AJAX
            'get_item_preview', 'get_item_edit_form',
        );

        foreach ($actions as $action) {
            add_action('wp_ajax_wpmb_' . $action, array($this, $action));
        }
    }

    /* ============================================================
       🛡️ GUARD
       ============================================================ */
    private function guard($capability = 'manage_options') {
        error_reporting(0);
        @ini_set('display_errors', 0);

        check_ajax_referer('wpmb_uic_admin', 'nonce');

        if (!current_user_can($capability)) {
            wp_send_json_error(array('message' => 'คุณไม่มีสิทธิ์ใช้งานส่วนนี้'), 403);
        }
    }

    /* ============================================================
       📁 CREATE PROJECT
       ============================================================ */
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

    /* ============================================================
       📤 UPLOAD FILES
       ============================================================ */
    public function upload_files() {
        error_reporting(0);
        @ini_set('display_errors', 0);
        $this->guard('upload_files');

        $project_id = isset($_POST['project_id']) ? absint($_POST['project_id']) : 0;

        if (!$project_id || !WPMB_UIC_DB::instance()->get_project($project_id)) {
            wp_send_json_error(array('message' => 'ไม่พบโปรเจกต์'), 404);
        }

        if (empty($_FILES['files'])) {
            wp_send_json_error(array('message' => 'ยังไม่ได้เลือกไฟล์'), 400);
        }

        $files = $this->normalize_files_array($_FILES['files']);
        $uploads = wp_upload_dir();

        if (!empty($uploads['error'])) {
            wp_send_json_error(array('message' => $uploads['error']), 500);
        }

        $dir = trailingslashit($uploads['basedir'])
            . 'wp-my-boss-imports/project-' . $project_id . '/uploads/';

        if (!wp_mkdir_p($dir) || !is_writable($dir)) {
            wp_send_json_error(array('message' => 'ไม่มีสิทธิ์เขียนโฟลเดอร์นำเข้า: ' . $dir), 500);
        }

        $results = array();
        global $wpdb;
        $db = WPMB_UIC_DB::instance();

        foreach ($files as $file) {
            if ((int) $file['error'] !== UPLOAD_ERR_OK) {
                $results[] = array(
                    'name' => $file['name'],
                    'status' => 'error',
                    'message' => $this->upload_error_message((int) $file['error']),
                );
                continue;
            }

            $safe_name = sanitize_file_name(wp_basename($file['name']));
            $target = $dir . wp_unique_filename($dir, $safe_name);

            if (!@move_uploaded_file($file['tmp_name'], $target)) {
                $results[] = array(
                    'name' => $file['name'],
                    'status' => 'error',
                    'message' => 'ย้ายไฟล์ไปโฟลเดอร์กลางไม่ได้',
                );
                continue;
            }

            $type = WPMB_UIC_Readers::detect_type($target, $file['name']);

            if ($type === 'other') {
                @unlink($target);
                $results[] = array(
                    'name' => $file['name'],
                    'status' => 'ignored',
                    'message' => 'ชนิดไฟล์นี้ไม่ใช่ข้อมูลนำเข้าที่รองรับ',
                );
                continue;
            }

            $hash = hash_file('sha256', $target);
            $duplicate_id = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$db->files}
                 WHERE project_id=%d AND parent_file_id=0 AND file_hash=%s LIMIT 1",
                $project_id,
                $hash
            ));

            if ($duplicate_id) {
                @unlink($target);
                $results[] = array(
                    'file_id' => $duplicate_id,
                    'name' => $file['name'],
                    'type' => $type,
                    'status' => 'duplicate',
                    'message' => 'ไฟล์เดิมมีอยู่ในโปรเจกต์แล้ว',
                );
                continue;
            }

            $wpdb->insert($db->files, array(
                'project_id'     => $project_id,
                'parent_file_id' => 0,
                'original_name'  => $file['name'],
                'stored_path'    => $target,
                'file_type'      => $type,
                'mime'           => isset($file['type']) ? sanitize_mime_type($file['type']) : '',
                'size_bytes'     => filesize($target),
                'file_hash'      => $hash,
                'analysis'       => wp_json_encode(array()),
                'status'         => 'uploaded',
                'created_at'     => current_time('mysql'),
            ));

            $file_id = (int) $wpdb->insert_id;

            $results[] = array(
                'file_id' => $file_id,
                'name' => $file['name'],
                'type' => $type,
                'status' => 'uploaded',
                'analysis' => array('type' => $type, 'done' => false),
            );
        }

        WPMB_UIC_DB::instance()->update_project($project_id, array('status' => 'analyzing'));

        wp_send_json_success(array(
            'files' => $results,
            'summary' => $this->summary_data($project_id),
        ));
    }

    /* ============================================================
       📊 ANALYZE FILE
       ============================================================ */
    public function analyze_file() {
        $this->guard('upload_files');
        global $wpdb;
        $db = WPMB_UIC_DB::instance();

        $project_id = absint($_POST['project_id'] ?? 0);
        $file_id = absint($_POST['file_id'] ?? 0);
        $limit = max(1, min(50, absint($_POST['limit'] ?? 12)));

        $file = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$db->files} WHERE id=%d AND project_id=%d",
            $file_id,
            $project_id
        ), ARRAY_A);

        if (!$file) {
            wp_send_json_error(array('message' => 'ไม่พบไฟล์ในโปรเจกต์'), 404);
        }

        try {
            $result = WPMB_UIC_Readers::analyze_file_batch(
                $project_id,
                $file_id,
                $file['stored_path'],
                $file['original_name'],
                $limit
            );

            if (!empty($result['done']) && !empty($file['parent_file_id'])) {
                $this->update_parent_child_analysis($file, $result, 'analyzed');
            }

            if (!empty($result['done'])) {
                $pending = (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$db->files}
                     WHERE project_id=%d AND status IN ('uploaded','analyzing')",
                    $project_id
                ));

                if ($pending === 0) {
                    $errors = (int) $wpdb->get_var($wpdb->prepare(
                        "SELECT COUNT(*) FROM {$db->files}
                         WHERE project_id=%d AND status='error'",
                        $project_id
                    ));

                    $db->update_project($project_id, array(
                        'status' => $errors ? 'staged_with_errors' : 'staged',
                    ));
                }
            }

            wp_send_json_success(array(
                'file_id' => $file_id,
                'name' => $file['original_name'],
                'analysis' => $result,
                'summary' => $this->summary_data($project_id),
            ));

        } catch (Throwable $e) {
            $error_analysis = array(
                'type' => $file['file_type'],
                'error' => $e->getMessage(),
                'done' => true,
                'items' => 0,
                'media' => 0,
            );

            $wpdb->update($db->files, array(
                'status' => 'error',
                'analysis' => wp_json_encode($error_analysis, JSON_UNESCAPED_UNICODE),
            ), array('id' => $file_id));

            if (!empty($file['parent_file_id'])) {
                $this->update_parent_child_analysis($file, $error_analysis, 'error');
            }

            $pending = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$db->files}
                 WHERE project_id=%d AND status IN ('uploaded','analyzing')",
                $project_id
            ));

            if ($pending === 0) {
                $db->update_project($project_id, array('status' => 'staged_with_errors'));
            }

            wp_send_json_error(array(
                'message' => $e->getMessage(),
                'file_id' => $file_id,
                'done' => true,
            ), 500);
        }
    }

    /* ============================================================
       🌳 UPDATE PARENT CHILD ANALYSIS
       ============================================================ */
    private function update_parent_child_analysis(array $child_file, array $child_result, $status) {
        global $wpdb;
        $db = WPMB_UIC_DB::instance();

        $parent_id = !empty($child_file['parent_file_id']) ? (int) $child_file['parent_file_id'] : 0;
        if (!$parent_id) {
            return;
        }

        $parent = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$db->files} WHERE id=%d",
            $parent_id
        ), ARRAY_A);

        if (!$parent) {
            return;
        }

        $analysis = json_decode((string) $parent['analysis'], true);
        if (!is_array($analysis)) {
            return;
        }

        $items = 0;
        $media = 0;

        foreach ((array) ($analysis['children'] ?? array()) as &$entry) {
            if ((int) ($entry['file_id'] ?? 0) === (int) $child_file['id']) {
                $entry['queued'] = false;
                $entry['used'] = $status !== 'error';
                $entry['status'] = $status;
                $entry['items'] = (int) ($child_result['items'] ?? 0);
                $entry['media'] = (int) ($child_result['media'] ?? 0);
                $entry['error'] = (string) ($child_result['error'] ?? '');
            }
            $items += (int) ($entry['items'] ?? 0);
            $media += (int) ($entry['media'] ?? 0);
        }
        unset($entry);

        $analysis['items'] = $items;
        $analysis['media'] = $media;

        $wpdb->update($db->files, array(
            'analysis' => wp_json_encode($analysis, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ), array('id' => $parent_id));

        // ZIP ซ้อน
        if (!empty($parent['parent_file_id'])) {
            $parent['analysis'] = wp_json_encode($analysis, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $this->update_parent_child_analysis($parent, $analysis, isset($parent['status']) ? $parent['status'] : 'analyzed');
        }
    }

    /* ============================================================
       📊 PENDING FILES
       ============================================================ */
    public function pending_files() {
        $this->guard('upload_files');
        global $wpdb;
        $db = WPMB_UIC_DB::instance();

        $project_id = absint($_POST['project_id'] ?? 0);

        if (!$project_id || !$db->get_project($project_id)) {
            wp_send_json_error(array('message' => 'ไม่พบโปรเจกต์'), 404);
        }

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id, original_name, file_type, status FROM {$db->files}
             WHERE project_id=%d AND status IN ('uploaded','analyzing')
             ORDER BY id ASC",
            $project_id
        ), ARRAY_A);

        $files = array();
        foreach ($rows as $row) {
            $files[] = array(
                'file_id' => (int) $row['id'],
                'name' => $row['original_name'],
                'type' => $row['file_type'],
                'status' => $row['status'],
            );
        }

        wp_send_json_success(array('files' => $files));
    }

    /* ============================================================
       📋 GET ITEMS
       ============================================================ */
    public function get_items() {
        $this->guard('manage_options');
        global $wpdb;
        $db = WPMB_UIC_DB::instance();

        $project_id = absint($_POST['project_id'] ?? 0);
        $page = max(1, absint($_POST['page'] ?? 1));
        $per_page = max(1, min(100, absint($_POST['per_page'] ?? 20)));
        $status = sanitize_key($_POST['status'] ?? '');

        $where = 'project_id=%d';
        $args = array($project_id);

        if ($status !== '') {
            $where .= ' AND status=%s';
            $args[] = $status;
        } else {
            $where .= " AND status<>'excluded'";
        }

        $offset = ($page - 1) * $per_page;
        $args_list = array_merge($args, array($per_page, $offset));

        $items = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$db->items} WHERE {$where} ORDER BY id ASC LIMIT %d OFFSET %d",
            $args_list
        ), ARRAY_A);

        $total = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$db->items} WHERE {$where}",
            $args
        ));

        foreach ($items as &$item) {
            $item['payload'] = json_decode((string) $item['payload'], true) ?: array();
            $item['validation'] = json_decode((string) $item['validation'], true) ?: array();

            $staged_media = isset($item['payload']['media']) && is_array($item['payload']['media'])
                ? $item['payload']['media']
                : array();

            $library_media = WPMB_UIC_Media::find_for_item(
                $item['code'],
                $item['payload'],
                $project_id
            );

            $media = $this->merge_preview_media($staged_media, $library_media);
            $item['preview_media'] = $media;
            $item['live_validation'] = WPMB_UIC_SEO::validate_payload($item['payload'], $media);
        }
        unset($item);

        wp_send_json_success(array(
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'pages' => (int) ceil($total / $per_page),
        ));
    }

    /* ============================================================
       🖼️ MATCH LIBRARY MEDIA
       ============================================================ */
    public function match_library_media() {
        $this->guard('manage_options');
        global $wpdb;
        $db = WPMB_UIC_DB::instance();

        $project_id = absint($_POST['project_id'] ?? 0);
        $item_id = absint($_POST['item_id'] ?? 0);
        $offset = max(0, absint($_POST['offset'] ?? 0));
        $limit = max(1, min(50, absint($_POST['limit'] ?? 20)));

        if (!$project_id || !$db->get_project($project_id)) {
            wp_send_json_error(array('message' => 'ไม่พบโปรเจกต์'), 404);
        }

        if ($item_id) {
            $items = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$db->items} WHERE project_id=%d AND id=%d LIMIT 1",
                $project_id,
                $item_id
            ), ARRAY_A);
            $total = count($items);
        } else {
            $total = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$db->items} WHERE project_id=%d AND status<>'excluded'",
                $project_id
            ));

            $items = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$db->items}
                 WHERE project_id=%d AND status<>'excluded'
                 ORDER BY id ASC LIMIT %d OFFSET %d",
                $project_id,
                $limit,
                $offset
            ), ARRAY_A);
        }

        $summary = array(
            'processed' => 0,
            'items_with_library_media' => 0,
            'complete_items' => 0,
            'missing_slots' => 0,
            'pending_import_files' => 0,
            'library_attachments' => 0,
        );

        $item_results = array();

        foreach ($items as $item) {
            $payload = json_decode((string) $item['payload'], true);
            $payload = is_array($payload) ? $payload : array();

            $staged = isset($payload['media']) && is_array($payload['media']) ? $payload['media'] : array();
            $expected = WPMB_UIC_Media::expected_from_payload($payload);
            $library = WPMB_UIC_Media::find_for_item($item['code'], $payload, $project_id);
            $match = WPMB_UIC_Media::merge_library_preferred($staged, $library, $expected);

            $payload['media'] = $match['media'];
            $payload['media_library_audit'] = $match['audit'];

            $validation = WPMB_UIC_SEO::validate_payload($payload, $payload['media']);

            $status = (string) $item['status'];
            if (!in_array($status, array('done', 'done_warning', 'running', 'excluded'), true)) {
                $status = ($validation['status'] ?? '') === 'error' ? 'warning' : 'ready';
            }

            $audit = $match['audit'];
            $step = !empty($audit['complete'])
                ? 'ค้นหา Media ในเว็บแล้ว: ครบตามชุดต้นทาง'
                : 'ค้นหา Media ในเว็บแล้ว: ขาด ' . (int) $audit['missing_count'] . ' ช่อง';

            $wpdb->update($db->items, array(
                'payload'    => wp_json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'validation' => wp_json_encode($validation, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'status'     => $status,
                'step'       => $step,
                'updated_at' => current_time('mysql'),
            ), array('id' => (int) $item['id']));

            $summary['processed']++;
            $summary['library_attachments'] += (int) ($audit['library_attachments'] ?? 0);
            $summary['missing_slots'] += (int) ($audit['missing_count'] ?? 0);
            $summary['pending_import_files'] += (int) ($audit['pending_import_files'] ?? 0);

            if (!empty($audit['library_attachments'])) {
                $summary['items_with_library_media']++;
            }
            if (!empty($audit['complete'])) {
                $summary['complete_items']++;
            }

            $item_results[] = array(
                'item_id' => (int) $item['id'],
                'code' => $item['code'],
                'audit' => $audit,
            );
        }

        $next_offset = $item_id ? 0 : $offset + count($items);

        wp_send_json_success(array(
            'summary' => $summary,
            'items' => $item_results,
            'offset' => $offset,
            'next_offset' => $next_offset,
            'total' => $total,
            'done' => $item_id ? true : ($next_offset >= $total || count($items) < $limit),
        ));
    }

    /* ============================================================
       💾 SAVE ITEM
       ============================================================ */
    public function save_item() {
        $this->guard('manage_options');
        global $wpdb;
        $db = WPMB_UIC_DB::instance();

        $item_id = absint($_POST['item_id'] ?? 0);
        $item = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$db->items} WHERE id=%d",
            $item_id
        ), ARRAY_A);

        if (!$item) {
            wp_send_json_error(array('message' => 'ไม่พบรายการ'), 404);
        }

        $payload = json_decode((string) $item['payload'], true) ?: array();

        $fields = array(
            'title', 'slug', 'content', 'focus', 'meta_description',
            'actor_en', 'status', 'post_date', 'duration', 'video_url'
        );

        foreach ($fields as $field) {
            if (array_key_exists($field, $_POST)) {
                $value = wp_unslash($_POST[$field]);

                if ($field === 'content') {
                    $payload[$field] = current_user_can('unfiltered_html')
                        ? $value
                        : wp_kses_post($value);
                } elseif ($field === 'video_url') {
                    $payload[$field] = esc_url_raw($value);
                } elseif ($field === 'post_date') {
                    $raw_date = sanitize_text_field($value);
                    $raw_date = str_replace('T', ' ', $raw_date);

                    if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $raw_date)) {
                        $raw_date .= ':00';
                    }

                    $dt = DateTime::createFromFormat('!Y-m-d H:i:s', $raw_date, wp_timezone());
                    $payload[$field] = ($dt instanceof DateTime && $dt->format('Y-m-d H:i:s') === $raw_date)
                        ? $raw_date
                        : '';
                } else {
                    $payload[$field] = sanitize_textarea_field($value);
                }
            }
        }

        if (array_key_exists('target_post_id', $_POST)) {
            $payload['target_post_id'] = absint($_POST['target_post_id']);
        }

        if (isset($_POST['taxonomies'])) {
            $tax = json_decode(wp_unslash($_POST['taxonomies']), true);
            if (is_array($tax)) {
                if (!isset($payload['taxonomies']) || !is_array($payload['taxonomies'])) {
                    $payload['taxonomies'] = array();
                }
                foreach ($tax as $taxonomy => $terms) {
                    $taxonomy = sanitize_key($taxonomy);
                    $payload['taxonomies'][$taxonomy] = array_values(array_filter(
                        array_map('sanitize_text_field', (array) $terms),
                        'strlen'
                    ));
                }
            }
        }

        if (isset($_POST['media'])) {
            $media = json_decode(wp_unslash($_POST['media']), true);
            if (is_array($media)) {
                $clean = array();
                $uploads = wp_upload_dir();
                $allowed_root = empty($uploads['error'])
                    ? wp_normalize_path(trailingslashit($uploads['basedir']) . 'wp-my-boss-imports/')
                    : '';

                foreach ($media as $entry) {
                    if (!is_array($entry)) {
                        continue;
                    }

                    $path = isset($entry['path']) ? wp_normalize_path((string) $entry['path']) : '';

                    if ($path !== ''
                        && ($allowed_root === ''
                            || strpos($path, $allowed_root) !== 0
                            || !is_file($path))
                    ) {
                        $path = '';
                    }

                    $clean[] = array(
                        'attachment_id' => absint($entry['attachment_id'] ?? 0),
                        'media_url'     => esc_url_raw($entry['media_url'] ?? ''),
                        'preview_url'   => esc_url_raw($entry['preview_url'] ?? ''),
                        'file_name'     => sanitize_file_name($entry['file_name'] ?? ''),
                        'file_id'       => absint($entry['file_id'] ?? 0),
                        'path'          => $path,
                        'name'          => sanitize_file_name($entry['name'] ?? ''),
                        'role'          => in_array(($entry['role'] ?? ''), array('cover', 'poster', 'gallery', 'unused'), true)
                            ? $entry['role']
                            : 'gallery',
                        'role_source'   => in_array(($entry['role_source'] ?? ''), array('manual', 'filename', 'hint'), true)
                            ? $entry['role_source']
                            : 'filename',
                        'role_manual_locked' => !empty($entry['role_manual_locked']) ? 1 : 0,
                        'sequence'      => isset($entry['sequence']) ? absint($entry['sequence']) : null,
                        'width'         => absint($entry['width'] ?? 0),
                        'height'        => absint($entry['height'] ?? 0),
                        'orientation'   => in_array(($entry['orientation'] ?? ''), array('landscape', 'portrait', 'square', 'unknown'), true)
                            ? $entry['orientation']
                            : 'unknown',
                        'readable'      => !empty($entry['readable']),
                        'hash'          => sanitize_text_field($entry['hash'] ?? ''),
                        'alt_text'      => sanitize_text_field($entry['alt_text'] ?? ''),
                        'confidence'    => absint($entry['confidence'] ?? 0),
                    );
                }

                $payload['media'] = WPMB_UIC_Code::natural_media_sort($clean);
            }
        }

        $validation = WPMB_UIC_SEO::validate_payload($payload, $payload['media'] ?? array());
        $status = $validation['status'] === 'error' ? 'warning' : 'ready';

        $wpdb->update($db->items, array(
            'payload'    => wp_json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'validation' => wp_json_encode($validation, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'status'     => $status,
            'step'       => 'บันทึก Preview แล้ว',
            'updated_at' => current_time('mysql'),
        ), array('id' => $item_id));

        wp_send_json_success(array(
            'item_id' => $item_id,
            'status' => $status,
            'validation' => $validation,
        ));
    }

    /* ============================================================
       ✅ VALIDATE ITEM
       ============================================================ */
    public function validate_item() {
        $this->guard('manage_options');
        global $wpdb;
        $db = WPMB_UIC_DB::instance();

        $item_id = absint($_POST['item_id'] ?? 0);
        $item = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$db->items} WHERE id=%d",
            $item_id
        ), ARRAY_A);

        if (!$item) {
            wp_send_json_error(array('message' => 'ไม่พบรายการ'), 404);
        }

        $project_id = (int) $item['project_id'];
        $payload = json_decode((string) $item['payload'], true) ?: array();

        $library_media = WPMB_UIC_Media::find_for_item($item['code'], $payload, $project_id);
        $media = $this->merge_preview_media((array) ($payload['media'] ?? array()), $library_media);
        $validation = WPMB_UIC_SEO::validate_payload($payload, $media);

        $wpdb->update($db->items, array(
            'validation' => wp_json_encode($validation, JSON_UNESCAPED_UNICODE),
            'status' => $validation['status'] === 'error' ? 'warning' : 'ready',
        ), array('id' => $item_id));

        wp_send_json_success(array(
            'validation' => $validation,
            'media_found' => count($media),
        ));
    }

    /* ============================================================
       🚀 IMPORT NEXT
       ============================================================ */
    public function import_next() {
        $this->guard('edit_posts');
        $project_id = absint($_POST['project_id'] ?? 0);
        $retry = !empty($_POST['retry_errors']);
        wp_send_json_success(WPMB_UIC_Importer::import_next($project_id, $retry));
    }

    /* ============================================================
       🔄 RESET ERRORS
       ============================================================ */
    public function reset_errors() {
        $this->guard('manage_options');
        global $wpdb;
        $db = WPMB_UIC_DB::instance();

        $project_id = absint($_POST['project_id'] ?? 0);
        $count = $wpdb->query($wpdb->prepare(
            "UPDATE {$db->items} SET status='ready', step='รอทำใหม่', error_text=''
             WHERE project_id=%d AND status='error'",
            $project_id
        ));

        wp_send_json_success(array('reset' => (int) $count));
    }

    /* ============================================================
       ✅ MARK READY
       ============================================================ */
    public function mark_ready() {
        $this->guard('manage_options');
        global $wpdb;
        $db = WPMB_UIC_DB::instance();

        $project_id = absint($_POST['project_id'] ?? 0);
        $count = $wpdb->query($wpdb->prepare(
            "UPDATE {$db->items} SET status='ready', step='พร้อมนำเข้า'
             WHERE project_id=%d AND status IN ('staged','warning')",
            $project_id
        ));

        wp_send_json_success(array(
            'updated' => (int) $count,
            'counts' => WPMB_UIC_Importer::status_counts($project_id),
        ));
    }

    /* ============================================================
       ❌ EXCLUDE ITEM
       ============================================================ */
    public function exclude_item() {
        $this->guard('manage_options');
        global $wpdb;
        $db = WPMB_UIC_DB::instance();

        $item_id = absint($_POST['item_id'] ?? 0);
        $item = $wpdb->get_row($wpdb->prepare(
            "SELECT id,status FROM {$db->items} WHERE id=%d",
            $item_id
        ), ARRAY_A);

        if (!$item) {
            wp_send_json_error(array('message' => 'ไม่พบรายการ'), 404);
        }

        if (in_array((string) $item['status'], array('importing', 'running', 'done', 'done_warning'), true)) {
            wp_send_json_error(array('message' => 'รายการนี้เริ่มนำเข้าหรือเสร็จแล้ว'), 409);
        }

        $ok = $wpdb->update($db->items, array(
            'status' => 'excluded',
            'step' => 'นำออกจากรายการโดยผู้ใช้',
            'error_text' => '',
            'updated_at' => current_time('mysql'),
        ), array('id' => $item_id));

        if (false === $ok) {
            wp_send_json_error(array('message' => 'บันทึกสถานะไม่สำเร็จ'), 500);
        }

        wp_send_json_success(array('item_id' => $item_id, 'status' => 'excluded'));
    }

    /* ============================================================
       ♻️ RESTORE ITEM
       ============================================================ */
    public function restore_item() {
        $this->guard('manage_options');
        global $wpdb;
        $db = WPMB_UIC_DB::instance();

        $item_id = absint($_POST['item_id'] ?? 0);
        $item = $wpdb->get_row($wpdb->prepare(
            "SELECT id,payload FROM {$db->items} WHERE id=%d AND status='excluded'",
            $item_id
        ), ARRAY_A);

        if (!$item) {
            wp_send_json_error(array('message' => 'ไม่พบรายการที่นำออกไว้'), 404);
        }

        $payload = json_decode((string) $item['payload'], true) ?: array();
        $validation = WPMB_UIC_SEO::validate_payload($payload, $payload['media'] ?? array());
        $status = ($validation['status'] ?? '') === 'error' ? 'warning' : 'ready';

        $wpdb->update($db->items, array(
            'status' => $status,
            'step' => $status === 'ready' ? 'พร้อมนำเข้า' : 'กลับมาตรวจใหม่',
            'validation' => wp_json_encode($validation, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'updated_at' => current_time('mysql'),
        ), array('id' => $item_id));

        wp_send_json_success(array('item_id' => $item_id, 'status' => $status));
    }

    /* ============================================================
       📊 BULK ITEM STATUS
       ============================================================ */
    public function bulk_item_status() {
        $this->guard('manage_options');
        global $wpdb;
        $db = WPMB_UIC_DB::instance();

        $project_id = absint($_POST['project_id'] ?? 0);
        $decision = sanitize_key($_POST['decision'] ?? '');
        $ids = isset($_POST['ids']) ? json_decode(wp_unslash($_POST['ids']), true) : array();
        $ids = is_array($ids)
            ? array_values(array_unique(array_filter(array_map('absint', $ids))))
            : array();

        if (!$project_id || !$ids || !in_array($decision, array('keep', 'exclude'), true)) {
            wp_send_json_error(array('message' => 'ข้อมูลการเลือกไม่ครบ'), 400);
        }

        $placeholders = implode(',', array_fill(0, count($ids), '%d'));
        $args = array_merge(array($project_id), $ids);

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id,status,payload FROM {$db->items}
             WHERE project_id=%d AND id IN ({$placeholders})",
            $args
        ), ARRAY_A);

        $updated = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            if (in_array((string) $row['status'], array('running', 'done', 'done_warning'), true)) {
                $skipped++;
                continue;
            }

            if ($decision === 'exclude') {
                $ok = $wpdb->update($db->items, array(
                    'status' => 'excluded',
                    'step' => 'นำออกจากรายการโดยผู้ใช้',
                    'error_text' => '',
                    'updated_at' => current_time('mysql'),
                ), array('id' => (int) $row['id']));
            } else {
                $payload = json_decode((string) $row['payload'], true) ?: array();
                $validation = WPMB_UIC_SEO::validate_payload($payload, $payload['media'] ?? array());
                $status = ($validation['status'] ?? '') === 'error' ? 'warning' : 'ready';

                $ok = $wpdb->update($db->items, array(
                    'status' => $status,
                    'step' => $status === 'ready' ? 'เลือกนำเข้าแล้ว' : 'เลือกไว้แต่ยังมีคำเตือน',
                    'validation' => wp_json_encode($validation, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'error_text' => '',
                    'updated_at' => current_time('mysql'),
                ), array('id' => (int) $row['id']));
            }

            if ($ok === false) {
                $skipped++;
            } else {
                $updated++;
            }
        }

        wp_send_json_success(array(
            'updated' => $updated,
            'skipped' => $skipped,
            'counts' => WPMB_UIC_Importer::status_counts($project_id),
        ));
    }

    /* ============================================================
       🖼️ BULK FILL ALT
       ============================================================ */
    public function bulk_fill_alt() {
        $this->guard('manage_options');
        global $wpdb;
        $db = WPMB_UIC_DB::instance();

        $project_id = absint($_POST['project_id'] ?? 0);
        $overwrite = !empty($_POST['overwrite']);
        $ids = isset($_POST['ids']) ? json_decode(wp_unslash($_POST['ids']), true) : array();
        $ids = is_array($ids)
            ? array_values(array_unique(array_filter(array_map('absint', $ids))))
            : array();

        if (!$project_id) {
            wp_send_json_error(array('message' => 'ไม่พบโปรเจกต์'), 400);
        }

        $where = "project_id=%d AND status IN ('staged','warning','ready','error')";
        $args = array($project_id);

        if ($ids) {
            $where .= ' AND id IN (' . implode(',', array_fill(0, count($ids), '%d')) . ')';
            $args = array_merge($args, $ids);
        }

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id,code,status,payload FROM {$db->items} WHERE {$where} ORDER BY id ASC",
            $args
        ), ARRAY_A);

        $updated_items = 0;
        $updated_media = 0;

        foreach ($rows as $row) {
            $payload = json_decode((string) $row['payload'], true) ?: array('code' => $row['code']);
            $library_media = WPMB_UIC_Media::find_for_item($row['code'], $payload, $project_id);
            $media = $this->merge_preview_media((array) ($payload['media'] ?? array()), $library_media);

            if (!$media) {
                continue;
            }

            $before = wp_json_encode($media);
            $media = WPMB_UIC_SEO::fill_alt(
                $media,
                $row['code'],
                isset($payload['actor_en']) ? $payload['actor_en'] : '',
                isset($payload['focus']) ? $payload['focus'] : '',
                $overwrite,
                isset($payload['title']) ? $payload['title'] : ''
            );
            $payload['media'] = $media;

            $validation = WPMB_UIC_SEO::validate_payload($payload, $media);
            $status = $row['status'] === 'error'
                ? 'error'
                : (($validation['status'] ?? '') === 'error' ? 'warning' : 'ready');

            $wpdb->update($db->items, array(
                'payload' => wp_json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'validation' => wp_json_encode($validation, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'status' => $status,
                'step' => $overwrite ? 'เขียน ALT ทุกภาพแล้ว' : 'เติม ALT ที่ว่างแล้ว',
                'updated_at' => current_time('mysql'),
            ), array('id' => (int) $row['id']));

            if ($before !== wp_json_encode($media)) {
                $updated_media += count($media);
            }
            $updated_items++;
        }

        wp_send_json_success(array(
            'items' => $updated_items,
            'media' => $updated_media,
            'overwrite' => $overwrite ? 1 : 0,
        ));
    }

    /* ============================================================
       ⚙️ APPLY FIELD RULES
       ============================================================ */
    public function apply_field_rules() {
        $this->guard('manage_options');
        global $wpdb;
        $db = WPMB_UIC_DB::instance();

        $project_id = absint($_POST['project_id'] ?? 0);

        if (!$project_id) {
            wp_send_json_error(array('message' => 'ไม่พบโปรเจกต์'), 400);
        }

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id,status,payload FROM {$db->items}
             WHERE project_id=%d AND status IN ('staged','warning','ready','error')
             ORDER BY id ASC",
            $project_id
        ), ARRAY_A);

        $updated = 0;

        foreach ($rows as $row) {
            $payload = json_decode((string) $row['payload'], true) ?: array();
            $post_id = WPMB_UIC_Importer::find_existing_post($payload);
            $payload = WPMB_UIC_Field_Rules::prepare_payload($payload, $post_id);

            $validation = WPMB_UIC_SEO::validate_payload($payload, $payload['media'] ?? array());
            $status = $row['status'] === 'error'
                ? 'error'
                : (($validation['status'] ?? '') === 'error' ? 'warning' : 'ready');

            $wpdb->update($db->items, array(
                'payload' => wp_json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'validation' => wp_json_encode($validation, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'status' => $status,
                'step' => 'ใช้กฎหัวข้อมูล/แหล่งข้อมูลแล้ว',
                'updated_at' => current_time('mysql'),
            ), array('id' => (int) $row['id']));

            $updated++;
        }

        wp_send_json_success(array(
            'updated' => $updated,
            'rules' => WPMB_UIC_Field_Rules::settings(),
        ));
    }

    /* ============================================================
       🔍 SCAN SITE
       ============================================================ */
    public function scan_site() {
        $this->guard('manage_options');

        $stage = sanitize_key($_POST['stage'] ?? 'posts');
        $offset = absint($_POST['offset'] ?? 0);
        $limit = max(10, min(500, absint($_POST['limit'] ?? 100)));

        if ($stage === 'posts') {
            $result = WPMB_UIC_Importer::scan_posts_batch($offset, $limit);
            if ($result['done']) {
                $result['next_stage'] = 'media';
                $result['next_offset'] = 0;
            }
        } elseif ($stage === 'media') {
            $result = WPMB_UIC_Media::scan_media_batch($offset, $limit);
            if ($result['done']) {
                $result['next_stage'] = 'terms';
                $result['next_offset'] = 0;
            }
        } else {
            $result = WPMB_UIC_Taxonomy::scan_terms();
            $result['done'] = true;
            $result['all_done'] = true;
            $result['next_stage'] = '';
        }

        wp_send_json_success($result);
    }

    /* ============================================================
       🧹 SCAN UNUSED
       ============================================================ */
    public function scan_unused() {
        $this->guard('delete_posts');

        $limit = max(1, min(5000, absint($_POST['limit'] ?? 500)));
        $ignore = !isset($_POST['ignore_banners']) || !empty($_POST['ignore_banners']);
        $offset = absint($_POST['refresh_offset'] ?? 0);

        $scan = WPMB_UIC_Media::scan_media_batch($offset, 100);

        if (!$scan['done']) {
            wp_send_json_success(array(
                'phase' => 'scan',
                'scan' => $scan,
                'items' => array(),
            ));
        }

        wp_send_json_success(array(
            'phase' => 'results',
            'scan' => $scan,
            'items' => WPMB_UIC_Media::unused_candidates($limit, $ignore),
        ));
    }

    /* ============================================================
       🗑️ TRASH UNUSED
       ============================================================ */
    public function trash_unused() {
        $this->guard('delete_posts');

        $ids = isset($_POST['ids']) ? json_decode(wp_unslash($_POST['ids']), true) : array();
        if (!is_array($ids)) {
            $ids = array();
        }

        $ignore = !isset($_POST['ignore_banners']) || !empty($_POST['ignore_banners']);

        wp_send_json_success(WPMB_UIC_Media::trash_unused($ids, $ignore));
    }

    /* ============================================================
       🔍 TAXONOMY DUPLICATES
       ============================================================ */
    public function taxonomy_duplicates() {
        $this->guard('manage_categories');
        wp_send_json_success(array(
            'groups' => WPMB_UIC_Taxonomy::duplicate_groups(),
        ));
    }

    /* ============================================================
       🔗 TAXONOMY MERGE
       ============================================================ */
    public function taxonomy_merge() {
        $this->guard('manage_categories');

        $taxonomy = sanitize_key($_POST['taxonomy'] ?? '');
        $ids = isset($_POST['term_ids']) ? json_decode(wp_unslash($_POST['term_ids']), true) : array();
        $master = absint($_POST['master_term_id'] ?? 0);

        $result = WPMB_UIC_Taxonomy::merge_group(
            $taxonomy,
            is_array($ids) ? $ids : array(),
            $master
        );

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()), 400);
        }

        wp_send_json_success($result);
    }

    /* ============================================================
       🚫 TAXONOMY AUTO INDEX — ปิดถาวร
       ============================================================ */
    public function taxonomy_auto_index() {
        $this->guard('manage_categories');
        wp_send_json_success(WPMB_UIC_Taxonomy::run_auto_index_all());
    }

    /* ============================================================
       💾 SAVE SETTINGS
       ============================================================ */
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
            $field_source[$field] = array_key_exists($source, $definition['sources'])
                ? $source
                : 'import';

            $lock = sanitize_key($_POST['field_lock_' . $field] ?? 'overwrite');
            $field_lock[$field] = array_key_exists($lock, $lock_options)
                ? $lock
                : 'overwrite';
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

            // 🚫 v1.0.15.3: ปิด Index/Noindex ถาวร — บังคับ 0 เสมอ
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

    /* ============================================================
       🔄 CURRENT STATUS
       ============================================================ */
    public function current_status() {
        $this->guard('edit_posts');
        global $wpdb;
        $db = WPMB_UIC_DB::instance();

        $project_id = absint($_POST['project_id'] ?? 0);

        $item = $wpdb->get_row($wpdb->prepare(
            "SELECT id,code,status,step,error_text,updated_at FROM {$db->items}
             WHERE project_id=%d AND status='running'
             ORDER BY updated_at DESC LIMIT 1",
            $project_id
        ), ARRAY_A);

        wp_send_json_success(array(
            'item' => $item ?: null,
            'counts' => WPMB_UIC_Importer::status_counts($project_id),
        ));
    }

    /* ============================================================
       📊 PROJECT SUMMARY
       ============================================================ */
    public function project_summary() {
        $this->guard('manage_options');
        $project_id = absint($_POST['project_id'] ?? 0);
        wp_send_json_success($this->summary_data($project_id));
    }

    /* ============================================================
       🗑️ DELETE STAGED FILE
       ============================================================ */
    public function delete_staged_file() {
        $this->guard('manage_options');
        global $wpdb;
        $db = WPMB_UIC_DB::instance();

        $file_id = absint($_POST['file_id'] ?? 0);
        $file = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$db->files} WHERE id=%d",
            $file_id
        ), ARRAY_A);

        if (!$file) {
            wp_send_json_error(array('message' => 'ไม่พบไฟล์'), 404);
        }

        $path = (string) $file['stored_path'];
        if ($path && is_file($path)) {
            @unlink($path);
        }

        $wpdb->delete($db->files, array('id' => $file_id));

        wp_send_json_success(array('deleted' => $file_id));
    }

    /* ============================================================
       🧹 CLEAR PROJECT
       ============================================================ */
    public function clear_project() {
        $this->guard('manage_options');

        $project_id = absint($_POST['project_id'] ?? 0);

        if (!$project_id) {
            wp_send_json_error(array('message' => 'ไม่พบหมายเลขโปรเจกต์'), 400);
        }

        $result = WPMB_UIC_DB::instance()->clear_project_data($project_id);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()), 409);
        }

        wp_send_json_success($result);
    }

    /* ============================================================
       🗑️ DELETE PROJECT
       ============================================================ */
    public function delete_project() {
        $this->guard('manage_options');

        $project_id = absint($_POST['project_id'] ?? 0);

        if (!$project_id) {
            wp_send_json_error(array('message' => 'ไม่พบหมายเลขโปรเจกต์'), 400);
        }

        $result = WPMB_UIC_DB::instance()->delete_project($project_id);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()), 409);
        }

        $result['redirect'] = admin_url('admin.php?page=wpmb-uic');
        wp_send_json_success($result);
    }

    /* ============================================================
       📊 SUMMARY DATA (Helper)
       ============================================================ */
    private function summary_data($project_id) {
        global $wpdb;
        $db = WPMB_UIC_DB::instance();

        $counts = WPMB_UIC_Importer::status_counts($project_id);

        $files = $wpdb->get_results($wpdb->prepare(
            "SELECT id,original_name,file_type,size_bytes,status,analysis,created_at
             FROM {$db->files}
             WHERE project_id=%d AND parent_file_id=0
             ORDER BY id ASC",
            $project_id
        ), ARRAY_A);

        foreach ($files as &$file) {
            $file['analysis'] = json_decode((string) $file['analysis'], true) ?: array();
        }
        unset($file);

        return array(
            'project' => $db->get_project($project_id),
            'counts' => $counts,
            'files' => $files,
        );
    }

    /* ============================================================
       📁 NORMALIZE FILES ARRAY
       ============================================================ */
    private function normalize_files_array(array $files) {
        if (!is_array($files['name'])) {
            return array($files);
        }

        $out = array();
        foreach ($files['name'] as $i => $name) {
            $out[] = array(
                'name' => $name,
                'type' => $files['type'][$i] ?? '',
                'tmp_name' => $files['tmp_name'][$i] ?? '',
                'error' => $files['error'][$i] ?? UPLOAD_ERR_NO_FILE,
                'size' => $files['size'][$i] ?? 0,
            );
        }
        return $out;
    }

    /* ============================================================
       ⚠️ UPLOAD ERROR MESSAGE
       ============================================================ */
    private function upload_error_message($code) {
        $map = array(
            UPLOAD_ERR_INI_SIZE => 'ไฟล์ใหญ่เกิน upload_max_filesize',
            UPLOAD_ERR_FORM_SIZE => 'ไฟล์ใหญ่เกินค่าที่ฟอร์มกำหนด',
            UPLOAD_ERR_PARTIAL => 'อัปโหลดมาไม่ครบ',
            UPLOAD_ERR_NO_FILE => 'ไม่พบไฟล์',
            UPLOAD_ERR_NO_TMP_DIR => 'เซิร์ฟเวอร์ไม่มีโฟลเดอร์ชั่วคราว',
            UPLOAD_ERR_CANT_WRITE => 'เซิร์ฟเวอร์เขียนไฟล์ไม่ได้',
            UPLOAD_ERR_EXTENSION => 'ส่วนขยาย PHP หยุดการอัปโหลด',
        );
        return $map[$code] ?? ('Upload error ' . $code);
    }

    /* ============================================================
       🔀 MERGE PREVIEW MEDIA (Helper)
       ============================================================ */
    private function merge_preview_media(array $primary, array $secondary) {
        $matched = WPMB_UIC_Media::merge_library_preferred($primary, $secondary, array());
        return $matched['media'];
    }

    /* ============================================================
       🎯 META SCANNER — SCAN
       ============================================================ */
    public function meta_scan() {
        $this->guard('manage_options');

        $result = WPMB_UIC_Meta_Scanner::detect_field_map();

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()), 500);
        }

        $result['saved_mapping'] = WPMB_UIC_Meta_Scanner::get_mapping();
        $result['labels'] = WPMB_UIC_Meta_Scanner::field_labels();

        wp_send_json_success($result);
    }

    /* ============================================================
       💾 META SCANNER — SAVE
       ============================================================ */
    public function meta_save() {
        $this->guard('manage_options');

        $mapping = isset($_POST['mapping']) && is_array($_POST['mapping'])
            ? wp_unslash($_POST['mapping'])
            : array();

        $clean = WPMB_UIC_Meta_Scanner::save_mapping($mapping);

        wp_send_json_success(array(
            'saved' => $clean,
            'message' => 'บันทึก Meta Key Mapping เรียบร้อย',
        ));
    }

    /* ============================================================
       🔄 META SCANNER — RESET
       ============================================================ */
    public function meta_reset() {
        $this->guard('manage_options');

        WPMB_UIC_Meta_Scanner::reset_mapping();

        wp_send_json_success(array(
            'message' => 'รีเซ็ตเป็นค่าเริ่มต้นแล้ว',
            'mapping' => WPMB_UIC_Meta_Scanner::get_mapping(),
        ));
    }

    /* ============================================================
       🔍 GET TERMS BROWSE
       ============================================================ */
    public function get_terms_browse() {
        $this->guard('manage_options');

        $taxonomy = isset($_POST['taxonomy']) ? sanitize_key($_POST['taxonomy']) : 'category';
        $taxonomy = WPMB_UIC_Taxonomy::actual_taxonomy($taxonomy);

        if (!taxonomy_exists($taxonomy)) {
            wp_send_json_error(array('message' => 'ไม่พบ taxonomy: ' . $taxonomy), 400);
        }

        $terms = get_terms(array(
            'taxonomy' => $taxonomy,
            'hide_empty' => false,
            'number' => 0,
            'orderby' => 'count',
            'order' => 'DESC',
        ));

        if (is_wp_error($terms)) {
            wp_send_json_error(array('message' => $terms->get_error_message()), 500);
        }

        $out = array();
        foreach ($terms as $term) {
            $out[] = array(
                'term_id' => (int) $term->term_id,
                'name' => $term->name,
                'slug' => $term->slug,
                'count' => (int) $term->count,
            );
        }

        wp_send_json_success(array(
            'taxonomy' => $taxonomy,
            'terms' => $out,
            'total' => count($out),
        ));
    }

    /* ============================================================
       🎯 v1.0.15.3: GET ITEM PREVIEW
       ============================================================
       โหลดข้อมูล Preview สำหรับ Modal
       ============================================================ */
    public function get_item_preview() {
        $this->guard('manage_options');
        global $wpdb;
        $db = WPMB_UIC_DB::instance();

        $item_id = absint($_POST['item_id'] ?? 0);
        if (!$item_id) {
            wp_send_json_error(array('message' => 'ไม่พบรหัสรายการ'), 400);
        }

        $item = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$db->items} WHERE id=%d",
            $item_id
        ), ARRAY_A);

        if (!$item) {
            wp_send_json_error(array('message' => 'ไม่พบรายการ'), 404);
        }

        $payload = json_decode((string) $item['payload'], true);
        $payload = is_array($payload) ? $payload : array();

        $html = $this->build_preview_html($item, $payload);

        wp_send_json_success(array(
            'html' => $html,
            'code' => $item['code'],
            'status' => $item['status'],
        ));
    }

    /* ============================================================
       🎯 v1.0.15.3: GET ITEM EDIT FORM
       ============================================================
       โหลดฟอร์มแก้ไขสำหรับ Modal
       ============================================================ */
    public function get_item_edit_form() {
        $this->guard('manage_options');
        global $wpdb;
        $db = WPMB_UIC_DB::instance();

        $item_id = absint($_POST['item_id'] ?? 0);
        if (!$item_id) {
            wp_send_json_error(array('message' => 'ไม่พบรหัสรายการ'), 400);
        }

        $item = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$db->items} WHERE id=%d",
            $item_id
        ), ARRAY_A);

        if (!$item) {
            wp_send_json_error(array('message' => 'ไม่พบรายการ'), 404);
        }

        $payload = json_decode((string) $item['payload'], true);
        $payload = is_array($payload) ? $payload : array();

        $html = $this->build_edit_form_html($item, $payload);

        wp_send_json_success(array(
            'html' => $html,
            'code' => $item['code'],
        ));
    }

    /* ============================================================
       🎯 BUILD PREVIEW HTML (Helper)
       ============================================================ */
    private function build_preview_html(array $item, array $payload) {
        $code = esc_html($item['code']);
        $title = esc_html($payload['title'] ?? '—');
        $slug = esc_html($payload['slug'] ?? '—');
        $duration = isset($payload['duration']) ? (int) $payload['duration'] : 0;
        $video_url = esc_url($payload['video_url'] ?? '');
        $focus = esc_html($payload['focus'] ?? '—');
        $meta_desc = esc_html($payload['meta_description'] ?? '—');
        $actor_en = esc_html($payload['actor_en'] ?? '—');

        $tax = isset($payload['taxonomies']) && is_array($payload['taxonomies'])
            ? $payload['taxonomies']
            : array();

        $categories = isset($tax['category'])
            ? implode(', ', array_map('esc_html', (array) $tax['category']))
            : '—';

        $tags = isset($tax['post_tag'])
            ? implode(', ', array_map('esc_html', (array) $tax['post_tag']))
            : '—';

        $actors = isset($tax['actors'])
            ? implode(', ', array_map('esc_html', (array) $tax['actors']))
            : '—';

        $studio = isset($tax['studio'])
            ? implode(', ', array_map('esc_html', (array) $tax['studio']))
            : '—';

        $duration_min = $duration > 0 ? number_format($duration / 60, 1) : '0';

        ob_start();
        ?>
        <div class="wpmb-section">
            <h4 class="wpmb-section-title">📝 ชื่อเรื่อง</h4>
            <div class="wpmb-section-content">
                <p><strong><?php echo $title; ?></strong></p>
            </div>
        </div>

        <div class="wpmb-section">
            <h4 class="wpmb-section-title">🔗 Slug</h4>
            <div class="wpmb-section-content">
                <p><code><?php echo $slug; ?></code></p>
            </div>
        </div>

        <div class="wpmb-section">
            <h4 class="wpmb-section-title">🎬 ข้อมูลวิดีโอ</h4>
            <div class="wpmb-section-content">
                <p><strong>ระยะเวลา:</strong> <?php echo number_format($duration); ?> วินาที (<?php echo $duration_min; ?> นาที)</p>
                <?php if ($video_url) : ?>
                    <p><strong>ลิงก์วิดีโอ:</strong> <code><?php echo $video_url; ?></code></p>
                <?php else : ?>
                    <p><strong>ลิงก์วิดีโอ:</strong> <em style="color:#999;">ยังไม่มี</em></p>
                <?php endif; ?>
            </div>
        </div>

        <div class="wpmb-section">
            <h4 class="wpmb-section-title">📂 หมวดหมู่และแท็ก</h4>
            <div class="wpmb-section-content">
                <p><strong>หมวดหมู่:</strong> <?php echo $categories; ?></p>
                <p><strong>แท็ก:</strong> <?php echo $tags; ?></p>
                <p><strong>นักแสดง:</strong> <?php echo $actors; ?></p>
                <p><strong>ค่าย:</strong> <?php echo $studio; ?></p>
                <p><strong>Actor EN:</strong> <?php echo $actor_en; ?></p>
            </div>
        </div>

        <div class="wpmb-section">
            <h4 class="wpmb-section-title">🎯 SEO (Yoast)</h4>
            <div class="wpmb-section-content">
                <p><strong>Focus Keyphrase:</strong> <?php echo $focus; ?></p>
                <p><strong>Meta Description:</strong> <?php echo $meta_desc; ?></p>
            </div>
        </div>

        <div class="wpmb-section">
            <h4 class="wpmb-section-title">📊 สถานะ</h4>
            <div class="wpmb-section-content">
                <p><strong>สถานะ:</strong> <?php echo esc_html($item['status']); ?></p>
                <p><strong>ขั้นตอน:</strong> <?php echo esc_html($item['step']); ?></p>
                <?php if (!empty($item['error_text'])) : ?>
                    <p style="color:#d63638;"><strong>ข้อผิดพลาด:</strong> <?php echo esc_html($item['error_text']); ?></p>
                <?php endif; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /* ============================================================
       🎯 BUILD EDIT FORM HTML (Helper)
       ============================================================ */
    private function build_edit_form_html(array $item, array $payload) {
        $title = esc_attr($payload['title'] ?? '');
        $slug = esc_attr($payload['slug'] ?? '');
        $duration = esc_attr($payload['duration'] ?? '');
        $video_url = esc_attr($payload['video_url'] ?? '');
        $focus = esc_attr($payload['focus'] ?? '');
        $meta_desc = esc_textarea($payload['meta_description'] ?? '');
        $actor_en = esc_attr($payload['actor_en'] ?? '');

        ob_start();
        ?>
        <div class="wpmb-field">
            <label>📝 ชื่อเรื่อง</label>
            <input type="text" name="title" value="<?php echo $title; ?>">
        </div>

        <div class="wpmb-field">
            <label>🔗 Slug</label>
            <input type="text" name="slug" value="<?php echo $slug; ?>">
        </div>

        <div class="wpmb-field">
            <label>🎬 ลิงก์วิดีโอ (Video URL)</label>
            <input type="url" name="video_url" value="<?php echo $video_url; ?>" placeholder="https://...">
        </div>

        <div class="wpmb-field">
            <label>⏱️ ระยะเวลา (วินาที)</label>
            <input type="number" name="duration" value="<?php echo $duration; ?>" min="0">
        </div>

        <div class="wpmb-field">
            <label>👤 Actor EN (ใช้สำหรับ ALT รูป)</label>
            <input type="text" name="actor_en" value="<?php echo $actor_en; ?>">
        </div>

        <div class="wpmb-field">
            <label>🎯 Focus Keyphrase (Yoast)</label>
            <input type="text" name="focus" value="<?php echo $focus; ?>">
        </div>

        <div class="wpmb-field">
            <label>📄 Meta Description (Yoast)</label>
            <textarea name="meta_description"><?php echo $meta_desc; ?></textarea>
        </div>

        <input type="hidden" name="item_id" value="<?php echo (int) $item['id']; ?>">
        <?php
        return ob_get_clean();
    }
}