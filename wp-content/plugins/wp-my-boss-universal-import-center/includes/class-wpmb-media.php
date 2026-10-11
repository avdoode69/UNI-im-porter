<?php
if (!defined('ABSPATH')) {
    exit;
}

final class WPMB_UIC_Media {

    public static function instance() {
        return new self();
    }

    public static function attach_media($post_id, $media_path, $title = '') {
        if (empty($media_path) || !file_exists($media_path)) {
            return 0;
        }

        $filetype = wp_check_filetype(basename($media_path), null);
        $title = trim((string) $title);
        if ($title === '') {
            $title = basename($media_path);
        }

        $attachment = array(
            'post_mime_type' => $filetype['type'] ?: 'image/jpeg',
            'post_title' => $title,
            'post_content' => '',
            'post_status' => 'inherit',
        );

        $attachment_id = media_sideload_image($media_path, $post_id, $title, 'id');
        if (is_wp_error($attachment_id)) {
            return 0;
        }

        return (int) $attachment_id;
    }

    public static function resolve_gallery_ids($post_id, $media_items = array()) {
        $ids = array();
        foreach ((array) $media_items as $item) {
            $id = is_array($item) && !empty($item['attachment_id']) ? (int) $item['attachment_id'] : 0;
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        if (!empty($ids)) {
            update_post_meta($post_id, 'wpmb_gallery_ids', $ids);
        }

        return $ids;
    }
}
