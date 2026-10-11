<?php
class WPMB_UIC_Meta_Scanner {
    public static function detect_field_map() { return array('fields' => array(), 'all_meta_keys' => array()); }
    public static function get_mapping() { return array(); }
    public static function field_labels() { return array(); }
    public static function save_mapping($mapping) { return $mapping; }
    public static function reset_mapping() { return true; }
}
