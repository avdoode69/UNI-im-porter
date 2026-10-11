<?php
if (!defined('ABSPATH')) {
    exit;
}

final class WPMB_UIC_Field_Rules {

    public static function prepare_payload($payload) {
        if (!is_array($payload)) {
            return array();
        }

        $payload = wp_parse_args($payload, array(
            'title' => '',
            'slug' => '',
            'content' => '',
            'status' => 'draft',
            'taxonomies' => array(),
            'meta' => array(),
        ));

        if (!isset($payload['taxonomies']) || !is_array($payload['taxonomies'])) {
            $payload['taxonomies'] = array();
        }

        if (!isset($payload['meta']) || !is_array($payload['meta'])) {
            $payload['meta'] = array();
        }

        return $payload;
    }

    public static function can_write($field_name, $post_id = 0) {
        $settings = get_option('wpmb_uic_settings', array());

        if (!empty($settings['allow_blank_overwrite']) && !empty($settings['field_rules'][$field_name])) {
            return (bool) $settings['field_rules'][$field_name];
        }

        $allowed = array('category', 'post_tag', 'actors', 'studio', 'meta');
        if (!in_array($field_name, $allowed, true)) {
            return false;
        }

        return true;
    }

    public static function get_mapping() {
        return array(
            'focus' => array('_yoast_wpseo_focuskw'),
            'meta_description' => array('_yoast_wpseo_metadesc'),
            'duration' => array('duration'),
            'video_url' => array('video_url'),
            'gallery_ids' => array('wpmb_gallery_ids'),
        );
    }
}
