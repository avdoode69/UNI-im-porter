<?php
/**
 * Uninstall script for WP MY BOSS Universal Import Center
 *
 * This script runs when the plugin is deleted from WordPress admin.
 * It cleans up database tables and options.
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

global $wpdb;

// ===== Define uninstall option flag =====
// ไฟล์นี้ต้องเรียก define('WPMB_UIC_UNINSTALL_DATA', true) ในไฟล์ plugin.json หรือที่อื่น
// ถ้าไม่มี flag นี้ ให้ถือว่า "เก็บข้อมูล" (soft delete)
$do_uninstall_data = apply_filters('wpmb_uic_uninstall_data', false);

if (!$do_uninstall_data) {
    // Soft delete: เก็บข้อมูล โหลด plugin ใหม่ได้
    delete_option('wpmb_uic_activation_timestamp');
    return;
}

// ===== Hard delete: ลบทั้งหมด =====

// 1. ลบตัวเลือก
delete_option('wpmb_uic_settings');
delete_option('wpmb_uic_version');
delete_option('wpmb_uic_activation_timestamp');
delete_option('wpmb_uic_openai_key');
delete_option('wpmb_uic_gemini_key');
delete_option('wpmb_uic_anthropic_key');

// 2. ลบ transients
$wpdb->query("DELETE FROM $wpdb->options WHERE option_name LIKE '%wpmb_uic%transient%'");

// 3. ลบตารางฐานข้อมูล (ถ้ามี)
$tables = array(
    $wpdb->prefix . 'wpmb_uic_imports',
    $wpdb->prefix . 'wpmb_uic_logs',
    $wpdb->prefix . 'wpmb_uic_cache',
);

foreach ($tables as $table) {
    $wpdb->query("DROP TABLE IF EXISTS $table");
}

// 4. ลบ user meta (เช่น การตั้งค่าต่อผู้ใช้)
$wpdb->query("DELETE FROM $wpdb->usermeta WHERE meta_key LIKE '%wpmb_uic%'");

// 5. ลบ post meta (ถ้ามี)
$wpdb->query("DELETE FROM $wpdb->postmeta WHERE meta_key LIKE '%wpmb_uic%'");

// 6. ลบ cron job
wp_clear_scheduled_hook('wpmb_uic_daily_maintenance');

// ===== Cleanup done =====
do_action('wpmb_uic_uninstalled');
