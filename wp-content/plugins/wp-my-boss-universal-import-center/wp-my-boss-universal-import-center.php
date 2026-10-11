<?php
/**
 * Plugin Name: WP MY BOSS — Universal Import Center
 * Plugin URI: https://wordpress.org/plugins/
 * Description: Universal Import Center — ผู้ส่งข้อมูล (Courier) ไม่ใช่ผู้ควบคุมเว็บไซต์ | นำเข้าจาก ZIP/CS[...]
 * Version: 1.0.15.3
 * Author: WP MY BOSS / UNI-IM-PORT
 * License: GPLv2 or later
 * Text Domain: wp-my-boss-universal-import-center
 * Requires at least: 5.8
 * Requires PHP: 7.4
 *
 * ═══════════════════════════════════════════════════════════════
 * 📜 หลักการทำงานหลัก (Core Principle)
 * ═══════════════════════════════════════════════════════════════
 *
 * 1. ปลั๊กอินนี้เป็น "ผู้ส่งข้อมูล" (Courier) ไม่ใช่ "ผู้สร้าง" หรือ
 *    "ผู้ควบคุม" ของเว็บไซต์
 *
 * 2. ข้อมูลทุกอย่างที่มีอยู่ในเว็บไซต์แล้ว ถือว่ามีอำนาจสูงสุด
 *    (Highest Authority) — ห้ามแก้ ห้ามลบ ห้ามทับ
 *    ยกเว้นผู้ใช้สั่งโดยตรง
 *
 * 3. ก่อน Import ทุกครั้ง ต้องตรวจสอบว่ามีข้อมูลซ้ำหรือไม่
 *    ถ้ามี → ใช้ของเดิม ไม่สร้างใหม่
 *
 * 4. การจับคู่ข้อมูลซ้ำต้องดูจาก "ชื่อที่ normalize แล้ว"
 *    และเลือกตัวที่มี "จำนวนโพสต์มากที่สุด" เป็นหลัก
 *
 * 5. ปลั๊กอินไม่แก้ Robots / SEO / Index / Config ใดๆ ของเว็บไซต์
 *    — ปล่อยให้เจ้าของเว็บไซต์จัดการเอง
 *
 * ═══════════════════════════════════════════════════════════════
 * 🎯 Meta Key ที่เขียนจริง (7 Keys)
 * ═══════════════════════════════════════════════════════
 *
 * ✅ _yoast_wpseo_focuskw        (Focus Keyphrase)
 * ✅ _yoast_wpseo_metadesc       (Meta Description)
 * ✅ duration                     (วินาที)
 * ✅ video_url                    (ลิงก์วิดีโอ)
 * ✅ wpmb_gallery_ids             (Gallery IDs)
 * ✅ _thumbnail_id                (Featured Image — WP Core)
 * ✅ _wpmb_code                   (Plugin Internal — Private Meta)
 *
 * ═══════════════════════════════════════════════════════════════
 * 🚫 Meta Key ที่ปิดถาวร (14 Keys)
 * ═══════════════════════════════════════════════════════
 *
 * Rank Math (5): rank_math_focus_keyword, rank_math_description,
 *                rank_math_title, rank_math_canonical_url,
 *                rank_math_robots
 * Poster (4):    _avfp_poster_id, _wps_42_poster_id,
 *                wps_poster_id, wpmb_poster_id
 * Legacy (5):    _acs_code, acs_code, _acs_duration_seconds,
 *                _acs_video_url, _acs_actor_en, _acs_gallery_ids
 *
 * ═══════════════════════════════════════════════════════
 * 🔒 ระบบ Index/Noindex
 * ═══════════════════════════════════════════════════════
 *
 * ปิดถาวร 100% ตั้งแต่ v1.0.15.3
 * Yoast SEO / Rank Math / ธีม ยังคงเป็นเจ้าของ Robots ตามปกติ
 *
 * ═══════════════════════════════════════════════════════
 * 📚 เอกสารเพิ่มเติม
 * ═══════════════════════════════════════════════════════
 *
 * - PLUGIN_POLICY_TH.md               (นโยบายอย่างเป็นทางการ)
 * - PATCH_NOTES_v1.0.15.3_TH.txt      (Release Notes)
 *
 * ═══════════════════════════════════════════════════════════════
 *                    © 2026 WP MY BOSS / UNI-IM-PORT
 * ═══════════════════════════════════════════════════════════════
 */

if (!defined('ABSPATH')) {
    exit;
}

/* ============================================================
   📌 Constants
   ============================================================ */
define('WPMB_UIC_VERSION', '1.0.15.3');
define('WPMB_UIC_FILE', __FILE__);
define('WPMB_UIC_PATH', plugin_dir_path(__FILE__));
define('WPMB_UIC_URL', plugin_dir_url(__FILE__));

/* ============================================================
   📦 Load Classes
   ============================================================
   ใช้ class_exists guard ป้องกันการประกาศคลาสซ้ำ
   ถ้า main plugin โหลดไปแล้ว
   ============================================================ */
$wpmb_uic_classes = array(
    'class-wpmb-db.php',
    'class-wpmb-code.php',
    'class-wpmb-field-rules.php',
    'class-wpmb-meta-scanner.php',
    'class-wpmb-seo.php',
    'class-wpmb-taxonomy.php',
    'class-wpmb-media.php',
    'class-wpmb-readers.php',
    'class-wpmb-importer.php',
    'class-wpmb-exporter.php',
    'class-wpmb-ai-bridge.php',
    'class-wpmb-portable-settings.php',
    'class-wpmb-workpad-batch-api.php',
    'class-wpmb-uic-portable-package.php',
    'class-wpmb-compat.php',
    'class-wpmb-ajax.php',
    'class-wpmb-admin.php',
);

foreach ($wpmb_uic_classes as $file) {
    $path = WPMB_UIC_PATH . 'includes/' . $file;
    if (file_exists($path)) {
        require_once $path;
    }
}

/* ============================================================
   🔌 Activation Hook — สร้างตารางฐานข้อมูล
   ============================================================ */
register_activation_hook(__FILE__, array('WPMB_UIC_DB', 'activate'));

/* ============================================================
   🚀 Init ตอน plugins_loaded
   ============================================================ */
add_action('plugins_loaded', function () {

    // สร้างตารางถ้ายังไม่มี (กรณี update ไม่ได้ activate ใหม่)
    if (get_option('wpmb_uic_db_version') !== WPMB_UIC_VERSION) {
        if (class_exists('WPMB_UIC_DB')) {
            WPMB_UIC_DB::activate();
            update_option('wpmb_uic_db_version', WPMB_UIC_VERSION);
        }
    }

    // Init DB
    if (class_exists('WPMB_UIC_DB')) {
        WPMB_UIC_DB::instance();

        if (method_exists('WPMB_UIC_DB', 'ensure_settings_defaults')) {
            WPMB_UIC_DB::ensure_settings_defaults();
        }
    }

    // Compatibility Shield
    if (class_exists('WPMB_UIC_Compat')) {
        WPMB_UIC_Compat::register();
    }

    // Init Admin + AJAX (เฉพาะใน Admin หรือ AJAX)
    if (is_admin() || defined('DOING_AJAX')) {
        if (class_exists('WPMB_UIC_Admin')) {
            WPMB_UIC_Admin::instance();
        }
        if (class_exists('WPMB_UIC_Ajax')) {
            WPMB_UIC_Ajax::instance();
        }
    }

    // Init Taxonomy
    if (class_exists('WPMB_UIC_Taxonomy')) {
        WPMB_UIC_Taxonomy::instance();
    }
});
