<?php
class WPMB_UIC_Readers {
    public static function detect_type($path, $name = '') { return 'other'; }
    public static function analyze_file_batch($project_id, $file_id, $path, $name, $limit = 12) { return array('type' => 'other', 'done' => true, 'items' => 0, 'media' => 0); }
}
