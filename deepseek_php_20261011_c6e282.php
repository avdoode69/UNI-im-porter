<?php
/**
 * ═══════════════════════════════════════════════════════════════
 * 📜 นโยบาย Import ของ UNI-IM-PORT v1.0.15.3
 * ═══════════════════════════════════════════════════════════════
 *
 * ปลั๊กอินนี้เป็น "ผู้ส่งข้อมูล" ไม่ใช่ "ผู้สร้าง" ของเว็บไซต์
 *
 * หลักการ Import:
 * 1. ตรวจ CODE + Post เดิมก่อนสร้างเสมอ (5-Step Find)
 * 2. ถ้าพบโพสต์เดิม → อัปเดต ไม่สร้างใหม่
 * 3. ถ้าไม่พบ → สร้างใหม่
 * 4. Meta Key: เขียนเฉพาะที่ผู้ใช้ Lock ไว้ (STRICT MODE)
 * 5. ไม่เขียน Robots / Index / Noindex
 * 6. ไม่ทับข้อมูลที่มีอยู่ ถ้า protect_theme_meta เปิด
 * 7. เขียน _wpmb_code ทุกครั้ง (กุญแจสำคัญ)
 *
 * 🎯 Find Existing Post (5 ชั้น):
 *    STEP 1: target_post_id (ผู้ใช้ระบุ)
 *    STEP 2: _wpmb_code (Plugin เอง — แม่นสุด)
 *    STEP 3: _acs_code + meta key อื่นๆ (Legacy)
 *    STEP 4: Slug (5 รูปแบบ)
 *    STEP 5: Title (Strict + Middle)
 *
 * 🚫 ปิดระบบ Poster ถาวรตั้งแต่ v1.0.15.3
 *
 * ═══════════════════════════════════════════════════════════════
 */

if (!defined('ABSPATH')) {
    exit;
}

final class WPMB_UIC_Importer {

    private static $instance;

    public static function instance() {
        if (!self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {}

    /* ============================================================
       🚀 IMPORT NEXT — ประมวลผลทีละรายการ
       ============================================================ */
    public static function import_next($project_id, $retry_errors = false) {
        global $wpdb;
        $db = WPMB_UIC_DB::instance();

        // 🛡️ กู้คืนรายการค้าง (status=running เกิน 5 นาที)
        $stuck_cutoff = function_exists('get_date_from_gmt')
            ? get_date_from_gmt(gmdate('Y-m-d H:i:s', time() - 300), 'Y-m-d H:i:s')
            : date('Y-m-d H:i:s', time() - 300);

        $wpdb->query($wpdb->prepare(
            "UPDATE {$db->items}
             SET status='error',
                 step='ข้ามอัตโนมัติ (ค้างเกิน 5 นาที)',
                 error_text='ค้างเกิน 5 นาที — ระบบข้ามอัตโนมัติ สามารถกด ทำใหม่เฉพาะ Error ได้'
             WHERE project_id=%d
               AND status='running'
               AND updated_at < %s",
            $project_id,
            $stuck_cutoff
        ));

        // 🛡️ จำกัดเวลา 90 วินาทีต่อเรื่อง
        @set_time_limit(90);

        $statuses = $retry_errors
            ? array('error')
            : array('ready', 'staged', 'warning');

        $placeholders = implode(',', array_fill(0, count($statuses), '%s'));
        $args = array_merge(array((int) $project_id), $statuses);

        $query = $wpdb->prepare(
            "SELECT * FROM {$db->items}
             WHERE project_id=%d
               AND status IN ({$placeholders})
             ORDER BY id ASC
             LIMIT 1",
            $args
        );

        $item = $wpdb->get_row($query, ARRAY_A);

        if (!$item) {
            $counts = self::status_counts($project_id);
            $project_status = !empty($counts['error'])
                ? 'completed_with_errors'
                : 'completed';
            $db->update_project($project_id, array(
                'status' => $project_status,
                'stats'  => $counts,
            ));
            return array(
                'done'    => true,
                'counts'  => $counts,
                'message' => 'ไม่มีรายการค้างในคิว',
            );
        }

        $item_id = (int) $item['id'];
        $payload = json_decode((string) $item['payload'], true);

        if (!is_array($payload)) {
            $payload = array('code' => $item['code']);
        }

        $wpdb->update($db->items, array(
            'status'     => 'running',
            'step'       => 'เริ่มตรวจรายการ',
            'attempts'   => (int) $item['attempts'] + 1,
            'error_text' => '',
            'updated_at' => current_time('mysql'),
        ), array('id' => $item_id));

        $db->log($project_id, $item_id, 'info', 'import_start', 'เริ่มนำเข้า ' . $item['code']);

        try {
            $result = self::import_item($project_id, $item_id, $payload);
            $validation = WPMB_UIC_SEO::validate_payload($result['payload'], $result['media']);
            $status = $validation['status'] === 'error' ? 'done_warning' : 'done';

            $wpdb->update($db->items, array(
                'payload'    => wp_json_encode($result['payload'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'validation' => wp_json_encode($validation, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'status'     => $status,
                'step'       => $status === 'done' ? 'เสร็จสมบูรณ์' : 'เสร็จพร้อมคำเตือน',
                'wp_post_id' => (int) $result['post_id'],
                'updated_at' => current_time('mysql'),
            ), array('id' => $item_id));

            $db->log($project_id, $item_id, 'success', 'import_done', 'นำเข้าสำเร็จ', $result);

            return array(
                'done'       => false,
                'item_id'    => $item_id,
                'code'       => $item['code'],
                'post_id'    => (int) $result['post_id'],
                'status'     => $status,
                'steps'      => $result['steps'],
                'meta_log'   => isset($result['meta_log']) ? $result['meta_log'] : array(),
                'validation' => $validation,
                'counts'     => self::status_counts($project_id),
            );

        } catch (Throwable $e) {
            $wpdb->update($db->items, array(
                'status'     => 'error',
                'step'       => 'ผิดพลาด',
                'error_text' => $e->getMessage(),
                'updated_at' => current_time('mysql'),
            ), array('id' => $item_id));

            $db->log($project_id, $item_id, 'error', 'import_error', $e->getMessage());

            return array(
                'done'    => false,
                'item_id' => $item_id,
                'code'    => $item['code'],
                'status'  => 'error',
                'error'   => $e->getMessage(),
                'counts'  => self::status_counts($project_id),
            );
        }
    }

    /* ============================================================
       📝 IMPORT ITEM — นำเข้ารายการเดียว
       ============================================================ */
    public static function import_item($project_id, $item_id, array $payload) {
        $steps = array();

        $code = WPMB_UIC_Code::normalize_code(isset($payload['code']) ? $payload['code'] : '');
        if ($code === '') {
            throw new RuntimeException('ไม่พบ CODE');
        }
        $payload['code'] = $code;

        // ─── STEP 1: ตรวจ CODE ───
        self::set_step($item_id, 'ตรวจ CODE และค้นโพสต์เดิม');
        $steps[] = array('name' => 'ตรวจ CODE', 'status' => 'done');

        $project = WPMB_UIC_DB::instance()->get_project($project_id);
        $project_mode = $project && !empty($project['mode']) ? $project['mode'] : 'mixed';

        // 🎯 5-Step Find Existing Post
        $post_id = self::find_existing_post($payload);
        $existing_post_id = (int) $post_id;
        $is_new = !$post_id;

        if ($project_mode === 'update' && !$post_id) {
            throw new RuntimeException('โหมดอัปเดตเท่านั้น แต่ไม่พบโพสต์เดิมสำหรับ ' . $code);
        }

        $payload = WPMB_UIC_Field_Rules::prepare_payload($payload, $existing_post_id);
        $payload['code'] = $code;

        // 📝 Audit Log — บันทึกการตัดสินใจ
        WPMB_UIC_DB::instance()->log($project_id, $item_id, 'info', 'import_decision',
            $is_new ? 'สร้างโพสต์ใหม่ (Create new)' : 'พบโพสต์เดิม → อัปเดต (Respect existing)',
            array(
                'code'              => $code,
                'is_new'            => $is_new ? 1 : 0,
                'existing_post_id'  => $existing_post_id,
                'authority'         => $is_new ? 'none' : 'existing_wp_post',
                'code_meta_written' => '_wpmb_code',
            )
        );

        // ─── STEP 2: ค้นหา Media ───
        self::set_step($item_id, 'ค้นหา Media เดิมและนำเข้าเฉพาะรูปที่ขาด');

        $lookup_payload = $payload;
        if ($existing_post_id) {
            $lookup_payload['target_post_id'] = $existing_post_id;
        }

        $existing_media = WPMB_UIC_Media::find_for_item($code, $lookup_payload, $project_id);
        $staged_media = isset($payload['media']) && is_array($payload['media'])
            ? $payload['media']
            : array();
        $expected_media = WPMB_UIC_Media::expected_from_payload($payload);

        $matched_media = WPMB_UIC_Media::merge_library_preferred(
            $staged_media,
            $existing_media,
            $expected_media
        );

        $payload['media'] = $matched_media['media'];
        $payload['media_library_audit'] = $matched_media['audit'];

        $actor_en = self::resolve_actor_en($payload);
        $payload['actor_en'] = $actor_en;
        $focus = isset($payload['focus']) ? trim((string) $payload['focus']) : '';

        // Upload เฉพาะรูปที่ยังขาด
        $media = $payload['media']
            ? WPMB_UIC_Media::upload_staged_media($payload['media'], $code, $actor_en, $focus)
            : array();

        $media = WPMB_UIC_Media::normalize_roles_and_sequences($media);
        $media = WPMB_UIC_SEO::fill_alt(
            $media,
            $code,
            $actor_en,
            $focus,
            false,
            isset($payload['title']) ? $payload['title'] : ''
        );
        $payload['media'] = $media;

        $media_settings = wp_parse_args(get_option('wpmb_uic_settings', array()), array(
            'media_gallery_fallback_cover' => 1
        ));

        list($featured, $poster, $gallery) = WPMB_UIC_Media::choose_media_roles(
            $media,
            !empty($media_settings['media_gallery_fallback_cover'])
        );

        $audit = isset($payload['media_library_audit']) && is_array($payload['media_library_audit'])
            ? $payload['media_library_audit']
            : array();

        $steps[] = array(
            'name'   => 'จับคู่ Media',
            'status' => 'done',
            'detail' => count($media) . ' รูป · ใช้ของเดิม '
                . (int) ($audit['library_attachments'] ?? 0)
                . ' · นำเข้าเฉพาะที่ขาด '
                . (int) ($audit['pending_import_files'] ?? 0)
        );

        // ─── STEP 3: เปลี่ยน Gallery เป็น Local ───
        self::set_step($item_id, 'เปลี่ยนลิงก์ Gallery เป็นเว็บปัจจุบัน');

        $content = isset($payload['content']) ? (string) $payload['content'] : '';
        $settings = wp_parse_args(get_option('wpmb_uic_settings', array()), array(
            'force_local_gallery' => 1,
            'allow_external_missing' => 0,
        ));

        $source_urls = isset($payload['gallery_source_urls']) && is_array($payload['gallery_source_urls'])
            ? $payload['gallery_source_urls']
            : array();

        $rewrite = WPMB_UIC_Media::rewrite_gallery_to_local(
            $content,
            $media,
            !empty($settings['force_local_gallery']),
            $source_urls
        );

        $content = $rewrite['content'];

        if (!empty($rewrite['unresolved']) && empty($settings['allow_external_missing'])) {
            throw new RuntimeException(
                'พบลิงก์รูปต้นทางที่จับคู่ Media ในเว็บนี้ไม่ได้ '
                . count($rewrite['unresolved']) . ' รายการ'
            );
        }

        $has_gallery_block = strpos($content, '<!-- wp:gallery') !== false
            || strpos($content, 'wp-block-gallery') !== false;

        if (!$has_gallery_block && $gallery) {
            $content .= WPMB_UIC_Media::build_gallery_block($gallery, 2);
        }

        // แปลงเป็น Gutenberg Blocks
        $content = WPMB_UIC_Media::content_to_gutenberg_blocks($content);
        $payload['content'] = $content;
        $payload['gallery_unresolved'] = $rewrite['unresolved'];

        $steps[] = array(
            'name'   => 'แปลง Gallery เป็น Local',
            'status' => 'done',
            'detail' => empty($rewrite['unresolved'])
                ? 'ไม่เหลือลิงก์ต้นทาง'
                : count($rewrite['unresolved']) . ' ลิงก์ยังไม่พบคู่'
        );

        // ─── STEP 4: สร้าง/อัปเดตโพสต์ ───
        self::set_step($item_id, $is_new ? 'สร้างโพสต์ใหม่' : 'อัปเดตโพสต์เดิม');

        $postarr = array('post_type' => 'post');

        if ($is_new) {
            $postarr['post_title'] = WPMB_UIC_Field_Rules::is_enabled('title')
                && isset($payload['title'])
                && $payload['title'] !== ''
                ? wp_strip_all_tags($payload['title'])
                : $code;
        } elseif (WPMB_UIC_Field_Rules::can_write('title', $existing_post_id)) {
            $postarr['post_title'] = isset($payload['title']) && $payload['title'] !== ''
                ? wp_strip_all_tags($payload['title'])
                : $code;
        }

        if ($is_new) {
            $postarr['post_content'] = WPMB_UIC_Field_Rules::is_enabled('content')
                ? $content
                : '';
        } elseif (WPMB_UIC_Field_Rules::can_write('content', $existing_post_id)) {
            $postarr['post_content'] = $content;
        }

        if ($is_new) {
            $postarr['post_status'] = WPMB_UIC_Field_Rules::is_enabled('status')
                ? self::sanitize_status(isset($payload['status']) ? $payload['status'] : 'draft')
                : 'draft';
        } elseif (WPMB_UIC_Field_Rules::can_write('status', $existing_post_id)) {
            $postarr['post_status'] = self::sanitize_status(
                isset($payload['status']) ? $payload['status'] : 'draft'
            );
        }

        if (!empty($payload['slug']) && WPMB_UIC_Field_Rules::can_write('slug', $existing_post_id)) {
            $postarr['post_name'] = sanitize_title($payload['slug']);
        }

        // Post Date
        $post_date_detail = 'ใช้วันที่ปัจจุบันของ WordPress';

        if (!empty($payload['post_date'])
            && WPMB_UIC_Field_Rules::can_write('post_date', $existing_post_id)
        ) {
            $raw_post_date = str_replace('T', ' ', sanitize_text_field((string) $payload['post_date']));

            if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $raw_post_date)) {
                $raw_post_date .= ':00';
            }

            $date_obj = DateTime::createFromFormat('!Y-m-d H:i:s', $raw_post_date, wp_timezone());

            if ($date_obj instanceof DateTime
                && $date_obj->format('Y-m-d H:i:s') === $raw_post_date
            ) {
                $postarr['post_date'] = $raw_post_date;
                $postarr['post_date_gmt'] = get_gmt_from_date($raw_post_date);
                $post_date_detail = 'กำหนดวันที่ ' . $raw_post_date . ' (ตามเขตเวลา WordPress)';

                // ถ้าเป็นวันในอนาคต + Publish → Scheduled
                if (isset($postarr['post_status'])
                    && $postarr['post_status'] === 'publish'
                    && strtotime($postarr['post_date_gmt'] . ' UTC') > time()
                ) {
                    $postarr['post_status'] = 'future';
                    $post_date_detail .= ' → อนาคต (Scheduled)';
                }
            } else {
                $post_date_detail = 'วันที่ไม่ถูกต้อง ("'
                    . esc_html($raw_post_date) . '") → ข้าม ใช้วันปัจจุบัน';
                $steps[] = array(
                    'name'   => 'ตรวจ Post Date',
                    'status' => 'warning',
                    'detail' => $post_date_detail,
                );
            }
        } elseif (!empty($payload['post_date'])) {
            $post_date_detail = 'มีค่าวันที่ในข้อมูลแต่ Field Lock ป้องกัน → ใช้วันเดิม/ปัจจุบัน';
        }

        if ($post_id) {
            $postarr['ID'] = $post_id;
            $saved = wp_update_post(wp_slash($postarr), true);
        } else {
            $saved = wp_insert_post(wp_slash($postarr), true);
        }

        if (is_wp_error($saved)) {
            throw new RuntimeException($saved->get_error_message());
        }

        $post_id = (int) $saved;

        // 🎯 v1.0.15.3: เขียน _wpmb_code ทุกครั้ง (กุญแจสำคัญ!)
        update_post_meta($post_id, '_wpmb_code', $code);
        update_post_meta($post_id, '_wpmb_code_locked_at', current_time('mysql'));

        $steps[] = array(
            'name'   => $is_new ? 'สร้างโพสต์' : 'อัปเดตโพสต์',
            'status' => 'done',
            'detail' => 'Post ID ' . $post_id . ' · ' . $post_date_detail
        );

        // ─── STEP 5: จับคู่ Taxonomy ───
        self::set_step($item_id, 'จับคู่ Taxonomy โดยไม่สร้างชื่อซ้ำ');

        $taxonomies = isset($payload['taxonomies']) && is_array($payload['taxonomies'])
            ? $payload['taxonomies']
            : array();

        $write_taxonomies = array();
        foreach ($taxonomies as $taxonomy => $names) {
            if (WPMB_UIC_Field_Rules::can_write($taxonomy, $existing_post_id)) {
                $write_taxonomies[$taxonomy] = $names;
            }
        }

        $assigned = WPMB_UIC_Taxonomy::assign_terms($post_id, $write_taxonomies);

        $steps[] = array(
            'name'   => 'Taxonomy',
            'status' => 'done',
            'detail' => array_sum(array_map('count', $assigned)) . ' term'
        );

        // ─── STEP 6: Featured / Gallery / Meta / ALT ───
        self::set_step($item_id, 'ตั้งรูปปก Featured แกลเลอรี่ ALT และ SEO');

        $pm_set = get_option('wpmb_uic_settings', array());
        $protect_meta = !empty($pm_set['protect_theme_meta']);
        $media_meta_log = array();

        // Featured Image
        $existing_thumbnail_id = (int) get_post_thumbnail_id($post_id);

        if ($featured && !empty($featured['attachment_id'])) {
            $new_thumbnail_id = (int) $featured['attachment_id'];

            if (!$protect_meta || !$existing_thumbnail_id) {
                set_post_thumbnail($post_id, $new_thumbnail_id);
                $media_meta_log[] = array(
                    'key' => '_thumbnail_id',
                    'value' => (string) $new_thumbnail_id,
                    'status' => 'written'
                );
            } else {
                $media_meta_log[] = array(
                    'key' => '_thumbnail_id',
                    'value' => (string) $new_thumbnail_id,
                    'status' => 'skipped_protected_theme',
                    'existing' => (string) $existing_thumbnail_id
                );
            }
        }

        // ตั้ง post_parent ให้ attachment
        foreach ($media as $media_entry) {
            $attachment_id = !empty($media_entry['attachment_id'])
                ? (int) $media_entry['attachment_id']
                : 0;

            if (!$attachment_id || (isset($media_entry['role']) && $media_entry['role'] === 'unused')) {
                continue;
            }

            $attachment = get_post($attachment_id);
            if ($attachment
                && $attachment->post_type === 'attachment'
                && (int) $attachment->post_parent === 0
            ) {
                wp_update_post(array(
                    'ID' => $attachment_id,
                    'post_parent' => $post_id
                ));
            }
        }

        // Gallery IDs
        $gallery_ids = array();
        foreach ($gallery as $entry) {
            if (!empty($entry['attachment_id'])) {
                $gallery_ids[] = (int) $entry['attachment_id'];
            }
        }

        // 🚫 v1.0.15.3: ปิดระบบ Poster ถาวร — ไม่เขียน meta Poster ใดๆ
        $media_meta_log[] = array(
            'key' => 'poster',
            'value' => '',
            'status' => 'disabled_by_plugin'
        );

        // Gallery Meta
        $gallery_keys = WPMB_UIC_Meta_Scanner::write_keys_for('gallery_ids');
        foreach ($gallery_keys as $gkey) {
            if (!empty($gallery_ids)) {
                $existing_value = get_post_meta($post_id, $gkey, true);

                if ($protect_meta && $existing_value !== '' && $existing_value !== null) {
                    $media_meta_log[] = array(
                        'key' => $gkey,
                        'value' => wp_json_encode($gallery_ids),
                        'status' => 'skipped_protected_theme',
                        'existing' => is_scalar($existing_value)
                            ? (string) $existing_value
                            : wp_json_encode($existing_value)
                    );
                    continue;
                }

                update_post_meta($post_id, $gkey, $gallery_ids);
                $media_meta_log[] = array(
                    'key' => $gkey,
                    'value' => wp_json_encode($gallery_ids),
                    'status' => 'written'
                );
            }
        }

        // SEO Meta (Yoast)
        WPMB_UIC_SEO::save_post_meta($post_id, $payload, $existing_post_id);
        $meta_log = array_merge(WPMB_UIC_SEO::get_meta_log(), $media_meta_log);

        $steps[] = array(
            'name'   => 'SEO / ALT / Featured',
            'status' => 'done',
            'detail' => 'Cover ' . ($featured ? '1' : '0')
                . ' · Gallery ' . count($gallery)
                . ' (ปิดระบบ Poster)'
        );

        // ─── STEP 7: ตรวจหลังนำเข้า ───
        self::set_step($item_id, 'ตรวจหลังนำเข้า');

        $payload['media_result'] = $media;
        $payload['wp_post_id'] = $post_id;

        $steps[] = array('name' => 'ตรวจหลังนำเข้า', 'status' => 'done');

        return array(
            'post_id'  => $post_id,
            'payload'  => $payload,
            'media'    => $media,
            'steps'    => $steps,
            'meta_log' => isset($meta_log) ? $meta_log : array(),
        );
    }

    /* ============================================================
       🔍 FIND EXISTING POST — 5 STEP
       ============================================================
       STEP 1: target_post_id (ผู้ใช้ระบุ)
       STEP 2: _wpmb_code (Plugin เอง — แม่นสุด)
       STEP 3: meta key อื่นๆ (Legacy)
       STEP 4: Slug (5 รูปแบบ)
       STEP 5: Title (Strict + Middle)
       ============================================================ */
    public static function find_existing_post(array $payload) {
        global $wpdb;

        // ─── STEP 1: target_post_id ───
        if (!empty($payload['target_post_id'])) {
            $post = get_post((int) $payload['target_post_id']);
            if ($post && $post->post_type === 'post' && $post->post_status !== 'trash') {
                return (int) $post->ID;
            }
        }

        $code = WPMB_UIC_Code::normalize_code(isset($payload['code']) ? $payload['code'] : '');
        if ($code === '') {
            return 0;
        }

        // ─── STEP 2: _wpmb_code (Plugin เอง — แม่นสุด) ───
        $id = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT pm.post_id
             FROM {$wpdb->postmeta} pm
             INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
             WHERE pm.meta_key = '_wpmb_code'
               AND UPPER(pm.meta_value) = %s
               AND p.post_type = 'post'
               AND p.post_status NOT IN ('trash', 'auto-draft')
             ORDER BY p.ID ASC
             LIMIT 1",
            $code
        ));

        if ($id > 0) {
            return $id;
        }

        // ─── STEP 3: meta key อื่นๆ (Legacy) ───
        $legacy_keys = array('_acs_code', 'acs_code', 'code', 'product_code');

        foreach ($legacy_keys as $mkey) {
            $id = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT pm.post_id
                 FROM {$wpdb->postmeta} pm
                 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
                 WHERE pm.meta_key = %s
                   AND UPPER(pm.meta_value) = %s
                   AND p.post_type = 'post'
                   AND p.post_status NOT IN ('trash', 'auto-draft')
                 ORDER BY p.ID ASC
                 LIMIT 1",
                $mkey,
                $code
            ));

            if ($id > 0) {
                return $id;
            }
        }

        // ─── STEP 4: Slug (5 รูปแบบ) ───
        $slug_candidates = array(
            sanitize_title($code),
            sanitize_title(str_replace('-', '', $code)),
            sanitize_title(str_replace('-', '_', $code)),
            sanitize_title(strtolower($code)),
            sanitize_title(strtoupper($code)),
        );

        $slug_candidates = array_values(array_unique(array_filter($slug_candidates)));

        if (!empty($slug_candidates)) {
            $in = implode(',', array_fill(0, count($slug_candidates), '%s'));
            $query = $wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts}
                 WHERE post_type = 'post'
                   AND post_status NOT IN ('trash','auto-draft')
                   AND post_name IN ({$in})
                 ORDER BY ID ASC
                 LIMIT 1",
                $slug_candidates
            );

            $id = (int) $wpdb->get_var($query);
            if ($id > 0) {
                return $id;
            }
        }

        // ─── STEP 5a: Title (ขึ้นต้นด้วย CODE) ───
        $esc = $wpdb->esc_like($code);
        $title_patterns = array(
            $esc . ' %',
            $esc . '-%',
            $esc . '_%',
            $esc . '(%',
            $esc,
        );

        foreach ($title_patterns as $pattern) {
            $id = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts}
                 WHERE post_type = 'post'
                   AND post_status NOT IN ('trash','auto-draft')
                   AND post_title LIKE %s
                 ORDER BY ID ASC
                 LIMIT 1",
                $pattern
            ));

            if ($id > 0) {
                return $id;
            }
        }

        // ─── STEP 5b: Title (CODE อยู่กลางชื่อ) ───
        $title_middle = array(
            '% ' . $esc . ' %',
            '%-' . $esc . '-%',
            '%[' . $esc . ']%',
            '%(' . $esc . ')%',
            '%_' . $esc . '_%',
        );

        foreach ($title_middle as $pattern) {
            $id = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts}
                 WHERE post_type = 'post'
                   AND post_status NOT IN ('trash','auto-draft')
                   AND post_title LIKE %s
                 ORDER BY ID ASC
                 LIMIT 1",
                $pattern
            ));

            if ($id > 0) {
                return $id;
            }
        }

        return 0;
    }

    /* ============================================================
       📊 STATUS COUNTS
       ============================================================ */
    public static function status_counts($project_id) {
        global $wpdb;
        $db = WPMB_UIC_DB::instance();

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT status, COUNT(*) c FROM {$db->items} WHERE project_id=%d GROUP BY status",
            $project_id
        ), ARRAY_A);

        $out = array('total' => 0);

        foreach ($rows as $row) {
            $out[$row['status']] = (int) $row['c'];
            $out['total'] += (int) $row['c'];
        }

        return $out;
    }

    /* ============================================================
       📊 SCAN POSTS BATCH
       ============================================================ */
    public static function scan_posts_batch($offset = 0, $limit = 100) {
        global $wpdb;
        $db = WPMB_UIC_DB::instance();

        if ((int) $offset === 0) {
            $wpdb->query("DELETE FROM {$db->post_index}");
        }

        $posts = get_posts(array(
            'post_type'      => 'post',
            'post_status'    => array('publish', 'draft', 'pending', 'private', 'future'),
            'posts_per_page' => max(1, min(500, (int) $limit)),
            'offset'         => max(0, (int) $offset),
            'orderby'        => 'ID',
            'order'          => 'ASC',
        ));

        foreach ($posts as $post) {
            $code = get_post_meta($post->ID, '_wpmb_code', true);

            if (!$code) {
                $code = get_post_meta($post->ID, '_acs_code', true);
            }

            if (!$code) {
                $code = WPMB_UIC_Code::extract_code($post->post_title . ' ' . $post->post_name);
            }

            $wpdb->replace($db->post_index, array(
                'post_id'     => (int) $post->ID,
                'code'        => WPMB_UIC_Code::normalize_code($code),
                'post_title'  => $post->post_title,
                'post_slug'   => $post->post_name,
                'post_status' => $post->post_status,
                'modified_at' => $post->post_modified,
                'scanned_at'  => current_time('mysql'),
            ));
        }

        $counts = wp_count_posts('post');
        $total = 0;
        foreach (array('publish', 'draft', 'pending', 'private', 'future') as $status) {
            $total += isset($counts->$status) ? (int) $counts->$status : 0;
        }

        return array(
            'processed'   => count($posts),
            'next_offset' => (int) $offset + count($posts),
            'total'       => $total,
            'done'        => count($posts) < $limit,
        );
    }

    /* ============================================================
       📝 SET STEP
       ============================================================ */
    private static function set_step($item_id, $step) {
        global $wpdb;
        $db = WPMB_UIC_DB::instance();

        $wpdb->update($db->items, array(
            'step'       => $step,
            'updated_at' => current_time('mysql'),
        ), array('id' => (int) $item_id));
    }

    /* ============================================================
       👤 RESOLVE ACTOR EN
       ============================================================ */
    private static function resolve_actor_en(array $payload) {
        $value = isset($payload['actor_en']) ? trim((string) $payload['actor_en']) : '';

        if ($value !== '') {
            $en = WPMB_UIC_Code::extract_english_names($value);
            if ($en !== '') {
                return $en;
            }
            return $value;
        }

        $terms = isset($payload['taxonomies']['actors']) && is_array($payload['taxonomies']['actors'])
            ? $payload['taxonomies']['actors']
            : array();

        $english = array();

        foreach ($terms as $name) {
            $name = trim((string) $name);
            if ($name === '') {
                continue;
            }

            $en = WPMB_UIC_Code::extract_english_names($name);

            if ($en !== '') {
                $english[] = $en;
            } elseif (!preg_match('/[\x{0E00}-\x{0E7F}]/u', $name)) {
                $english[] = $name;
            }
        }

        return implode(', ', array_values(array_unique($english)));
    }

    /* ============================================================
       ✅ SANITIZE STATUS
       ============================================================ */
    private static function sanitize_status($status) {
        $allowed = array('draft', 'publish', 'pending', 'private', 'future');
        return in_array($status, $allowed, true) ? $status : 'draft';
    }
}