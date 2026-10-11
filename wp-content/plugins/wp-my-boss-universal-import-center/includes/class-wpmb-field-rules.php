<?php
if (!defined('ABSPATH')) {
    exit;
}

final class WPMB_UIC_Field_Rules {
    public static function definitions() {
        return array(
            'title' => array(
                'label' => 'Title',
                'sources' => array(
                    'import' => 'จากไฟล์/Preview',
                    'code' => 'ใช้ CODE',
                    'existing' => 'ใช้ค่าที่มีใน WordPress',
                ),
            ),
            'slug' => array(
                'label' => 'Slug',
                'sources' => array(
                    'import' => 'จากไฟล์/Preview',
                    'code' => 'ใช้ CODE เท่านั้น',
                    'title' => 'สร้างจาก Title',
                    'code_title' => 'CODE + Title',
                    'existing' => 'ใช้ Slug เดิมใน WordPress',
                ),
            ),
            'content' => array(
                'label' => 'Content',
                'sources' => array(
                    'import' => 'จากไฟล์/Preview',
                    'existing' => 'ใช้เนื้อหาเดิมใน WordPress',
                    'blank' => 'เว้นว่าง',
                ),
            ),
            'focus' => array(
                'label' => 'Focus Keyphrase',
                'sources' => array(
                    'import' => 'จากไฟล์/Preview',
                    'existing' => 'ใช้ Focus Keyphrase เดิมใน WordPress',
                ),
            ),
            'meta_description' => array(
                'label' => 'Meta Description',
                'sources' => array(
                    'import' => 'จากไฟล์/Preview',
                    'existing' => 'ใช้ Meta Description เดิมใน WordPress',
                ),
            ),
            'actor_en' => array(
                'label' => 'Actor EN',
                'sources' => array(
                    'import' => 'จากไฟล์/Preview',
                    'actors' => 'ดึงชื่ออังกฤษจาก Actor',
                    'existing' => 'ใช้ค่าเดิมใน WordPress',
                    'blank' => 'เว้นว่าง',
                ),
            ),
            'status' => array(
                'label' => 'Post Status',
                'sources' => array(
                    'import' => 'จากไฟล์/Preview',
                    'draft' => 'บังคับ Draft',
                    'publish' => 'บังคับ Publish',
                    'existing' => 'ใช้สถานะเดิมใน WordPress',
                ),
            ),
            'post_date' => array(
                'label' => 'Post Date',
                'sources' => array(
                    'import' => 'จากไฟล์/Preview',
                    'existing' => 'ใช้วันที่เดิมใน WordPress',
                    'blank' => 'ให้ WordPress กำหนด',
                ),
            ),
            'duration' => array(
                'label' => 'Duration Seconds',
                'sources' => array(
                    'import' => 'จากไฟล์/Preview',
                    'existing' => 'ใช้ค่าเดิมใน WordPress',
                    'blank' => 'เว้นว่าง',
                ),
            ),
            'video_url' => array(
                'label' => 'Video URL',
                'sources' => array(
                    'import' => 'จากไฟล์/Preview',
                    'existing' => 'ใช้ค่าเดิมใน WordPress',
                    'blank' => 'เว้นว่าง',
                ),
            ),
            'category' => array(
                'label' => 'Category',
                'sources' => array(
                    'import' => 'จากไฟล์/Preview',
                    'existing' => 'ใช้หมวดเดิมใน WordPress',
                    'blank' => 'ไม่กำหนด',
                ),
            ),
            'post_tag' => array(
                'label' => 'Tags',
                'sources' => array(
                    'import' => 'จากไฟล์/Preview',
                    'existing' => 'ใช้แท็กเดิมใน WordPress',
                    'blank' => 'ไม่กำหนด',
                ),
            ),
            'actors' => array(
                'label' => 'Actor',
                'sources' => array(
                    'import' => 'จากไฟล์/Preview',
                    'existing' => 'ใช้ Actor เดิมใน WordPress',
                    'blank' => 'ไม่กำหนด',
                ),
            ),
            'studio' => array(
                'label' => 'Studio',
                'sources' => array(
                    'import' => 'จากไฟล์/Preview',
                    'existing' => 'ใช้ Studio เดิมใน WordPress',
                    'blank' => 'ไม่กำหนด',
                ),
            ),
        );
    }

    public static function lock_options() {
        return array(
            'overwrite' => 'เขียนทับได้ตามค่าที่เลือก',
            'fill_empty' => 'เขียนเฉพาะเมื่อปลายทางว่าง',
            'preserve' => 'ถ้ามีโพสต์เดิมให้รักษาค่าเดิม',
        );
    }

    public static function defaults() {
        $enabled = array();
        $source = array();
        $lock = array();

        foreach (self::definitions() as $field => $definition) {
            $enabled[$field] = 1;
            $source[$field] = 'import';
            $lock[$field] = 'overwrite';
        }

        return array(
            'field_enabled' => $enabled,
            'field_source' => $source,
            'field_lock' => $lock,
        );
    }

    public static function settings() {
        $defaults = self::defaults();
        $saved = get_option('wpmb_uic_settings', array());
        $saved = is_array($saved) ? $saved : array();

        $enabled = isset($saved['field_enabled']) && is_array($saved['field_enabled']) ? $saved['field_enabled'] : array();
        $source = isset($saved['field_source']) && is_array($saved['field_source']) ? $saved['field_source'] : array();
        $lock = isset($saved['field_lock']) && is_array($saved['field_lock']) ? $saved['field_lock'] : array();

        $definitions = self::definitions();

        foreach ($definitions as $field => $definition) {
            $enabled[$field] = array_key_exists($field, $enabled)
                ? (!empty($enabled[$field]) ? 1 : 0)
                : 1;

            $candidate_source = isset($source[$field]) ? sanitize_key($source[$field]) : 'import';
            $source[$field] = array_key_exists($candidate_source, $definition['sources'])
                ? $candidate_source
                : 'import';

            $candidate_lock = isset($lock[$field]) ? sanitize_key($lock[$field]) : 'overwrite';
            $lock[$field] = array_key_exists($candidate_lock, self::lock_options())
                ? $candidate_lock
                : 'overwrite';
        }

        return array(
            'field_enabled' => $enabled,
            'field_source' => $source,
            'field_lock' => $lock,
        );
    }

    public static function is_enabled($field) {
        $settings = self::settings();
        return !empty($settings['field_enabled'][$field]);
    }

    public static function source($field) {
        $settings = self::settings();
        return isset($settings['field_source'][$field]) ? $settings['field_source'][$field] : 'import';
    }

    public static function lock($field) {
        $settings = self::settings();
        return isset($settings['field_lock'][$field]) ? $settings['field_lock'][$field] : 'overwrite';
    }

    public static function prepare_payload(array $payload, $post_id = 0) {
        $post_id = absint($post_id);
        $disabled = array();

        foreach (self::definitions() as $field => $definition) {
            if (!self::is_enabled($field)) {
                $disabled[] = $field;
                continue;
            }

            $source = self::source($field);
            if ($source === 'import') {
                continue;
            }

            $value = self::resolve_source_value($field, $source, $payload, $post_id);
            self::set_payload_value($payload, $field, $value);
        }

        $payload['_wpmb_disabled_fields'] = $disabled;
        $payload['_wpmb_field_rules_applied'] = current_time('mysql');

        return $payload;
    }

    public static function can_write($field, $post_id = 0) {
        $post_id = absint($post_id);

        if (!self::is_enabled($field)) {
            return false;
        }

        if (!$post_id) {
            return true;
        }

        if (self::source($field) === 'existing') {
            return false;
        }

        $lock = self::lock($field);

        if ($lock === 'preserve') {
            return false;
        }

        if ($lock === 'fill_empty') {
            return !self::has_value(self::existing_value($post_id, $field));
        }

        return true;
    }

    public static function payload_value(array $payload, $field) {
        if (in_array($field, array('category', 'post_tag', 'actors', 'studio'), true)) {
            return isset($payload['taxonomies'][$field]) ? $payload['taxonomies'][$field] : array();
        }
        return isset($payload[$field]) ? $payload[$field] : '';
    }

    public static function set_payload_value(array &$payload, $field, $value) {
        if (in_array($field, array('category', 'post_tag', 'actors', 'studio'), true)) {
            if (!isset($payload['taxonomies']) || !is_array($payload['taxonomies'])) {
                $payload['taxonomies'] = array();
            }
            $payload['taxonomies'][$field] = is_array($value)
                ? array_values($value)
                : ($value === '' ? array() : array($value));
            return;
        }

        if ($field === 'slug') {
            $payload[$field] = sanitize_title((string) $value);
            return;
        }

        if ($field === 'status') {
            $allowed = array('draft', 'publish', 'pending', 'private', 'future');
            $payload[$field] = in_array($value, $allowed, true) ? $value : 'draft';
            return;
        }

        $payload[$field] = $value;
    }

    public static function existing_value($post_id, $field) {
        $post_id = absint($post_id);

        if (!$post_id) {
            return in_array($field, array('category', 'post_tag', 'actors', 'studio'), true) ? array() : '';
        }

        $post = get_post($post_id);
        if (!$post) {
            return in_array($field, array('category', 'post_tag', 'actors', 'studio'), true) ? array() : '';
        }

        switch ($field) {
            case 'title':
                return (string) $post->post_title;
            case 'slug':
                return (string) $post->post_name;
            case 'content':
                return (string) $post->post_content;
            case 'status':
                return (string) $post->post_status;
            case 'post_date':
                return (string) $post->post_date;
            case 'focus':
                return self::first_meta($post_id, array('_yoast_wpseo_focuskw'));
            case 'meta_description':
                return self::first_meta($post_id, array('_yoast_wpseo_metadesc'));
            case 'actor_en':
                return self::first_meta($post_id, array('_acs_actor_en', 'actor_en', 'acs_actor_en', '_actor_en', 'actor_english', '_wpmb_actor_en'));
            case 'duration':
                return self::first_meta($post_id, array('_acs_duration_seconds', 'duration', 'duration_seconds'));
            case 'video_url':
                return self::first_meta($post_id, array('_acs_video_url', 'video_url', 'embed_url'));
            case 'category':
            case 'post_tag':
            case 'actors':
            case 'studio':
                $taxonomy = WPMB_UIC_Taxonomy::actual_taxonomy($field);
                $terms = taxonomy_exists($taxonomy) ? wp_get_object_terms($post_id, $taxonomy, array('fields' => 'names')) : array();
                return is_wp_error($terms) ? array() : array_values($terms);
        }

        return '';
    }

    private static function resolve_source_value($field, $source, array $payload, $post_id) {
        if ($source === 'existing') {
            return self::existing_value($post_id, $field);
        }

        if ($source === 'blank') {
            return in_array($field, array('category', 'post_tag', 'actors', 'studio'), true) ? array() : '';
        }

        if ($source === 'code') {
            return isset($payload['code']) ? (string) $payload['code'] : '';
        }

        if ($source === 'title') {
            return isset($payload['title']) ? (string) $payload['title'] : '';
        }

        if ($source === 'code_title') {
            $code = isset($payload['code']) ? (string) $payload['code'] : '';
            $title = isset($payload['title']) ? (string) $payload['title'] : '';
            return trim($code . '-' . $title, '- ');
        }

        if ($source === 'actors') {
            $names = isset($payload['taxonomies']['actors']) ? (array) $payload['taxonomies']['actors'] : array();
            $english = array();
            foreach ($names as $name) {
                $name = trim((string) $name);
                if ($name !== '' && !preg_match('/[\x{0E00}-\x{0E7F}]/u', $name)) {
                    $english[] = $name;
                }
            }
            return implode(', ', array_values(array_unique($english)));
        }

        if (in_array($source, array('draft', 'publish'), true)) {
            return $source;
        }

        return self::payload_value($payload, $field);
    }

    private static function first_meta($post_id, array $keys) {
        foreach ($keys as $key) {
            $value = get_post_meta($post_id, $key, true);
            if (self::has_value($value)) {
                return $value;
            }
        }
        return '';
    }

    private static function has_value($value) {
        if (is_array($value)) {
            return count(array_filter($value, function ($entry) {
                return trim((string) $entry) !== '';
            })) > 0;
        }
        return trim((string) $value) !== '';
    }
}
