<?php
/**
 * ═══════════════════════════════════════════════════════════════
 * 📜 Admin UI ของ UNI-IM-PORT v1.0.15.3
 * ═══════════════════════════════════════════════════════════════
 *
 * - หน้าจอ Admin ทั้งหมด
 * - Modals (Preview + Edit)
 * - Admin Column (CODE)
 * - Meta Box (CODE — เฉพาะแอดมิน)
 * - Responsive UI
 *
 * 🚫 Index/Noindex ปิดถาวร — UI ซ่อนสวิตช์ทั้งหมด
 *
 * ═══════════════════════════════════════════════════════════════
 */

if (!defined('ABSPATH')) {
    exit;
}

final class WPMB_UIC_Admin {

    private static $instance;
    private $hook_suffixes = array();

    /* ============================================================
       🏗️ SINGLETON + HOOKS
       ============================================================ */
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

        // 🎯 v1.0.15.3: Admin Column + Meta Box
        add_filter('manage_posts_columns', array($this, 'add_code_column'), 20);
        add_action('manage_posts_custom_column', array($this, 'render_code_column'), 10, 2);
        add_filter('manage_edit-post_sortable_columns', array($this, 'make_code_column_sortable'));
        add_action('pre_get_posts', array($this, 'handle_code_column_order'));
        add_action('add_meta_boxes', array($this, 'add_code_meta_box'));

        // 🎯 v1.0.15.3: Render Modals ที่ footer
        add_action('admin_footer', array($this, 'render_modals'));
    }

    /* ============================================================
       🔗 ACTION LINKS
       ============================================================ */
    public function action_links($links) {
        array_unshift($links, '<a href="' . esc_url(admin_url('admin.php?page=wpmb-uic')) . '">เปิด UNI-IM-PORT</a>');
        return $links;
    }

    /* ============================================================
       📋 MENU
       ============================================================ */
    public function menu() {
        $icon = 'dashicons-upload';

        $this->hook_suffixes[] = add_menu_page(
            'UNI-IM-PORT', 'UNI-IM-PORT', 'manage_options',
            'wpmb-uic', array($this, 'dashboard_page'), $icon, 57.20
        );

        $this->hook_suffixes[] = add_submenu_page(
            'wpmb-uic', 'UNI-IM-PORT', 'นำเข้า / ส่งออก', 'manage_options',
            'wpmb-uic', array($this, 'dashboard_page')
        );

        $this->hook_suffixes[] = add_submenu_page(
            'wpmb-uic', 'UNI Portable', 'Export UNI Portable', 'manage_options',
            'wpmb-uic-portable', array($this, 'portable_page')
        );

        $this->hook_suffixes[] = add_submenu_page(
            null, 'Import Project', 'Import Project', 'manage_options',
            'wpmb-uic-project', array($this, 'project_page')
        );

        $this->hook_suffixes[] = add_submenu_page(
            'wpmb-uic', 'สแกนทั้งเว็บ', 'สแกนข้อมูลทั้งเว็บ', 'manage_options',
            'wpmb-uic-scan', array($this, 'scan_page')
        );

        $this->hook_suffixes[] = add_submenu_page(
            'wpmb-uic', 'Media Cleanup', 'รูปไม่ได้ใช้งาน', 'delete_posts',
            'wpmb-uic-media', array($this, 'media_page')
        );

        $this->hook_suffixes[] = add_submenu_page(
            'wpmb-uic', 'Taxonomy & SEO', 'Taxonomy และ Index', 'manage_categories',
            'wpmb-uic-taxonomy', array($this, 'taxonomy_page')
        );

        $this->hook_suffixes[] = add_submenu_page(
            'wpmb-uic', 'Settings', 'ตั้งค่าและตรวจระบบ', 'manage_options',
            'wpmb-uic-settings', array($this, 'settings_page')
        );
    }

    /* ============================================================
       📦 ASSETS
       ============================================================ */
    public function assets($hook) {
        if (!in_array($hook, $this->hook_suffixes, true) && strpos($hook, 'wpmb-uic') === false) {
            return;
        }

        wp_enqueue_media();
        wp_enqueue_style('wpmb-uic-admin', WPMB_UIC_URL . 'assets/admin.css', array(), WPMB_UIC_VERSION);
        wp_enqueue_script('wpmb-uic-admin', WPMB_UIC_URL . 'assets/admin.js', array('jquery', 'jquery-ui-sortable'), WPMB_UIC_VERSION, true);

        // 🎯 v1.0.15.3: Responsive UI (CSS + JS)
        wp_enqueue_style('wpmb-uic-admin-v2', WPMB_UIC_URL . 'assets/admin-v2.css', array('wpmb-uic-admin'), WPMB_UIC_VERSION);
        wp_enqueue_script('wpmb-uic-admin-v2', WPMB_UIC_URL . 'assets/admin-v2.js', array('jquery', 'wpmb-uic-admin'), WPMB_UIC_VERSION, true);

        $field_rules = WPMB_UIC_Field_Rules::settings();

        $asset_settings = wp_parse_args(get_option('wpmb_uic_settings', array()), array(
            'alt_cover_template' => '{code} {actor_en}',
            'alt_poster_template' => '{code} {actor_en}',
            'alt_gallery_template' => '{code} {actor_en}',
        ));

        wp_localize_script('wpmb-uic-admin', 'WPMB_UIC', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wpmb_uic_admin'),
            'page' => isset($_GET['page']) ? sanitize_key($_GET['page']) : '',
            'projectId' => isset($_GET['project_id']) ? absint($_GET['project_id']) : 0,
            'fieldRules' => $field_rules,
            'previewVisibility' => $this->preview_visibility_settings(),
            'altTemplates' => array(
                'cover' => $asset_settings['alt_cover_template'],
                'poster' => $asset_settings['alt_poster_template'],
                'gallery' => $asset_settings['alt_gallery_template'],
            ),
            'strings' => array(
                'working' => 'กำลังทำงาน…',
                'done' => 'เสร็จแล้ว',
                'error' => 'เกิดข้อผิดพลาด',
                'confirmTrash' => 'ยืนยันย้ายรูปที่เลือกไปถังขยะหรือไม่?',
                'confirmMerge' => 'ยืนยันรวม Taxonomy กลุ่มนี้หรือไม่?',
                'confirmClearProject' => 'ยืนยันล้างไฟล์ รายการ Preview และ Log ทั้งหมดในโปรเจกต์นี้หรือไม่?',
                'confirmDeleteProject' => 'ยืนยันลบ Import Project นี้หรือไม่?',
                'confirmExclude' => 'เอาเรื่องนี้ออกจากรายการนำเข้าหรือไม่?',
                'confirmRestore' => 'นำเรื่องนี้กลับเข้ารายการ Preview หรือไม่?',
                'confirmBulkExclude' => 'นำเรื่องที่เลือกออกจากคิวนำเข้าหรือไม่?',
                'confirmAltOverwrite' => 'ยืนยันเขียนทับ ALT เดิมหรือไม่?',
                'confirmApplyRules' => 'ใช้กฎหัวข้อมูลกับรายการที่รออยู่ทั้งหมดหรือไม่?',
            ),
        ));
    }

    /* ============================================================
       📥 DOWNLOAD REPORT
       ============================================================ */
    public function download_report() {
        if (!current_user_can('manage_options')) {
            wp_die('คุณไม่มีสิทธิ์ดาวน์โหลดรายงาน');
        }

        $project_id = isset($_GET['project_id']) ? absint($_GET['project_id']) : 0;
        check_admin_referer('wpmb_uic_report_' . $project_id);

        $project = WPMB_UIC_DB::instance()->get_project($project_id);
        if (!$project) {
            wp_die('ไม่พบ Import Project');
        }

        global $wpdb;
        $db = WPMB_UIC_DB::instance();

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT code,status,step,attempts,error_text,wp_post_id,payload,validation,updated_at
             FROM {$db->items} WHERE project_id=%d ORDER BY id ASC",
            $project_id
        ), ARRAY_A);

        $filename = sanitize_file_name('WP_MY_BOSS_IMPORT_REPORT_' . $project_id . '_' . current_time('Ymd_His') . '_SITE_TIME.csv');

        nocache_headers();
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $out = fopen('php://output', 'wb');
        fwrite($out, "\xEF\xBB\xBF");

        fputcsv($out, array(
            'CODE', 'STATUS', 'STEP', 'ATTEMPTS', 'WP_POST_ID',
            'TITLE_STATUS', 'META_STATUS', 'ALT_COVER', 'ALT_POSTER', 'ALT_GALLERY',
            'MEDIA_LIBRARY_COMPLETE', 'MEDIA_LIBRARY_ATTACHMENTS', 'MEDIA_MISSING_SLOTS',
            'MEDIA_PENDING_IMPORT', 'ERROR', 'UPDATED_AT'
        ));

        foreach ($rows as $row) {
            $validation = json_decode((string) $row['validation'], true);
            $validation = is_array($validation) ? $validation : array();

            $payload = json_decode((string) $row['payload'], true);
            $payload = is_array($payload) ? $payload : array();

            $audit = isset($payload['media_library_audit']) && is_array($payload['media_library_audit'])
                ? $payload['media_library_audit']
                : array();

            $title = isset($validation['title']) ? $validation['title'] : array();
            $meta = isset($validation['meta']) ? $validation['meta'] : array();
            $alt = isset($validation['alt']) ? $validation['alt'] : array();

            fputcsv($out, array(
                $row['code'], $row['status'], $row['step'],
                (int) $row['attempts'], (int) $row['wp_post_id'],
                (!empty($title['ok']) ? 'PASS' : 'CHECK') . ' ' . (int) ($title['length'] ?? 0) . '/' . (int) ($title['max'] ?? 0),
                (!empty($meta['ok']) ? 'PASS' : 'CHECK') . ' ' . (int) ($meta['length'] ?? 0) . '/' . (int) ($meta['max'] ?? 0),
                !empty($alt['featured']) ? 'PASS' : 'CHECK',
                !empty($alt['poster']) ? 'PASS' : 'CHECK',
                (int) ($alt['gallery_complete'] ?? 0) . '/' . (int) ($alt['gallery_total'] ?? 0),
                !empty($audit['complete']) ? 'YES' : 'NO',
                (int) ($audit['library_attachments'] ?? 0),
                implode('|', (array) ($audit['missing_slots'] ?? array())),
                (int) ($audit['pending_import_files'] ?? 0),
                $row['error_text'], $row['updated_at'],
            ));
        }

        fclose($out);
        exit;
    }

    /* ============================================================
       🎨 HEADER (Helper)
       ============================================================ */
    private function header($title, $subtitle = '') {
        echo '<div class="wrap wpmb-wrap">';
        echo '<div class="wpmb-hero"><div class="wpmb-brand"><img class="wpmb-uni-logo" src="' . esc_url(WPMB_UIC_URL . 'assets/boss-logo-square.png') . '" alt="UNI-IM-PORT"><div><h1>' . esc_html($title) . '</h1>';
        if ($subtitle) {
            echo '<p>' . esc_html($subtitle) . '</p>';
        }
        echo '</div></div><span class="wpmb-version">v' . esc_html(WPMB_UIC_VERSION) . '</span></div>';
    }

    private function footer() {
        echo '</div>';
    }

    /* ============================================================
       🏠 DASHBOARD PAGE
       ============================================================ */
    public function dashboard_page() {
        $this->header('UNI-IM-PORT', 'เก็บไฟล์ทุกส่วนไว้ในฐานกลาง ตรวจแก้ Preview แล้วจึงนำเข้าเว็บจริง');

        $projects = WPMB_UIC_DB::instance()->list_projects(100);
        ?>
        <div class="wpmb-grid wpmb-grid-2">
            <section class="wpmb-card wpmb-card-accent">
                <h2>สร้าง Import Project</h2>
                <p>ไฟล์ XML, CSV, XLSX, TXT, JSON, ZIP และรูปแยกหลายชุดสามารถเพิ่มเข้ามาทีหลังและรวมด้วย CODE เป็นเรื่องเดียวได้</p>
                <form id="wpmb-create-project" class="wpmb-form">
                    <label>ชื่อโปรเจกต์<input type="text" name="name" placeholder="เช่น AVSPICYXX — รอบ 15 กรกฎาคม"></label>
                    <label>ลักษณะเว็บ<select name="mode">
                        <option value="mixed">ผสม — มีบางส่วนแล้ว</option>
                        <option value="fresh">เว็บใหม่ — สร้างข้อมูลที่ไม่มี</option>
                        <option value="update">เว็บเดิม — อัปเดตเท่านั้น</option>
                    </select></label>
                    <button class="button button-primary wpmb-btn wpmb-btn-primary" type="submit">สร้างโปรเจกต์และเพิ่มไฟล์</button>
                </form>
            </section>
            <section class="wpmb-card">
                <h2>หลักการทำงาน</h2>
                <ol class="wpmb-steps">
                    <li><b>เพิ่มไฟล์</b> จะเป็นแพ็กเดียวหรือแยกหลายครั้งก็ได้</li>
                    <li><b>ระบบสแกนและรวม</b> Post + Media + Gallery + เวลา + SEO ด้วย CODE</li>
                    <li><b>Preview Editor</b> แก้ชื่อ Meta เนื้อหา Taxonomy ALT และลำดับรูปได้</li>
                    <li><b>Import Queue</b> ทำทีละเรื่องและแสดงขั้นตอนสด</li>
                </ol>
            </section>
        </div>
        <section class="wpmb-card">
            <div class="wpmb-section-head"><div><h2>โปรเจกต์ทั้งหมด</h2><p>ข้อมูลยังอยู่ในฐานกลางจนกว่าจะกดเริ่มนำเข้าจริง</p></div></div>
            <?php if (!$projects) : ?>
                <div class="wpmb-empty">ยังไม่มีโปรเจกต์</div>
            <?php else : ?>
                <div class="wpmb-table-wrap">
                    <table class="widefat striped wpmb-table">
                        <thead><tr><th>ชื่อ</th><th>โหมด</th><th>สถานะ</th><th>แก้ไขล่าสุด</th><th>จัดการ</th></tr></thead>
                        <tbody>
                        <?php foreach ($projects as $project) : ?>
                            <tr data-project-row="<?php echo (int) $project['id']; ?>">
                                <td><strong><?php echo esc_html($project['name']); ?></strong></td>
                                <td><?php echo esc_html($project['mode']); ?></td>
                                <td><span class="wpmb-badge"><?php echo esc_html($project['status']); ?></span></td>
                                <td><?php echo esc_html($project['updated_at']); ?></td>
                                <td>
                                    <div class="wpmb-row-actions">
                                        <a class="button wpmb-btn" href="<?php echo esc_url(admin_url('admin.php?page=wpmb-uic-project&project_id=' . (int) $project['id'])); ?>">เปิดโปรเจกต์</a>
                                        <button type="button" class="button wpmb-btn wpmb-btn-danger wpmb-delete-project" data-project-id="<?php echo (int) $project['id']; ?>" data-project-name="<?php echo esc_attr($project['name']); ?>">ลบโปรเจกต์</button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
        <?php
        $this->footer();
    }

    /* ============================================================
       📦 PORTABLE PAGE
       ============================================================ */
    public function portable_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $profile = WPMB_UIC_Portable_Settings::collect();
        $this->header('Export UNI Portable', 'ส่งออกเฉพาะค่าร่วมของ UNI โดยไม่รวม Project, Queue, Log หรือ Runtime');
        ?>
        <div class="wpmb-grid wpmb-grid-2">
            <section class="wpmb-card wpmb-card-accent">
                <h2>UNI Shared Settings</h2>
                <p>ไฟล์นี้มีเฉพาะค่า Import/Export ที่อยู่ใน whitelist และนำไป Preview ก่อน Merge ได้</p>
                <p>
                    <a class="button button-primary wpmb-btn wpmb-btn-primary" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=wpmb_uic_export_portable_settings'), 'wpmb_uic_export_portable_settings')); ?>">ดาวน์โหลด UNI Portable JSON</a>
                </p>
            </section>
            <section class="wpmb-card">
                <h2>ขอบเขตข้อมูล</h2>
                <ul>
                    <li>Queue: <?php echo empty($profile['contains_queue']) ? 'ไม่รวม' : 'รวม'; ?></li>
                    <li>Log: <?php echo empty($profile['contains_logs']) ? 'ไม่รวม' : 'รวม'; ?></li>
                    <li>Runtime: <?php echo empty($profile['contains_runtime']) ? 'ไม่รวม' : 'รวม'; ?></li>
                </ul>
                <?php if (class_exists('WPMB_Portable_Site_Package')) : ?>
                    <p><a class="button" href="<?php echo esc_url(admin_url('admin.php?page=wpmb-portable-profile')); ?>">เปิด Portable Site Package Control Center</a></p>
                <?php endif; ?>
            </section>
        </div>
        <?php
        $this->footer();
    }

    /* ============================================================
       📂 PROJECT PAGE
       ============================================================ */
    public function project_page() {
        $project_id = isset($_GET['project_id']) ? absint($_GET['project_id']) : 0;
        $project = WPMB_UIC_DB::instance()->get_project($project_id);

        if (!$project) {
            wp_die('ไม่พบ Import Project');
        }

        global $wpdb;
        $db = WPMB_UIC_DB::instance();

        $files = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$db->files} WHERE project_id=%d AND parent_file_id=0 ORDER BY id ASC",
            $project_id
        ), ARRAY_A);

        $counts = WPMB_UIC_Importer::status_counts($project_id);

        $this->header($project['name'], 'โหมด ' . $project['mode'] . ' — สามารถเพิ่มไฟล์หลายรอบและรวมเป็นโปรเจกต์เดียว');
        ?>
        <div id="wpmb-project" data-project-id="<?php echo (int) $project_id; ?>" data-project-name="<?php echo esc_attr($project['name']); ?>">

            <div class="wpmb-project-tools">
                <a class="button wpmb-btn" href="<?php echo esc_url(admin_url('admin.php?page=wpmb-uic')); ?>">← โปรเจกต์ทั้งหมด</a>
                <span class="wpmb-project-tools-spacer"></span>
                <button type="button" class="button wpmb-btn wpmb-btn-media-search" id="wpmb-match-library-media-top">🔎 ค้นหา Media ในเว็บ</button>
                <a class="button wpmb-btn wpmb-btn-secondary" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=wpmb_uic_export_matched_package&project_id=' . (int) $project_id), 'wpmb_uic_export_matched_' . (int) $project_id)); ?>">ส่งออกชุดที่จับคู่</a>
                <button type="button" class="button wpmb-btn" id="wpmb-clear-project">ล้างข้อมูลหน้า Import</button>
                <button type="button" class="button wpmb-btn wpmb-btn-danger" id="wpmb-delete-current-project">ลบโปรเจกต์นี้</button>
            </div>

            <div id="wpmb-project-action-message" aria-live="polite"></div>

            <!-- ============================================
                 📁 Section 1: Upload
                 ============================================ -->
            <section class="wpmb-card wpmb-upload-card">
                <div class="wpmb-section-head">
                    <div>
                        <h2>1. เพิ่มไฟล์เข้าฐานกลาง</h2>
                        <p>รองรับ ZIP แพ็กเดียวหรือ XML/CSV/XLSX/TXT/JSON/รูป JPG PNG WebP GIF ที่แยกกัน</p>
                    </div>
                    <span class="wpmb-safe">ยังไม่เข้าเว็บจริง</span>
                </div>
                <form id="wpmb-upload-form" enctype="multipart/form-data">
                    <input type="hidden" name="project_id" value="<?php echo (int) $project_id; ?>">
                    <label class="wpmb-dropzone">
                        <input type="file" name="files[]" multiple accept=".zip,.xml,.csv,.xlsx,.json,.txt,.jpg,.jpeg,.png,.webp,.gif,.avif">
                        <span class="dashicons dashicons-upload"></span>
                        <strong>เลือกไฟล์หรือลากมาวาง</strong>
                        <small>ระบบจะเปิด ZIP อย่างปลอดภัยและนำเฉพาะชนิดไฟล์ที่รองรับมาแยกและรวม</small>
                    </label>
                    <div class="wpmb-actions">
                        <button type="submit" class="button button-primary wpmb-btn wpmb-btn-primary">อัปโหลดและวิเคราะห์</button>
                        <button type="button" class="button wpmb-btn" id="wpmb-resume-analysis">ทำต่อการวิเคราะห์ไฟล์ค้าง</button>
                        <progress id="wpmb-upload-progress" max="100" value="0"></progress>
                        <span id="wpmb-upload-text"></span>
                    </div>
                </form>
                <div id="wpmb-upload-result"></div>
            </section>

            <!-- ============================================
                 📊 Section 2: Files & Summary (Compact)
                 ============================================ -->
            <section class="wpmb-card">
                <div class="wpmb-section-head">
                    <div>
                        <h2>ไฟล์และส่วนประกอบที่ตรวจพบ</h2>
                        <p>ตรวจว่าไฟล์ใดใช้เป็นเรื่อง รูป เวลา SEO หรือข้อมูลเสริม</p>
                    </div>
                    <button class="button wpmb-btn" id="wpmb-refresh-summary">รีเฟรช</button>
                </div>
                <div id="wpmb-project-summary">
                    <?php $this->render_counts($counts); ?>
                    <?php $this->render_files_compact($files); ?>
                </div>
            </section>

            <!-- ============================================
                 📝 Section 3: Preview Editor
                 ============================================ -->
            <section class="wpmb-card wpmb-preview-shell">
                <div class="wpmb-section-head">
                    <div>
                        <h2>2. Preview Editor ก่อนนำเข้าจริง</h2>
                        <p>ตรวจและแก้ Title, Slug, Content, SEO, Meta, Taxonomy, ALT, รูปปก และลำดับ Gallery</p>
                    </div>
                    <button class="button wpmb-btn wpmb-btn-secondary" id="wpmb-toggle-preview" aria-expanded="false">แสดงตัวอย่าง ▼</button>
                </div>
                <div id="wpmb-preview-panel" hidden>
                    <div class="wpmb-toolbar">
                        <button class="button wpmb-btn" id="wpmb-load-items">โหลดรายการ</button>
                        <button class="button wpmb-btn wpmb-btn-media-search" id="wpmb-match-library-media">🔎 ค้นหาและใส่ Media ในเว็บ</button>
                        <button class="button wpmb-btn" id="wpmb-select-page">☑ เลือกหน้านี้</button>
                        <button class="button wpmb-btn" id="wpmb-clear-selection">ล้างที่เลือก</button>
                        <button class="button wpmb-btn wpmb-keep-selected" id="wpmb-keep-selected">✅ เอาเรื่องที่เลือก</button>
                        <button class="button wpmb-btn wpmb-exclude-selected" id="wpmb-exclude-selected">❎ ไม่เอาที่เลือก</button>
                        <button class="button wpmb-btn" id="wpmb-alt-missing-all">ALT ที่ว่างทั้งหมด</button>
                        <button class="button wpmb-btn" id="wpmb-alt-overwrite-all">เขียน ALT ใหม่ทั้งหมด</button>
                        <button class="button wpmb-btn" id="wpmb-apply-field-rules">ใช้กฎหัวข้อมูล/Slug</button>
                        <button class="button wpmb-btn" id="wpmb-mark-ready">ตรวจและตั้งพร้อมนำเข้าทั้งหมด</button>
                        <select id="wpmb-filter-status">
                            <option value="">ทุกสถานะ</option>
                            <option value="staged">รอตรวจ</option>
                            <option value="warning">มีคำเตือน</option>
                            <option value="ready">พร้อม</option>
                            <option value="error">Error</option>
                            <option value="done">เสร็จ</option>
                            <option value="done_warning">เสร็จพร้อมคำเตือน</option>
                            <option value="excluded">นำออกแล้ว</option>
                        </select>
                    </div>
                    <div id="wpmb-items"></div>
                    <div class="wpmb-pagination">
                        <button class="button" id="wpmb-prev-page">ก่อนหน้า</button>
                        <span id="wpmb-page-label">1/1</span>
                        <button class="button" id="wpmb-next-page">ถัดไป</button>
                    </div>
                </div>
            </section>

            <!-- ============================================
                 🚀 Section 4: Import Queue + Real-time Report
                 ============================================ -->
            <section class="wpmb-card wpmb-run-card">
                <div class="wpmb-section-head">
                    <div>
                        <h2>3. เริ่มนำเข้าจริง</h2>
                        <p>Queue ทำทีละเรื่องเป็นค่าเริ่มต้น รองรับทุกขนาดงานและทำต่อจากจุดเดิมได้</p>
                    </div>
                    <span class="wpmb-live-dot"></span>
                </div>
                <div class="wpmb-run-controls">
                    <button class="button button-primary wpmb-btn wpmb-btn-primary" id="wpmb-start-import">เริ่มนำเข้า</button>
                    <button class="button wpmb-btn" id="wpmb-pause-import" disabled>หยุดชั่วคราว</button>
                    <button class="button wpmb-btn" id="wpmb-resume-import" disabled>ทำต่อ</button>
                    <button class="button wpmb-btn" id="wpmb-retry-errors">ทำใหม่เฉพาะ Error</button>
                    <a class="button wpmb-btn" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=wpmb_uic_download_report&project_id=' . (int) $project_id), 'wpmb_uic_report_' . (int) $project_id)); ?>">ดาวน์โหลดรายงาน CSV</a>
                    <a class="button wpmb-btn wpmb-btn-secondary" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=wpmb_uic_export_matched_package&project_id=' . (int) $project_id), 'wpmb_uic_export_matched_' . (int) $project_id)); ?>">ส่งออกชุดที่จับคู่</a>
                </div>

                <!-- 🎯 v1.0.15.3: Real-time Report -->
                <div class="wpmb-rt">
                    <div class="wpmb-rt-header">
                        <div>
                            <span>🔄 กำลังนำเข้า:</span>
                            <span class="wpmb-rt-code" id="wpmb-rt-code">—</span>
                        </div>
                        <div class="wpmb-rt-counter">
                            <strong id="wpmb-rt-done">0</strong> / <span id="wpmb-rt-total">0</span>
                            (<span id="wpmb-rt-percent">0</span>%)
                        </div>
                    </div>
                    <div class="wpmb-progress-bar">
                        <div class="wpmb-progress-fill" id="wpmb-rt-bar" style="width: 0%;"></div>
                    </div>
                    <div class="wpmb-steps" id="wpmb-rt-steps"></div>
                    <details>
                        <summary style="cursor: pointer; padding: 8px; font-size: 13px; font-weight: 600;">
                            📋 ประวัติ 10 รายการล่าสุด
                        </summary>
                        <div class="wpmb-history" id="wpmb-rt-history" style="margin-top: 8px;"></div>
                    </details>
                </div>
            </section>
        </div>
        <?php
        $this->footer();
    }

    /* ============================================================
       🔍 SCAN PAGE
       ============================================================ */
    public function scan_page() {
        $this->header('สแกนข้อมูลทั้งเว็บ', 'สร้างฐานข้อมูลกลางของโพสต์ Media และ Taxonomy เพื่อจับคู่ได้แม้ ID หรือ Slug ต่างกัน');
        ?>
        <section class="wpmb-card">
            <h2>Website Database Scanner</h2>
            <p>ระบบทำเป็นชุดย่อยและแสดงสถานะ ไม่ต้องเปิดหน้าค้างกับคำสั่ง PHP ยาวครั้งเดียว</p>
            <button class="button button-primary wpmb-btn wpmb-btn-primary" id="wpmb-start-scan">เริ่มสแกนทั้งเว็บ</button>
            <button class="button wpmb-btn" id="wpmb-pause-scan" disabled>หยุดชั่วคราว</button>
            <div class="wpmb-progress-box">
                <progress id="wpmb-scan-progress" max="100" value="0"></progress>
                <div id="wpmb-scan-status">รอเริ่ม</div>
            </div>
            <div class="wpmb-check-list">
                <span>โพสต์และ CODE</span>
                <span>ชื่อไฟล์/ALT/Attachment ID</span>
                <span>แนวนอน/แนวตั้ง/ขนาด</span>
                <span>Category/Tag/Actor/Studio</span>
                <span>จำนวนเรื่องและสถานะ Index</span>
            </div>
        </section>

        <section class="wpmb-card" id="wpmb-meta-scanner-section">
            <h2>Meta Key Scanner &amp; Mapping (STRICT MODE)</h2>
            <p>สแกนหา <b>meta key จริงทั้งหมด</b>ในตาราง postmeta ของเว็บไซต์นี้ แล้ว Lock Meta Key ที่ต้องการให้ Import เขียนเท่านั้น</p>

            <div class="wpmb-notice wpmb-notice-info">
                <strong>🎯 STRICT MODE</strong><br>
                ระบบจะเขียนเฉพาะ Meta Key ที่คุณ Lock ไว้เท่านั้น — ไม่ Auto-detect, ไม่ Fallback, ไม่สร้าง Meta Key อื่น
            </div>

            <div class="wpmb-actions">
                <button type="button" class="button button-primary wpmb-btn wpmb-btn-primary" id="wpmb-meta-scan-start">เริ่มสแกน Meta Key</button>
                <button type="button" class="button wpmb-btn" id="wpmb-meta-scan-save" disabled>บันทึกลงระบบนำเข้า</button>
                <button type="button" class="button wpmb-btn wpmb-btn-danger" id="wpmb-meta-scan-reset">รีเซ็ตเป็นค่าเริ่มต้น</button>
            </div>

            <div id="wpmb-meta-scan-status" style="margin:10px 0;color:#666;">รอเริ่มสแกน</div>
            <div id="wpmb-meta-scan-results" style="overflow-x:auto;"></div>

            <script type="text/javascript">
            (function(){
                var nonce = '<?php echo wp_create_nonce("wpmb_uic_admin"); ?>';
                var $ = jQuery;
                var savedMapping = {};
                var allMeta = [];
                var fieldOptions = '';

                function escHtml(s){ return String(s==null?'':s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

                function post(action, data, cb){
                    $.post(ajaxurl, $.extend({action:'wpmb_'+action, nonce:nonce}, data), cb, 'json');
                }

                function addKeyToField(field, key){
                    if(!field || !key) return false;
                    var $cell = $('#wpmb-keys-cell-'+field);
                    if(!$cell.length) return false;
                    var found = false;
                    $cell.find('input.wpmb-meta-key').each(function(){
                        if($(this).val()===key){ found=true; $(this).prop('checked',true); }
                    });
                    if(!found){
                        $cell.append('<label style="display:block;margin:2px 0;"><input type="checkbox" class="wpmb-meta-key" data-field="'+escHtml(field)+'" value="'+escHtml(key)+'" checked> <code>'+escHtml(key)+'</code> <small style="color:#008000;font-weight:bold;">(เพิ่มเอง)</small></label>');
                    }
                    return true;
                }

                function renderAllMeta(filter){
                    var $list = $('#wpmb-all-meta-list');
                    if(!$list.length) return;
                    var f = (filter||'').toLowerCase();
                    var shown = allMeta.filter(function(r){ return !f || String(r.meta_key).toLowerCase().indexOf(f)>=0; }).slice(0, 400);
                    var html = '';
                    if(!shown.length){ html = '<div class="wpmb-empty-small">ไม่พบ meta key ตรงกับคำค้นหา</div>'; }
                    $.each(shown, function(i, r){
                        html += '<div class="wpmb-allmeta-row"><code>'+escHtml(r.meta_key)+'</code> <small>('+Number(r.c)+' รายการ)</small> <select class="wpmb-assign-field"><option value="">— จับคู่กับฟิลด์ —</option>'+fieldOptions+'</select><button type="button" class="button wpmb-assign-add" data-key="'+escHtml(r.meta_key)+'">เพิ่ม</button></div>';
                    });
                    $list.html(html);
                    $('#wpmb-allmeta-count').text('แสดง ' + shown.length + ' จากทั้งหมด ' + allMeta.length + ' meta key (พิมพ์เพื่อค้นหา)');
                }

                $('#wpmb-meta-scan-start').on('click', function(){
                    var $st = $('#wpmb-meta-scan-status');
                    $st.text('กำลังสแกน meta key ในฐานข้อมูล...');
                    $('#wpmb-meta-scan-save').prop('disabled', true);

                    post('meta_scan', {}, function(r){
                        if(!r || !r.success){ $st.text('สแกนไม่สำเร็จ: '+(r && r.data && r.data.message ? r.data.message : 'unknown')); return; }
                        var d = r.data;
                        savedMapping = d.saved_mapping || {};
                        var labels = d.labels || {};
                        var fields = d.fields || {};
                        allMeta = d.all_meta_keys || [];
                        fieldOptions = '';
                        $.each(labels, function(f, lb){ fieldOptions += '<option value="'+escHtml(f)+'">'+escHtml(lb)+'</option>'; });

                        var html = '<table class="widefat striped" style="margin-top:10px;"><thead><tr><th style="width:22%;">ฟิลด์</th><th style="width:14%;">สถานะ</th><th>Meta Key ที่ใช้ตอน Import (ติ๊กเลือก)</th></tr></thead><tbody>';

                        $.each(fields, function(field, info){
                            var found = info.keys_found || [], missing = info.keys_missing || [], counts = info.counts || {};
                            var savedKeys = savedMapping[field] || found;
                            var statusBadge = found.length ? '<span style="color:#008000;font-weight:bold;">พบ '+found.length+' key</span>' : '<span style="color:#c00;font-weight:bold;">ไม่พบ</span>';
                            var keysHtml = '<div id="wpmb-keys-cell-'+escHtml(field)+'">';
                            var allKeys = found.concat(missing.filter(function(k){return found.indexOf(k)<0;}));

                            $.each(allKeys, function(i, k){
                                var checked = savedKeys.indexOf(k) >= 0 ? 'checked' : '';
                                var cnt = counts[k] ? ' <small>('+counts[k]+' รายการ)</small>' : (found.indexOf(k)<0 ? ' <small style="color:#999;">(ยังไม่มีในเว็บ)</small>' : '');
                                keysHtml += '<label style="display:block;margin:2px 0;"><input type="checkbox" class="wpmb-meta-key" data-field="'+escHtml(field)+'" value="'+escHtml(k)+'" '+checked+'> <code>'+escHtml(k)+'</code>'+cnt+'</label>';
                            });

                            keysHtml += '<div style="margin-top:8px;padding-top:8px;border-top:1px dashed #ccc;">'
                                + '<input type="text" class="wpmb-custom-key" data-field="'+escHtml(field)+'" placeholder="พิมพ์ meta key ที่ต้องการเอง…" style="width:62%;max-width:280px;padding:5px 8px;"> '
                                + '<button type="button" class="button wpmb-add-custom" data-field="'+escHtml(field)+'">เพิ่ม</button></div></div>';

                            html += '<tr><td><strong>'+escHtml(labels[field]||field)+'</strong></td><td>'+statusBadge+'</td><td>'+keysHtml+'</td></tr>';
                        });

                        html += '</tbody></table>';

                        html += '<div style="margin-top:20px;padding:14px 16px;border:1px solid #d5d9df;border-radius:12px;background:#fafbfc;">'
                            + '<h3 style="margin:0 0 6px;font-size:15px;">เมต้าทั้งหมดที่พบจริงในเว็บ (เรียงจำนวนมาก → น้อย)</h3>'
                            + '<p style="margin:0 0 10px;color:#666;font-size:12.5px;">พบทั้งหมด <b>'+allMeta.length+'</b> meta key</p>'
                            + '<input type="text" id="wpmb-allmeta-search" placeholder="ค้นหา meta key…" style="width:100%;max-width:420px;padding:7px 10px;margin-bottom:6px;border:1px solid #cfd5dc;border-radius:8px;">'
                            + '<div><small id="wpmb-allmeta-count" style="color:#888;"></small></div>'
                            + '<div id="wpmb-all-meta-list" style="max-height:340px;overflow-y:auto;margin-top:8px;padding-right:6px;"></div>'
                            + '</div>';

                        $('#wpmb-meta-scan-results').html(html);
                        renderAllMeta('');
                        $st.text('สแกนเสร็จ — ตรวจสอบและกด "บันทึกลงระบบนำเข้า"');
                        $('#wpmb-meta-scan-save').prop('disabled', false);
                    });
                });

                $('#wpmb-meta-scan-results').on('click', '.wpmb-add-custom', function(){
                    var field = $(this).data('field');
                    var $inp = $(this).siblings('input.wpmb-custom-key');
                    var key = $.trim($inp.val());
                    if(!key) return;
                    if(addKeyToField(field, key)){ $inp.val(''); }
                });

                $('#wpmb-meta-scan-results').on('keydown', '.wpmb-custom-key', function(e){
                    if(e.keyCode===13){ e.preventDefault(); $(this).siblings('.wpmb-add-custom').click(); }
                });

                $('#wpmb-meta-scan-results').on('click', '.wpmb-assign-add', function(){
                    var key = $(this).data('key');
                    var field = $(this).siblings('select.wpmb-assign-field').val();
                    if(!field){ alert('กรุณาเลือกฟิลด์ที่ต้องการจับคู่ก่อน'); return; }
                    if(addKeyToField(field, key)){ $(this).text('✓ เพิ่มแล้ว').prop('disabled',true).css('color','#008000'); }
                });

                $('#wpmb-meta-scan-results').on('input', '#wpmb-allmeta-search', function(){
                    renderAllMeta($(this).val());
                });

                $('#wpmb-meta-scan-save').on('click', function(){
                    var mapping = {};
                    $('.wpmb-meta-key:checked').each(function(){
                        var f = $(this).data('field');
                        if(!mapping[f]) mapping[f]=[];
                        mapping[f].push($(this).val());
                    });
                    var $st = $('#wpmb-meta-scan-status');
                    $st.text('กำลังบันทึก...');
                    post('meta_save', {mapping: mapping}, function(r){
                        if(r && r.success){
                            $st.text('✅ บันทึกแล้ว — ระบบนำเข้าจะใช้ Meta Key เหล่านี้');
                            savedMapping = r.data.saved;
                        } else {
                            $st.text('บันทึกไม่สำเร็จ: '+((r&&r.data&&r.data.message)?r.data.message:''));
                        }
                    });
                });

                $('#wpmb-meta-scan-reset').on('click', function(){
                    if(!confirm('รีเซ็ต Meta Key Mapping กลับเป็นค่าเริ่มต้น?')) return;
                    post('meta_reset', {}, function(r){
                        if(r && r.success){ savedMapping = r.data.mapping; $('#wpmb-meta-scan-start').click(); }
                    });
                });
            })();
            </script>
        </section>
        <?php
        $this->footer();
    }

    /* ============================================================
       🧹 MEDIA PAGE
       ============================================================ */
    public function media_page() {
        $this->header('Media Cleanup Scanner', 'ค้นหารูปที่ไม่ถูกใช้งาน ละเว้นภาพป้าย และย้ายไปถังขยะหลังตรวจซ้ำ');
        ?>
        <section class="wpmb-card">
            <div class="wpmb-notice wpmb-notice-warn">
                <strong>Safe Delete:</strong> ระบบไม่ลบทันที จะสแกนการใช้งานซ้ำก่อน และใช้ถังขยะของ WordPress
            </div>
            <label class="wpmb-switch">
                <input type="checkbox" id="wpmb-ignore-banners" checked>
                <span></span>
                ละเว้น Banner / Ads / Logo / Icon / Slider / Header / Footer / Promo
            </label>
            <div class="wpmb-actions">
                <a class="button wpmb-btn" href="<?php echo esc_url(admin_url('media-new.php')); ?>">อัปโหลดรูปเข้า Media แบบปกติ</a>
                <button class="button button-primary wpmb-btn wpmb-btn-primary" id="wpmb-scan-unused">สแกนรูปที่ไม่ถูกใช้งาน</button>
                <button class="button wpmb-btn wpmb-btn-danger" id="wpmb-trash-unused" disabled>ย้ายรายการที่เลือกไปถังขยะ</button>
            </div>
            <div id="wpmb-unused-results"></div>
        </section>
        <?php
        $this->footer();
    }

    /* ============================================================
       🏷️ TAXONOMY PAGE
       ============================================================ */
    public function taxonomy_page() {
        $settings = wp_parse_args(get_option('wpmb_uic_settings', array()), array(
            'taxonomy_threshold' => 10
        ));

        $this->header('Taxonomy Guard', 'รวมเฉพาะชื่อที่ตัวอักษรเหมือนกันทุกตัวแต่เว้นวรรคต่างกัน และเลือกตัวที่มีเรื่องมากที่สุด');
        ?>
        <div class="wpmb-grid wpmb-grid-2">
            <section class="wpmb-card">
                <h2>ตรวจ Taxonomy ซ้ำจากช่องว่าง</h2>
                <div class="wpmb-example">
                    <code>หนังAVญี่ปุ่น</code> = <code>หนัง AV ญี่ปุ่น</code><br>
                    <code>หนังเอวีญี่ปุ่น</code> ≠ <code>หนังAVญี่ปุ่น</code>
                </div>
                <button class="button button-primary wpmb-btn wpmb-btn-primary" id="wpmb-scan-tax-duplicates">สแกนรายการซ้ำ</button>
                <div id="wpmb-tax-groups"></div>
            </section>

            <section class="wpmb-card">
                <h2>Index / Noindex</h2>
                <div class="wpmb-notice wpmb-notice-warn">
                    <strong>🚫 ระบบ Index/Noindex ถูกปิดถาวร</strong><br>
                    Yoast SEO / Rank Math / ธีม ยังคงเป็นเจ้าของ Robots ตามปกติ<br>
                    ปลั๊กอินนี้จะไม่แก้ Robots ของ Taxonomy ใดๆ
                </div>
                <p>จำนวนเรื่องขั้นต่ำก่อน Taxonomy Index: <strong><?php echo (int) $settings['taxonomy_threshold']; ?> เรื่อง</strong> (แสดงเพื่อเป็นข้อมูลเท่านั้น)</p>
            </section>
        </div>
        <?php
        $this->footer();
    }

    /* ============================================================
       ⚙️ SETTINGS PAGE
       ============================================================ */
    public function settings_page() {
        $settings = wp_parse_args(get_option('wpmb_uic_settings', array()), array(
            'title_max' => 70,
            'meta_max' => 140,
            'taxonomy_threshold' => 10,
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
            'preview_visibility' => array(),
        ));

        $field_rules = WPMB_UIC_Field_Rules::settings();
        $field_definitions = WPMB_UIC_Field_Rules::definitions();
        $field_lock_options = WPMB_UIC_Field_Rules::lock_options();

        $uploads = wp_upload_dir();

        $checks = array(
            'PHP' => PHP_VERSION,
            'WordPress' => get_bloginfo('version'),
            'ZIP Reader' => class_exists('ZipArchive') ? 'ZipArchive พร้อมใช้งาน' : 'PCLZip Fallback',
            'PHP XML / SimpleXML' => function_exists('simplexml_load_file') ? 'พร้อมใช้งาน' : 'ไม่พบ — XML/WXR จะอ่านไม่ได้',
            'XMLReader' => class_exists('XMLReader') ? 'พร้อม' : 'ไม่พบ',
            'PHP DOM' => class_exists('DOMDocument') ? 'พร้อม' : 'ไม่พบ',
            'Uploads writable' => empty($uploads['error']) && is_writable($uploads['basedir']) ? 'เขียนได้' : 'เขียนไม่ได้',
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size'),
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'WebP Editor' => function_exists('wp_get_image_editor') ? 'ตรวจผ่าน WP Image Editor' : 'ไม่พบ',
            'Subtitle MIME Shield' => WPMB_UIC_Compat::is_patched() ? 'ทำงานแล้ว' : 'ตรวจพบคลาส Subtitle หรือยังไม่พบ',
        );

        $this->header('ตั้งค่าและตรวจระบบ', 'ค่าทั้งหมดแก้ได้ ไม่ล็อกจำนวนเรื่องหรือรูปแบบไฟล์');
        ?>
        <div class="wpmb-grid wpmb-grid-2">
            <section class="wpmb-card">
                <h2>Import / SEO / Gallery</h2>
                <form id="wpmb-settings-form" class="wpmb-form">

                    <div class="wpmb-inline-fields">
                        <label>ชื่อเรื่องสูงสุด<input type="number" name="title_max" value="<?php echo (int) $settings['title_max']; ?>" min="1"></label>
                        <label>Meta สูงสุด<input type="number" name="meta_max" value="<?php echo (int) $settings['meta_max']; ?>" min="1"></label>
                    </div>

                    <label>จำนวนเรื่องขั้นต่ำก่อน Taxonomy Index<input type="number" name="taxonomy_threshold" value="<?php echo (int) $settings['taxonomy_threshold']; ?>" min="1"></label>

                    <!-- 🚫 v1.0.15.3: ปิดระบบ Index/Noindex ถาวร -->
                    <div class="wpmb-notice wpmb-notice-warn">
                        <strong>🚫 ระบบ Index/Noindex ถูกปิดถาวร</strong><br>
                        ปลั๊กอินนี้เป็น "ผู้ส่งข้อมูล" ไม่ใช่ "ผู้ควบคุม" — จะไม่แก้ Robots ของ Taxonomy ใดๆ<br>
                        Yoast SEO / Rank Math / ธีม ยังคงเป็นเจ้าของ Robots ตามปกติ
                    </div>
                    <input type="hidden" name="taxonomy_index_control_enabled" value="0">
                    <input type="hidden" name="taxonomy_auto_index" value="0">
                    <input type="hidden" name="taxonomy_auto_noindex" value="0">

                    <fieldset>
                        <legend>Taxonomy ที่ควบคุม</legend>
                        <?php foreach (array('category' => 'Category', 'post_tag' => 'Tag', 'actors' => 'Actor', 'studio' => 'Studio') as $key => $label) : ?>
                            <label class="wpmb-check"><input type="checkbox" name="taxonomy_types[]" value="<?php echo esc_attr($key); ?>" <?php checked(in_array($key, (array) $settings['taxonomy_types'], true)); ?>><?php echo esc_html($label); ?></label>
                        <?php endforeach; ?>
                    </fieldset>

                    <label class="wpmb-switch"><input type="checkbox" name="force_local_gallery" value="1" <?php checked($settings['force_local_gallery']); ?>><span></span>บังคับ Gallery ใน XML ให้เปลี่ยนเป็นลิงก์เว็บนี้</label>
                    <label class="wpmb-switch"><input type="checkbox" name="allow_external_missing" value="1" <?php checked($settings['allow_external_missing']); ?>><span></span>อนุญาตลิงก์ต้นทางเฉพาะรูปที่หา Media คู่ไม่ได้</label>
                    <label class="wpmb-switch"><input type="checkbox" name="unused_ignore_banners" value="1" <?php checked($settings['unused_ignore_banners']); ?>><span></span>ละเว้นภาพป้ายใน Cleanup</label>

                    <fieldset>
                        <legend>ความปลอดภัย &amp; รูปแบบการเขียน Meta</legend>
                        <label class="wpmb-switch"><input type="checkbox" name="protect_theme_meta" value="1" <?php checked($settings['protect_theme_meta']); ?>><span></span><b>ปกป้อง meta ของธีม (ค่าเริ่มต้นเปิด)</b> — ห้ามเขียนทับ meta ที่มีค่าแล้ว</label>
                        <label>รูปแบบเวลา Duration ที่ธีมอ่าน<select name="duration_format">
                            <option value="auto" <?php selected($settings['duration_format'], 'auto'); ?>>ตรวจจับอัตโนมัติจากธีม (แนะนำ)</option>
                            <option value="seconds" <?php selected($settings['duration_format'], 'seconds'); ?>>วินาที (ตัวเลข)</option>
                            <option value="hms" <?php selected($settings['duration_format'], 'hms'); ?>>HH:MM:SS</option>
                            <option value="ms" <?php selected($settings['duration_format'], 'ms'); ?>>MM:SS</option>
                        </select></label>
                    </fieldset>

                    <details class="wpmb-settings-details" open>
                        <summary>กฎ ALT ทั้งหมด</summary>
                        <div class="wpmb-settings-detail-body">
                            <label>ALT Featured Image / Thumbnail<input type="text" name="alt_cover_template" value="<?php echo esc_attr($settings['alt_cover_template']); ?>"><small>ใช้ได้: {focus_or_code} {focus} {code} {actor_en} {title}</small></label>
                            <label>ALT Gallery<input type="text" name="alt_gallery_template" value="<?php echo esc_attr($settings['alt_gallery_template']); ?>"><small>ใช้ได้: {code} {actor_en} {title} {number} {role}</small></label>
                        </div>
                    </details>

                    <details class="wpmb-settings-details" open>
                        <summary>กฎจับคู่รูป 3 บทบาทและการแปลง</summary>
                        <div class="wpmb-settings-detail-body">
                            <div class="wpmb-notice">
                                <strong>จับจาก CODE ก่อน:</strong> Featured (cover/featured/thumb/main) · Gallery (CODE + เลข 1,2,3...)
                            </div>
                            <label>รูปแบบไฟล์ปลายทาง<select name="media_target_format">
                                <option value="original" <?php selected($settings['media_target_format'], 'original'); ?>>ใช้ชนิดไฟล์เดิม</option>
                                <option value="webp" <?php selected($settings['media_target_format'], 'webp'); ?>>แปลงเป็น WebP</option>
                                <option value="avif" <?php selected($settings['media_target_format'], 'avif'); ?>>แปลงเป็น AVIF</option>
                            </select></label>
                            <label>คุณภาพรูปปลายทาง<input type="number" name="media_quality" min="40" max="100" value="<?php echo (int) $settings['media_quality']; ?>"></label>
                            <label class="wpmb-switch"><input type="checkbox" name="media_keep_original" value="1" <?php checked($settings['media_keep_original']); ?>><span></span>เก็บไฟล์ต้นฉบับไว้ข้างไฟล์ที่แปลง</label>
                            <label>หากแปลงไม่ได้<select name="media_convert_fallback">
                                <option value="original" <?php selected($settings['media_convert_fallback'], 'original'); ?>>ใช้ไฟล์ต้นฉบับแทน</option>
                                <option value="stop" <?php selected($settings['media_convert_fallback'], 'stop'); ?>>ข้ามรูปและแจ้ง Error</option>
                            </select></label>
                            <label class="wpmb-switch"><input type="checkbox" name="media_gallery_fallback_cover" value="1" <?php checked($settings['media_gallery_fallback_cover']); ?>><span></span>อนุญาตใช้ Gallery ใบแรกเป็น Featured Image</label>
                        </div>
                    </details>

                    <details class="wpmb-settings-details">
                        <summary>เลือกหัวข้อมูล แหล่งข้อมูล และการล็อกปลายทาง</summary>
                        <div class="wpmb-settings-detail-body wpmb-table-wrap">
                            <div class="wpmb-notice">
                                <strong>CODE เป็นกุญแจหลัก:</strong> ระบบอ่านและล็อก CODE ไว้เสมอ
                            </div>
                            <table class="widefat striped wpmb-field-rules-table">
                                <thead><tr><th>อ่านหัวนี้</th><th>หัวข้อมูล</th><th>แหล่งข้อมูล</th><th>เมื่อตรงกับโพสต์เดิม</th></tr></thead>
                                <tbody>
                                    <tr class="wpmb-field-rule-required">
                                        <td><input type="checkbox" checked disabled></td>
                                        <th>CODE <small class="wpmb-required-badge">กุญแจหลัก</small></th>
                                        <td>อ่านจาก CODE / ชื่อไฟล์ / Mapping</td>
                                        <td>ล็อกไว้เสมอ</td>
                                    </tr>
                                    <?php foreach ($field_definitions as $field => $definition) : ?>
                                        <tr>
                                            <td><input class="wpmb-field-enabled-setting" data-field="<?php echo esc_attr($field); ?>" type="checkbox" name="field_enabled_<?php echo esc_attr($field); ?>" value="1" <?php checked(!empty($field_rules['field_enabled'][$field])); ?>></td>
                                            <th><?php echo esc_html($definition['label']); ?></th>
                                            <td><select name="field_source_<?php echo esc_attr($field); ?>"><?php foreach ($definition['sources'] as $source => $label) : ?><option value="<?php echo esc_attr($source); ?>" <?php selected($field_rules['field_source'][$field], $source); ?>><?php echo esc_html($label); ?></option><?php endforeach; ?></select></td>
                                            <td><select name="field_lock_<?php echo esc_attr($field); ?>"><?php foreach ($field_lock_options as $lock => $label) : ?><option value="<?php echo esc_attr($lock); ?>" <?php selected($field_rules['field_lock'][$field], $lock); ?>><?php echo esc_html($label); ?></option><?php endforeach; ?></select></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </details>

                    <details class="wpmb-settings-details">
                        <summary>การแสดงหัวข้อในหน้า Preview</summary>
                        <div class="wpmb-settings-detail-body wpmb-table-wrap">
                            <table class="widefat striped wpmb-field-rules-table">
                                <thead><tr><th>หัวข้อ</th><th>สถานะใน Preview</th></tr></thead>
                                <tbody>
                                    <?php foreach ($field_definitions as $field => $definition) : $pv = $this->preview_visibility_value($field); ?>
                                        <tr><th><?php echo esc_html($definition['label']); ?></th><td><select name="preview_visibility_<?php echo esc_attr($field); ?>"><option value="show" <?php selected($pv, 'show'); ?>>เปิด</option><option value="readonly" <?php selected($pv, 'readonly'); ?>>แสดงแต่ล็อก</option><option value="hide" <?php selected($pv, 'hide'); ?>>ซ่อน</option></select></td></tr>
                                    <?php endforeach; ?>
                                    <?php foreach (array('category' => 'Category', 'post_tag' => 'Tags', 'actors' => 'Actor', 'studio' => 'Studio', 'media' => 'รูปและลำดับ Gallery') as $field => $label) : $pv = $this->preview_visibility_value($field); ?>
                                        <tr><th><?php echo esc_html($label); ?></th><td><select name="preview_visibility_<?php echo esc_attr($field); ?>"><option value="show" <?php selected($pv, 'show'); ?>>เปิด</option><option value="readonly" <?php selected($pv, 'readonly'); ?>>แสดงแต่ล็อก</option><option value="hide" <?php selected($pv, 'hide'); ?>>ซ่อน</option></select></td></tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </details>

                    <button class="button button-primary wpmb-btn wpmb-btn-primary" type="submit">บันทึกการตั้งค่า</button>
                    <span id="wpmb-settings-result"></span>
                </form>
            </section>

            <section class="wpmb-card">
                <h2>System Check</h2>
                <table class="widefat striped wpmb-table">
                    <tbody>
                        <?php foreach ($checks as $label => $value) : ?>
                            <tr><th><?php echo esc_html($label); ?></th><td><?php echo esc_html($value); ?></td></tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <div class="wpmb-notice">
                    <strong>หมายเหตุ:</strong> หากเซิร์ฟเวอร์ไม่มี ZipArchive ระบบจะใช้ PclZip ที่มากับ WordPress
                </div>
            </section>
        </div>
        <?php
        $this->footer();
    }

    /* ============================================================
       🎯 PREVIEW VISIBILITY (Helper)
       ============================================================ */
    private function preview_visibility_settings() {
        $saved = get_option('wpmb_uic_settings', array());
        $saved = is_array($saved) ? $saved : array();
        $defaults = array();

        foreach (WPMB_UIC_Field_Rules::definitions() as $field => $definition) {
            $defaults[$field] = 'show';
        }
        foreach (array('category', 'post_tag', 'actors', 'studio', 'media') as $field) {
            $defaults[$field] = 'show';
        }

        $saved_visibility = isset($saved['preview_visibility']) && is_array($saved['preview_visibility'])
            ? $saved['preview_visibility']
            : array();

        foreach ($defaults as $field => $value) {
            $candidate = isset($saved_visibility[$field]) ? sanitize_key($saved_visibility[$field]) : 'show';
            $defaults[$field] = in_array($candidate, array('show', 'readonly', 'hide'), true) ? $candidate : 'show';
        }

        return $defaults;
    }

    private function preview_visibility_value($field) {
        $settings = $this->preview_visibility_settings();
        return isset($settings[$field]) ? $settings[$field] : 'show';
    }

    /* ============================================================
       📊 RENDER COUNTS (Helper)
       ============================================================ */
    private function render_counts(array $counts) {
        $labels = array(
            'total' => 'ทั้งหมด',
            'staged' => 'รอตรวจ',
            'warning' => 'มีคำเตือน',
            'ready' => 'พร้อม',
            'running' => 'กำลังทำ',
            'done' => 'สำเร็จ',
            'done_warning' => 'สำเร็จมีเตือน',
            'error' => 'Error',
            'excluded' => 'ไม่เอา',
        );

        echo '<div class="wpmb-summary">';
        foreach ($labels as $key => $label) {
            $value = (int) ($counts[$key] ?? 0);
            echo '<div class="wpmb-summary-item">';
            echo '<span class="label">' . esc_html($label) . '</span>';
            echo '<span class="value">' . $value . '</span>';
            echo '</div>';
        }
        echo '</div>';
    }

    /* ============================================================
       📁 RENDER FILES (Compact)
       ============================================================ */
    private function render_files_compact(array $files) {
        if (!$files) {
            echo '<div class="wpmb-empty">ยังไม่มีไฟล์</div>';
            return;
        }

        // สรุปแบบกระชับ
        $total_files = count($files);
        $total_items = 0;
        $total_media = 0;

        foreach ($files as $file) {
            $analysis = json_decode((string) $file['analysis'], true) ?: array();
            $total_items += isset($analysis['items']) ? (int) $analysis['items'] : 0;
            $total_media += isset($analysis['media']) ? (int) $analysis['media'] : 0;
        }

        ?>
        <div class="wpmb-summary">
            <div class="wpmb-summary-item">
                <span class="label">📄 ไฟล์</span>
                <span class="value"><?php echo (int) $total_files; ?></span>
            </div>
            <div class="wpmb-summary-item">
                <span class="label">📝 เรื่อง</span>
                <span class="value"><?php echo (int) $total_items; ?></span>
            </div>
            <div class="wpmb-summary-item">
                <span class="label">🖼️ รูป</span>
                <span class="value"><?php echo (int) $total_media; ?></span>
            </div>
            <div class="wpmb-summary-toggle">
                <button type="button" class="wpmb-toggle-btn" data-target="#wpmb-file-details">
                    <span class="wpmb-toggle-text" data-closed-text="▼ ดูรายละเอียดไฟล์ (<?php echo (int) $total_files; ?> รายการ)" data-open-text="▲ ซ่อนรายละเอียด">
                        ▼ ดูรายละเอียดไฟล์ (<?php echo (int) $total_files; ?> รายการ)
                    </span>
                </button>
            </div>
        </div>

        <div class="wpmb-file-list" id="wpmb-file-details">
            <?php foreach ($files as $file) :
                $analysis = json_decode((string) $file['analysis'], true) ?: array();
                ?>
                <details>
                    <summary>
                        <span class="wpmb-file-type"><?php echo esc_html(strtoupper($file['file_type'])); ?></span>
                        <strong><?php echo esc_html($file['original_name']); ?></strong>
                        <small style="color:#666;"><?php echo size_format((int) $file['size_bytes']); ?> · <?php echo esc_html($file['status']); ?></small>
                    </summary>
                    <div style="padding:10px 15px;background:#fff;">
                        <?php if (!empty($analysis['error'])) : ?>
                            <div class="wpmb-notice wpmb-notice-error"><?php echo esc_html($analysis['error']); ?></div>
                        <?php else : ?>
                            <p>เรื่องที่รวม: <b><?php echo (int) ($analysis['items'] ?? 0); ?></b> · รูป: <b><?php echo (int) ($analysis['media'] ?? 0); ?></b></p>
                            <?php foreach ((array) ($analysis['warnings'] ?? array()) as $warning) : ?>
                                <div class="wpmb-notice wpmb-notice-warn"><?php echo esc_html($warning); ?></div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </details>
            <?php endforeach; ?>
        </div>
        <?php
    }

    /* ============================================================
       🎯 v1.0.15.3: ADMIN COLUMN — CODE
       ============================================================ */
    public function add_code_column($columns) {
        $new = array();
        foreach ($columns as $key => $label) {
            $new[$key] = $label;
            if ($key === 'title') {
                $new['wpmb_code'] = '🔑 CODE';
            }
        }
        return $new;
    }

    public function render_code_column($column, $post_id) {
        if ($column !== 'wpmb_code') {
            return;
        }

        $code = get_post_meta($post_id, '_wpmb_code', true);

        if ($code) {
            echo '<code style="background:#f0f0f1;padding:2px 8px;border-radius:4px;font-size:12px;font-weight:600;color:#2271b1;">'
                . esc_html($code) . '</code>';
        } else {
            echo '<span style="color:#999;">—</span>';
        }
    }

    public function make_code_column_sortable($columns) {
        $columns['wpmb_code'] = 'wpmb_code';
        return $columns;
    }

    public function handle_code_column_order($query) {
        if (!is_admin() || !$query->is_main_query()) {
            return;
        }
        if ($query->get('orderby') === 'wpmb_code') {
            $query->set('meta_key', '_wpmb_code');
            $query->set('orderby', 'meta_value');
        }
    }

    /* ============================================================
       🎯 v1.0.15.3: META BOX — CODE
       ============================================================ */
    public function add_code_meta_box() {
        if (!current_user_can('manage_options')) {
            return;
        }

        add_meta_box(
            'wpmb_code_box',
            '🔑 UNI-IM-PORT — CODE',
            array($this, 'render_code_meta_box'),
            'post',
            'side',
            'high'
        );
    }

    public function render_code_meta_box($post) {
        $code = get_post_meta($post->ID, '_wpmb_code', true);
        $locked_at = get_post_meta($post->ID, '_wpmb_code_locked_at', true);

        ?>
        <div style="padding:5px 0;">
            <?php if ($code) : ?>
                <p style="margin:0 0 8px 0;">
                    <strong>CODE:</strong><br>
                    <code style="background:#f0f0f1;padding:4px 10px;border-radius:4px;font-size:13px;font-weight:700;color:#2271b1;display:inline-block;margin-top:4px;">
                        <?php echo esc_html($code); ?>
                    </code>
                </p>
                <?php if ($locked_at) : ?>
                    <p style="color:#666;font-size:11px;margin:0 0 8px 0;">
                        📅 บันทึกเมื่อ: <?php echo esc_html($locked_at); ?>
                    </p>
                <?php endif; ?>
                <p style="color:#666;font-size:11px;margin:8px 0 0 0;padding:8px;background:#fff8e5;border-left:3px solid #dba617;border-radius:3px;">
                    💡 CODE นี้ใช้จับคู่โพสต์เดิมเวลานำเข้าซ้ำ<br>
                    ⚠️ <strong>ห้ามลบหรือแก้ไข</strong> — จะทำให้ระบบสร้างโพสต์ใหม่
                </p>
            <?php else : ?>
                <p style="color:#999;font-size:12px;margin:0;">
                    ยังไม่มี CODE (โพสต์นี้ไม่ได้สร้างจาก UNI-IM-PORT)
                </p>
            <?php endif; ?>
        </div>
        <?php
    }

    /* ============================================================
       🎯 v1.0.15.3: RENDER MODALS
       ============================================================ */
    public function render_modals() {
        $page = isset($_GET['page']) ? sanitize_key($_GET['page']) : '';

        if (strpos($page, 'wpmb-uic') === false) {
            return;
        }
        ?>

        <!-- Modal: Preview -->
        <div class="wpmb-modal" id="wpmb-preview-modal" aria-hidden="true" role="dialog">
            <div class="wpmb-modal-overlay"></div>
            <div class="wpmb-modal-content">
                <div class="wpmb-modal-header">
                    <h3 class="wpmb-modal-title">
                        <span>👁️</span>
                        ดูตัวอย่าง: <code id="wpmb-preview-code">—</code>
                    </h3>
                    <div class="wpmb-modal-actions">
                        <button type="button" class="button button-primary" id="wpmb-preview-save">💾 บันทึก</button>
                        <button type="button" class="button wpmb-modal-cancel">↩️ ยกเลิก</button>
                        <button type="button" class="wpmb-modal-close" aria-label="ปิด">✕</button>
                    </div>
                </div>
                <div class="wpmb-modal-body" id="wpmb-preview-body">
                    <div class="wpmb-empty">⏳ กำลังโหลด...</div>
                </div>
            </div>
        </div>

        <!-- Modal: Edit -->
        <div class="wpmb-modal" id="wpmb-edit-modal" aria-hidden="true" role="dialog">
            <div class="wpmb-modal-overlay"></div>
            <div class="wpmb-modal-content">
                <div class="wpmb-modal-header">
                    <h3 class="wpmb-modal-title">
                        <span>✏️</span>
                        แก้ไข: <code id="wpmb-edit-code">—</code>
                    </h3>
                    <div class="wpmb-modal-actions">
                        <button type="button" class="button button-primary" id="wpmb-edit-save">💾 บันทึก</button>
                        <button type="button" class="button wpmb-modal-cancel">↩️ ยกเลิก</button>
                        <button type="button" class="wpmb-modal-close" aria-label="ปิด">✕</button>
                    </div>
                </div>
                <div class="wpmb-modal-body" id="wpmb-edit-body">
                    <div class="wpmb-empty">⏳ กำลังโหลดฟอร์ม...</div>
                </div>
            </div>
        </div>

        <?php
    }
}