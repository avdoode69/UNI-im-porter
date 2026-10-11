<?php
class WPMB_UIC_SEO {
    public static function validate_payload($payload, $media = array()) { return array('status' => 'ok', 'title' => array('ok' => true), 'meta' => array('ok' => true), 'alt' => array('featured' => true, 'poster' => true, 'gallery_complete' => true, 'gallery_total' => 0)); }
    public static function fill_alt($media, $code, $actor_en, $focus, $overwrite = false, $title = '') { return $media; }
    public static function save_post_meta($post_id, $payload, $existing_post_id = 0) { return true; }
    public static function get_meta_log() { return array(); }
}
