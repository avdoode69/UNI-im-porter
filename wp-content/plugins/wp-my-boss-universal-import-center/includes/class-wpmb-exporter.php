<?php
if (!defined('ABSPATH')) {
    exit;
}

final class WPMB_UIC_Exporter {

    public static function export_matched_package($post_ids = array(), $include_media = true) {
        $package = array(
            'version' => WPMB_UIC_VERSION,
            'exported_at' => current_time('mysql'),
            'items' => array(),
            'media' => array(),
        );

        $post_ids = array_filter(array_map('absint', (array) $post_ids));

        if (empty($post_ids)) {
            return $package;
        }

        foreach ($post_ids as $post_id) {
            $post = get_post($post_id);
            if (!$post) {
                continue;
            }

            $code = get_post_meta($post_id, '_wpmb_code', true);
            if (!$code) {
                $code = WPMB_UIC_Code::extract_code($post->post_title . ' ' . $post->post_name);
            }

            if ($code === '') {
                continue;
            }

            $item = array(
                'code' => $code,
                'title' => $post->post_title,
                'slug' => $post->post_name,
                'content' => $post->post_content,
                'status' => $post->post_status,
                'post_date' => $post->post_date,
                'taxonomies' => array(),
                'meta' => array(),
            );

            // Taxonomies
            foreach (array('category', 'post_tag', 'actors', 'studio') as $taxonomy) {
                $actual_taxonomy = WPMB_UIC_Taxonomy::actual_taxonomy($taxonomy);
                if (taxonomy_exists($actual_taxonomy)) {
                    $terms = wp_get_object_terms($post_id, $actual_taxonomy, array('fields' => 'names'));
                    if (!is_wp_error($terms) && !empty($terms)) {
                        $item['taxonomies'][$taxonomy] = array_values($terms);
                    }
                }
            }

            // Meta
            $meta_keys = array(
                '_yoast_wpseo_focuskw',
                '_yoast_wpseo_metadesc',
                'duration',
                'video_url',
                'wpmb_gallery_ids',
            );

            foreach ($meta_keys as $key) {
                $value = get_post_meta($post_id, $key, true);
                if ($value !== '' && $value !== false) {
                    $item['meta'][$key] = $value;
                }
            }

            $package['items'][$code] = $item;
        }

        return $package;
    }

    public static function export_as_json($post_ids = array()) {
        $package = self::export_matched_package($post_ids, false);
        return wp_json_encode($package, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    public static function export_as_csv($post_ids = array()) {
        $package = self::export_matched_package($post_ids, false);

        $csv = "code,title,slug,status,post_date,focus,meta_description,category,post_tag,actors,studio\n";

        foreach ($package['items'] as $item) {
            $focus = isset($item['meta']['_yoast_wpseo_focuskw']) ? $item['meta']['_yoast_wpseo_focuskw'] : '';
            $meta_desc = isset($item['meta']['_yoast_wpseo_metadesc']) ? $item['meta']['_yoast_wpseo_metadesc'] : '';
            $category = isset($item['taxonomies']['category']) ? implode(';', $item['taxonomies']['category']) : '';
            $post_tag = isset($item['taxonomies']['post_tag']) ? implode(';', $item['taxonomies']['post_tag']) : '';
            $actors = isset($item['taxonomies']['actors']) ? implode(';', $item['taxonomies']['actors']) : '';
            $studio = isset($item['taxonomies']['studio']) ? implode(';', $item['taxonomies']['studio']) : '';

            $csv .= '"' . addslashes($item['code']) . '",' .
                    '"' . addslashes($item['title']) . '",' .
                    '"' . addslashes($item['slug']) . '",' .
                    '"' . addslashes($item['status']) . '",' .
                    '"' . addslashes($item['post_date']) . '",' .
                    '"' . addslashes($focus) . '",' .
                    '"' . addslashes($meta_desc) . '",' .
                    '"' . addslashes($category) . '",' .
                    '"' . addslashes($post_tag) . '",' .
                    '"' . addslashes($actors) . '",' .
                    '"' . addslashes($studio) . '"' . "\n";
        }

        return $csv;
    }
}
