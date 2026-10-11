<?php
/**
 * Plugin Name: WP MY BOSS — Universal Import Center
 * Plugin URI: https://wordpress.org/plugins/
 * Description: Universal Import Center — ผู้ส่งข้อมูล (Courier) ไม่ใช่ผู้ควบคุมเว็บไซต์ | นำเข้าจาก ZIP/CSV/XLSX/XML/JSON/TXT
 * Version: 1.0.15.3
 * Author: WP MY BOSS / UNI-IM-PORT
 * License: GPLv2 or later
 * Text Domain: wp-my-boss-universal-import-center
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 *
 * ═══════════════════════════════════════════════════════════════
 * 📜 หลักการทำงานหลัก (Core Principle)
 * ═══════════════════════════════════════════════════════════════
 *
 * 1. ปลั๊กอินนี้เป็น "ผู้ส่งข้อมูล" (Courier) ไม่ใช่ "ผู้สร้าง" หรือ "ผู้ควบคุม"
 * 2. ข้อมูลทุกอย่างที่มีอยู่ในเว็บไซต์แล้ว ถือว่ามีอำนาจสูงสุด
 * 3. ก่อน Import ทุกครั้ง ต้องตรวจสอบว่ามีข้อมูลซ้ำหรือไม่
 * 4. การจับคู่ข้อมูลซ้ำต้องดูจาก "ชื่อที่ normalize แล้ว"
 * 5. ปลั๊กอินไม่แก้ Robots / SEO / Index / Config ใดๆ ของเว็บไซต์
 *
 * © 2026 WP MY BOSS / UNI-IM-PORT
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

// 🆕 PATCH: Alias สำหรับ portable-package + textdomain
if (!defined('WPMB_UIC_DIR')) {
    define('WPMB_UIC_DIR', WPMB_UIC_PATH);
}
if (!defined('WPMB_UIC_BASENAME')) {
    define('WPMB_UIC_BASENAME', plugin_basename(__FILE__));
}

/* ============================================================
   📦 Load Classes
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
    } elseif (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('[UNI-IM-PORT] Missing include: ' . $path);
    }
}
unset($wpmb_uic_classes, $file, $path);

/* ============================================================
   🔌 Activation Hook
   ============================================================ */
register_activation_hook(__FILE__, array('WPMB_UIC_DB', 'activate'));

/* ============================================================
   🔌 Deactivation Hook — 🆕 PATCH
   ============================================================ */
register_deactivation_hook(__FILE__, function () {
    wp_clear_scheduled_hook('wpmb_uic_daily_maintenance');
});

/* ============================================================
   🌐 Text Domain — 🆕 PATCH
   ============================================================ */
add_action('init', function () {
    load_plugin_textdomain(
        'wp-my-boss-universal-import-center',
        false,
        dirname(WPMB_UIC_BASENAME) . '/languages'
    );
});

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

    // Compatibility Shield (Subtitle MIME fix)
    if (class_exists('WPMB_UIC_Compat')) {
        WPMB_UIC_Compat::register();
    }

    // Admin + AJAX
    if (is_admin() || defined('DOING_AJAX')) {
        if (class_exists('WPMB_UIC_Admin')) {
            WPMB_UIC_Admin::instance();
        }
        if (class_exists('WPMB_UIC_Ajax')) {
            WPMB_UIC_Ajax::instance();
        }
    }

    // Taxonomy
    if (class_exists('WPMB_UIC_Taxonomy')) {
        WPMB_UIC_Taxonomy::instance();
    }

    // ═══════════════════════════════════════════════════════
    // 🆕 PATCH: Static registrars (ผูก hook ใน register())
    // ═══════════════════════════════════════════════════════
    foreach (array(
        'WPMB_UIC_Exporter'          => 'register',  // admin_post: export_matched_package
        'WPMB_UIC_Portable_Settings' => 'register',  // admin_post: export_portable_settings
        'WPMB_UIC_WorkPad_Batch_API' => 'register',  // rest_api_init
    ) as $class => $method) {
        if (class_exists($class) && method_exists($class, $method)) {
            call_user_func(array($class, $method));
        }
    }

    // ═══════════════════════════════════════════════════════
    // 🆕 PATCH: Singleton instances (ผูก hook ใน constructor)
    // ═══════════════════════════════════════════════��═══════
    foreach (array(
        'WPMB_UIC_Media',              // admin + frontend (the_content filter)
        'WPMB_UIC_SEO',                // singleton
        'WPMB_UIC_AI_Bridge',          // admin + AJAX (wp_ajax_wpmb_uic_ai_*)
        'WPMB_UIC_Portable_Package',   // 8 admin_post handlers
    ) as $class) {
        if (class_exists($class) && method_exists($class, 'instance')) {
            call_user_func(array($class, 'instance'));
        }
    }
});

/* ============================================================
   🎨 Frontend Assets — 🆕 PATCH
   ============================================================ */
add_action('wp_enqueue_scripts', function () {
    wp_register_style(
        'wpmb-uic-gallery',
        WPMB_UIC_URL . 'assets/frontend.css',
        array(),
        WPMB_UIC_VERSION
    );
    wp_register_script(
        'wpmb-uic-gallery',
        WPMB_UIC_URL . 'assets/frontend.js',
        array(),
        WPMB_UIC_VERSION,
        true
    );
});

/* ============================================================
   🧹 Cron: ล้าง Log เก่า 30 วัน — 🆕 PATCH
   ============================================================ */
add_action('wpmb_uic_daily_maintenance', function () {
    global $wpdb;
    if (!class_exists('WPMB_UIC_DB')) {
        return;
    }
    $db = WPMB_UIC_DB::instance();
    $cutoff = gmdate('Y-m-d H:i:s', time() - 30 * DAY_IN_SECONDS);
    $wpdb->query($wpdb->prepare(
        "DELETE FROM {$db->logs} WHERE created_at < %s",
        $cutoff
    ));
});
