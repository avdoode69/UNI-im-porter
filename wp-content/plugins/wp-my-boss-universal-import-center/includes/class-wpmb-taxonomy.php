<?php
if (!defined('ABSPATH')) {
    exit;
}

final class WPMB_UIC_Taxonomy {

    public static function instance() {
        return new self();
    }

    public static function actual_taxonomy($taxonomy_name) {
        $map = array(
            'category' => 'category',
            'post_tag' => 'post_tag',
            'actors' => 'actors',
            'studio' => 'studio',
        );

        return isset($map[$taxonomy_name]) ? $map[$taxonomy_name] : $taxonomy_name;
    }

    public static function normalize_term_name($name) {
        $name = trim((string) $name);
        $name = preg_replace('/\s+/u', ' ', $name);
        return $name;
    }

    public static function assign_terms($post_id, $taxonomy_map = array()) {
        if (empty($taxonomy_map) || empty($post_id)) {
            return array();
        }

        $assigned = array();

        foreach ($taxonomy_map as $taxonomy => $terms) {
            $taxonomy = self::actual_taxonomy($taxonomy);
            if (!taxonomy_exists($taxonomy)) {
                continue;
            }

            $term_ids = array();
            $terms = (array) $terms;

            foreach ($terms as $term) {
                $term_name = self::normalize_term_name($term);
                if ($term_name === '') {
                    continue;
                }

                $existing = term_exists($term_name, $taxonomy);
                if ($existing !== 0 && $existing !== null) {
                    $term_id = is_array($existing) ? (int) $existing['term_id'] : (int) $existing;
                } else {
                    $inserted = wp_insert_term($term_name, $taxonomy);
                    if (!is_wp_error($inserted)) {
                        $term_id = (int) $inserted['term_id'];
                    } else {
                        continue;
                    }
                }

                if ($term_id) {
                    $term_ids[] = $term_id;
                }
            }

            if (!empty($term_ids)) {
                wp_set_post_terms($post_id, $term_ids, $taxonomy, false);
                $assigned[$taxonomy] = $term_ids;
            }
        }

        return $assigned;
    }
}
