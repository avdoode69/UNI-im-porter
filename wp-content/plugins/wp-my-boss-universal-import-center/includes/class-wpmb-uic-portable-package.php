<?php
if (!defined('ABSPATH')) {
    exit;
}

final class WPMB_UIC_Portable_Package {

    public static function build($post_ids = array()) {
        return array(
            'version' => WPMB_UIC_VERSION,
            'exported_at' => current_time('mysql'),
            'settings' => WPMB_UIC_Portable_Settings::export_settings(),
            'items' => WPMB_UIC_Exporter::export_matched_package((array) $post_ids),
        );
    }

    public static function export_zip($post_ids = array()) {
        $package = self::build($post_ids);
        $json_content = wp_json_encode($package, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        $uploads = wp_upload_dir();
        $temp_file = wp_normalize_path($uploads['basedir'] . '/wpmb-portable-' . uniqid() . '.json');

        file_put_contents($temp_file, $json_content);

        return array(
            'temp_file' => $temp_file,
            'filename' => 'uni-import-portable-' . current_time('Y-m-d-H-i-s') . '.json',
            'size' => filesize($temp_file),
        );
    }
}
