<?php
if (!defined('ABSPATH')) {
    exit;
}

final class WPMB_UIC_Importer {

    public static function status_counts($project_id) {
        global $wpdb;
        $db = WPMB_UIC_DB::instance();
        $project_id = absint($project_id);

        return array(
            'staged' => (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$db->items} WHERE project_id=%d AND status='staged'",
                $project_id
            )),
            'validated' => (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$db->items} WHERE project_id=%d AND status='validated'",
                $project_id
            )),
            'running' => (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$db->items} WHERE project_id=%d AND status='running'",
                $project_id
            )),
            'done' => (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$db->items} WHERE project_id=%d AND status='done'",
                $project_id
            )),
            'error' => (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$db->items} WHERE project_id=%d AND status='error'",
                $project_id
            )),
            'total' => (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$db->items} WHERE project_id=%d",
                $project_id
            )),
        );
    }

    public static function import_next($project_id) {
        global $wpdb;
        $db = WPMB_UIC_DB::instance();
        $project_id = absint($project_id);

        $item = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$db->items} WHERE project_id=%d AND status='validated' ORDER BY id ASC LIMIT 1",
            $project_id
        ), ARRAY_A);

        if (!$item) {
            return array('item_id' => 0, 'message' => 'ไม่มีรายการที่จะนำเข้า');
        }

        $item_id = (int) $item['id'];
        $payload = json_decode($item['payload'], true) ?: array();
        $code = $item['code'];

        $wpdb->update(
            $db->items,
            array('status' => 'running'),
            array('id' => $item_id),
            array('%s'),
            array('%d')
        );

        $result = self::process_item($item_id, $code, $payload, $project_id);

        if (is_wp_error($result)) {
            $wpdb->update(
                $db->items,
                array('status' => 'error', 'error_text' => $result->get_error_message()),
                array('id' => $item_id),
                array('%s', '%s'),
                array('%d')
            );
        } else {
            $wpdb->update(
                $db->items,
                array('status' => 'done', 'wp_post_id' => (int) $result),
                array('id' => $item_id),
                array('%d'),
                array('%d')
            );
        }

        return array('item_id' => $item_id, 'post_id' => isset($result) && !is_wp_error($result) ? $result : 0);
    }

    private static function process_item($item_id, $code, $payload, $project_id) {
        $payload = WPMB_UIC_Field_Rules::prepare_payload($payload);

        $title = isset($payload['title']) ? trim((string) $payload['title']) : $code;
        if ($title === '') {
            $title = $code;
        }

        $slug = isset($payload['slug']) ? sanitize_title($payload['slug']) : sanitize_title($code);
        if ($slug === '') {
            $slug = sanitize_title($code);
        }

        $content = isset($payload['content']) ? wp_kses_post($payload['content']) : '';
        $status = isset($payload['status']) && in_array($payload['status'], array('draft', 'publish', 'pending'), true) ? $payload['status'] : 'draft';

        $post_args = array(
            'post_title'   => $title,
            'post_content' => $content,
            'post_status'  => $status,
            'post_type'    => 'post',
            'post_name'    => $slug,
        );

        if (isset($payload['post_date']) && !empty($payload['post_date'])) {
            $post_args['post_date'] = $payload['post_date'];
        }

        $post_id = wp_insert_post($post_args);

        if (is_wp_error($post_id)) {
            return $post_id;
        }

        if (WPMB_UIC_Field_Rules::can_write('category', $post_id)) {
            if (isset($payload['taxonomies']['category'])) {
                WPMB_UIC_Taxonomy::assign_terms($post_id, array('category' => $payload['taxonomies']['category']));
            }
        }

        if (WPMB_UIC_Field_Rules::can_write('post_tag', $post_id)) {
            if (isset($payload['taxonomies']['post_tag'])) {
                WPMB_UIC_Taxonomy::assign_terms($post_id, array('post_tag' => $payload['taxonomies']['post_tag']));
            }
        }

        if (WPMB_UIC_Field_Rules::can_write('actors', $post_id)) {
            if (isset($payload['taxonomies']['actors'])) {
                WPMB_UIC_Taxonomy::assign_terms($post_id, array('actors' => $payload['taxonomies']['actors']));
            }
        }

        if (WPMB_UIC_Field_Rules::can_write('studio', $post_id)) {
            if (isset($payload['taxonomies']['studio'])) {
                WPMB_UIC_Taxonomy::assign_terms($post_id, array('studio' => $payload['taxonomies']['studio']));
            }
        }

        WPMB_UIC_SEO::save_post_meta($post_id, $payload);
        update_post_meta($post_id, '_wpmb_code', WPMB_UIC_Code::normalize_code($code));

        return $post_id;
    }

    public static function validate_item($item_id) {
        global $wpdb;
        $db = WPMB_UIC_DB::instance();
        $item_id = absint($item_id);

        $item = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$db->items} WHERE id=%d",
            $item_id
        ), ARRAY_A);

        if (!$item) {
            return new WP_Error('not_found', 'ไม่พบรายการ');
        }

        $payload = json_decode($item['payload'], true) ?: array();
        $validation = WPMB_UIC_SEO::validate_payload($payload);

        $wpdb->update(
            $db->items,
            array('status' => 'validated', 'validation' => wp_json_encode($validation)),
            array('id' => $item_id),
            array('%s', '%s'),
            array('%d')
        );

        return $validation;
    }
}
