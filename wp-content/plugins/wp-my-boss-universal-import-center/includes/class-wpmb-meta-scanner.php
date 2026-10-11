<?php
if (!defined('ABSPATH')) {
    exit;
}

final class WPMB_UIC_Meta_Scanner {

    public static function scan_post_meta($post_id) {
        $meta = get_post_meta((int) $post_id);
        $keys = array_keys($meta);
        $result = array();

        foreach ($keys as $key) {
            $result[$key] = array(
                'value' => maybe_serialize($meta[$key]),
                'count' => count($meta[$key]),
            );
        }

        return $result;
    }
}
