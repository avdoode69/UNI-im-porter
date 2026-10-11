<?php
if (!defined('ABSPATH')) {
    exit;
}

final class WPMB_UIC_SEO {

    public static function validate_payload($payload) {
        $payload = WPMB_UIC_Field_Rules::prepare_payload($payload);

        $result = array(
            'valid' => true,
            'warnings' => array(),
            'blocked' => array(),
            'meta' => array(),
        );

        foreach ($payload['meta'] as $key => $value) {
            if (self::is_blocked_meta_key($key)) {
                $result['blocked'][] = $key;
                $result['warnings'][] = 'Meta key ถูกปิดไว้: ' . $key;
            } else {
                $result['meta'][$key] = $value;
            }
        }

        if (!empty($result['blocked'])) {
            $result['valid'] = false;
        }

        return $result;
    }

    public static function is_blocked_meta_key($key) {
        $blocked = array(
            'rank_math_focus_keyword',
            'rank_math_description',
            'rank_math_title',
            'rank_math_canonical_url',
            'rank_math_robots',
            '_avfp_poster_id',
            '_wps_42_poster_id',
            'wps_poster_id',
            'wpmb_poster_id',
            '_acs_code',
            'acs_code',
            '_acs_duration_seconds',
            '_acs_video_url',
            '_acs_actor_en',
            '_acs_gallery_ids',
        );

        return in_array((string) $key, $blocked, true);
    }

    public static function save_post_meta($post_id, $payload) {
        $payload = WPMB_UIC_Field_Rules::prepare_payload($payload);

        if (!isset($payload['meta']) || !is_array($payload['meta'])) {
            return true;
        }

        foreach ($payload['meta'] as $key => $value) {
            if (self::is_blocked_meta_key($key)) {
                continue;
            }

            if (is_array($value)) {
                $value = wp_json_encode($value, JSON_UNESCAPED_UNICODE);
            }

            update_post_meta((int) $post_id, (string) $key, $value);
        }

        if (!empty($payload['title'])) {
            update_post_meta((int) $post_id, '_yoast_wpseo_focuskw', $payload['title']);
        }

        if (!empty($payload['content'])) {
            update_post_meta((int) $post_id, '_yoast_wpseo_metadesc', wp_trim_words($payload['content'], 20));
        }

        return true;
    }
}
