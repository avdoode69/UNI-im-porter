<?php
class WPMB_UIC_Media {
    public static function find_for_item($code, array $payload, $project_id = 0) { return array(); }
    public static function expected_from_payload(array $payload) { return array(); }
    public static function merge_library_preferred($staged, $library, $expected) { return array('media' => $staged, 'audit' => array('complete' => true, 'missing_count' => 0, 'library_attachments' => 0, 'pending_import_files' => 0)); }
    public static function upload_staged_media($media, $code, $actor_en, $focus) { return $media; }
    public static function normalize_roles_and_sequences($media) { return $media; }
    public static function choose_media_roles($media, $fallback_cover = true) { return array(array(), array(), array()); }
    public static function rewrite_gallery_to_local($content, $media, $force_local, $source_urls) { return array('content' => $content, 'unresolved' => array()); }
    public static function build_gallery_block($gallery, $columns = 2) { return ''; }
    public static function content_to_gutenberg_blocks($content) { return $content; }
    public static function scan_media_batch($offset = 0, $limit = 100) { return array('processed' => 0, 'next_offset' => 0, 'total' => 0, 'done' => true); }
    public static function unused_candidates($limit = 50, $ignore = true) { return array(); }
    public static function trash_unused($ids, $ignore = true) { return array('deleted' => 0, 'failed' => array()); }
}
