<?php
if (!defined('ABSPATH')) {
    exit;
}

final class WPMB_UIC_Taxonomy {
    private static $instance;
    private static $resolver_cache = array();

    public static function instance() {
        if (!self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('set_object_terms', array($this, 'queue_term_recheck'), 20, 6);
        add_action('created_term', array($this, 'recheck_term'), 20, 3);
        add_action('edited_term', array($this, 'recheck_term'), 20, 3);

        add_filter('wp_robots', array($this, 'filter_wp_robots'));
        add_filter('wpseo_robots', array($this, 'filter_yoast_robots'));
        add_filter('rank_math/frontend/robots', array($this, 'filter_rankmath_robots'));
    }

    public static function resolve_term($name, $taxonomy, $create = true) {
        $taxonomy = self::actual_taxonomy($taxonomy);
        $name = trim(wp_strip_all_tags((string) $name));

        if ($name === '' || !taxonomy_exists($taxonomy)) {
            return 0;
        }

        if (!isset(self::$resolver_cache[$taxonomy])) {
            $terms = get_terms(array(
                'taxonomy'   => $taxonomy,
                'hide_empty' => false,
                'number'     => 0,
            ));

            if (is_wp_error($terms)) {
                return 0;
            }

            self::$resolver_cache[$taxonomy] = array(
                'by_key' => array(),
                'all'    => array(),
            );

            foreach ($terms as $term) {
                $term_key = WPMB_UIC_Code::taxonomy_key($term->name);
                if (!isset(self::$resolver_cache[$taxonomy]['by_key'][$term_key])) {
                    self::$resolver_cache[$taxonomy]['by_key'][$term_key] = array();
                }
                self::$resolver_cache[$taxonomy]['by_key'][$term_key][] = $term;
                self::$resolver_cache[$taxonomy]['all'][] = $term;
            }
        }

        $key = WPMB_UIC_Code::taxonomy_key($name);
        $matches = array();

        if (isset(self::$resolver_cache[$taxonomy]['by_key'][$key])) {
            $matches = self::$resolver_cache[$taxonomy]['by_key'][$key];
        }

        if (empty($matches)) {
            $lower = function_exists('mb_strtolower') ? mb_strtolower($name, 'UTF-8') : strtolower($name);
            foreach (self::$resolver_cache[$taxonomy]['all'] as $term) {
                $term_lower = function_exists('mb_strtolower') ? mb_strtolower($term->name, 'UTF-8') : strtolower($term->name);
                if ($term_lower === $lower) {
                    $matches[] = $term;
                }
            }
        }

        if (empty($matches)) {
            $slug = sanitize_title($name);
            if ($slug !== '') {
                foreach (self::$resolver_cache[$taxonomy]['all'] as $term) {
                    if ($term->slug === $slug) {
                        $matches[] = $term;
                    }
                }
            }
        }

        if (!empty($matches)) {
            foreach ($matches as $match) {
                $match->wpmb_all_post_count = self::term_all_post_count((int) $match->term_id, $taxonomy);
            }

            usort($matches, function ($a, $b) {
                if ((int) $a->wpmb_all_post_count !== (int) $b->wpmb_all_post_count) {
                    return (int) $b->wpmb_all_post_count <=> (int) $a->wpmb_all_post_count;
                }
                return (int) $a->term_id <=> (int) $b->term_id;
            });

            return (int) $matches[0]->term_id;
        }

        if (!$create) {
            return 0;
        }

        $existing_id = (int) self::find_term_by_name_strict($name, $taxonomy);
        if ($existing_id > 0) {
            return $existing_id;
        }

        $created = wp_insert_term($name, $taxonomy);
        if (is_wp_error($created)) {
            $existing_id = $created->get_error_data('term_exists');
            if ($existing_id) {
                return (int) $existing_id;
            }
            return 0;
        }

        $term_id = (int) $created['term_id'];
        $created_term = get_term($term_id, $taxonomy);

        if ($created_term && !is_wp_error($created_term)) {
            $new_key = WPMB_UIC_Code::taxonomy_key($created_term->name);
            if (!isset(self::$resolver_cache[$taxonomy]['by_key'][$new_key])) {
                self::$resolver_cache[$taxonomy]['by_key'][$new_key] = array();
            }
            self::$resolver_cache[$taxonomy]['by_key'][$new_key][] = $created_term;
            self::$resolver_cache[$taxonomy]['all'][] = $created_term;
        }

        return $term_id;
    }

    private static function find_term_by_name_strict($name, $taxonomy) {
        global $wpdb;

        $name = trim((string) $name);
        if ($name === '') {
            return 0;
        }

        $id = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT t.term_id FROM {$wpdb->terms} t INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = t.term_id WHERE tt.taxonomy = %s AND t.name = %s ORDER BY t.term_id ASC LIMIT 1",
            $taxonomy, $name
        ));
        if ($id > 0) {
            return $id;
        }

        $id = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT t.term_id FROM {$wpdb->terms} t INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = t.term_id WHERE tt.taxonomy = %s AND LOWER(t.name) = LOWER(%s) ORDER BY t.term_id ASC LIMIT 1",
            $taxonomy, $name
        ));
        if ($id > 0) {
            return $id;
        }

        $slug = sanitize_title($name);
        if ($slug !== '') {
            $id = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT t.term_id FROM {$wpdb->terms} t INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = t.term_id WHERE tt.taxonomy = %s AND t.slug = %s ORDER BY t.term_id ASC LIMIT 1",
                $taxonomy, $slug
            ));
            if ($id > 0) {
                return $id;
            }
        }

        return 0;
    }

    public static function assign_terms($post_id, array $taxonomies) {
        $result = array();

        foreach ($taxonomies as $taxonomy => $names) {
            $taxonomy = self::actual_taxonomy($taxonomy);
            if (!taxonomy_exists($taxonomy)) {
                continue;
            }

            $names = is_array($names) ? $names : preg_split('/[,;|\n]+/u', (string) $names);
            $ids = array();
            foreach ($names as $name) {
                $id = self::resolve_term($name, $taxonomy, true);
                if ($id) {
                    $ids[] = $id;
                }
            }

            $ids = array_values(array_unique(array_map('intval', $ids)));

            if ($ids) {
                $assigned = wp_set_object_terms($post_id, $ids, $taxonomy, false);
                if (!is_wp_error($assigned)) {
                    $result[$taxonomy] = $ids;
                }
            }
        }

        return $result;
    }

    public static function scan_terms() {
        global $wpdb;
        $db = WPMB_UIC_DB::instance();

        $wpdb->query("DELETE FROM {$db->term_index}");

        $settings = wp_parse_args(get_option('wpmb_uic_settings', array()), array(
            'taxonomy_types' => array('category', 'post_tag', 'actors', 'studio')
        ));

        $types = array_values(array_unique(array_filter(
            array_map(array(__CLASS__, 'actual_taxonomy'), (array) $settings['taxonomy_types']),
            'taxonomy_exists'
        )));

        if (!$types) {
            return array('count' => 0);
        }

        $count = 0;

        foreach ($types as $taxonomy) {
            $terms = get_terms(array(
                'taxonomy'   => $taxonomy,
                'hide_empty' => false,
                'number'     => 0,
            ));

            if (is_wp_error($terms)) {
                continue;
            }

            foreach ($terms as $term) {
                $tt_id = (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT term_taxonomy_id FROM {$wpdb->term_taxonomy} WHERE term_id=%d AND taxonomy=%s",
                    $term->term_id,
                    $taxonomy
                ));

                if (!$tt_id) {
                    continue;
                }

                $state = get_term_meta($term->term_id, '_wpmb_index_state', true);
                $object_count = self::relationship_count($tt_id);

                $wpdb->replace($db->term_index, array(
                    'term_taxonomy_id' => $tt_id,
                    'term_id'          => (int) $term->term_id,
                    'taxonomy'         => $taxonomy,
                    'term_name'        => $term->name,
                    'normalized_name'  => WPMB_UIC_Code::taxonomy_key($term->name),
                    'term_slug'        => $term->slug,
                    'object_count'     => $object_count,
                    'index_state'      => $state ? $state : 'auto',
                    'scanned_at'       => current_time('mysql'),
                ));

                $count++;
            }
        }

        return array('count' => $count);
    }

    public static function duplicate_groups() {
        global $wpdb;
        $db = WPMB_UIC_DB::instance();

        self::scan_terms();

        $groups = $wpdb->get_results(
            "SELECT taxonomy, normalized_name, COUNT(*) AS duplicate_count, SUM(object_count) AS total_count FROM {$db->term_index} WHERE normalized_name<>'' GROUP BY taxonomy, normalized_name HAVING COUNT(*) > 1 ORDER BY duplicate_count DESC, total_count DESC",
            ARRAY_A
        );

        foreach ($groups as &$group) {
            $group['terms'] = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$db->term_index} WHERE taxonomy=%s AND normalized_name=%s ORDER BY object_count DESC, term_id ASC",
                $group['taxonomy'],
                $group['normalized_name']
            ), ARRAY_A);

            $group['master_term_id'] = isset($group['terms'][0]['term_id']) ? (int) $group['terms'][0]['term_id'] : 0;
        }
        unset($group);

        return $groups;
    }

    public static function merge_group($taxonomy, array $term_ids, $master_term_id = 0) {
        global $wpdb;

        $taxonomy = sanitize_key($taxonomy);
        $term_ids = array_values(array_unique(array_filter(array_map('intval', $term_ids))));

        if (count($term_ids) < 2 || !taxonomy_exists($taxonomy)) {
            return new WP_Error('wpmb_bad_merge', 'ข้อมูลรวม Taxonomy ไม่ครบ');
        }

        $terms = array();
        foreach ($term_ids as $term_id) {
            $term = get_term($term_id, $taxonomy);
            if ($term && !is_wp_error($term)) {
                $terms[] = $term;
            }
        }

        if (count($terms) < 2) {
            return new WP_Error('wpmb_terms_missing', 'ไม่พบ Taxonomy ที่ต้องรวม');
        }

        $key = WPMB_UIC_Code::taxonomy_key($terms[0]->name);
        foreach ($terms as $term) {
            if (WPMB_UIC_Code::taxonomy_key($term->name) !== $key) {
                return new WP_Error('wpmb_not_whitespace_duplicate', 'ยกเลิก: ชื่อต่างกันมากกว่าเว้นวรรค ระบบจะไม่รวม');
            }
        }

        if (!$master_term_id) {
            foreach ($terms as $term) {
                $term->wpmb_all_post_count = self::term_all_post_count((int) $term->term_id, $taxonomy);
            }

            usort($terms, function ($a, $b) {
                if ((int) $a->wpmb_all_post_count !== (int) $b->wpmb_all_post_count) {
                    return (int) $b->wpmb_all_post_count <=> (int) $a->wpmb_all_post_count;
                }
                return (int) $a->term_id <=> (int) $b->term_id;
            });

            $master_term_id = (int) $terms[0]->term_id;
        }

        $moved = 0;

        foreach ($terms as $term) {
            if ((int) $term->term_id === (int) $master_term_id) {
                continue;
            }

            $objects = get_objects_in_term($term->term_id, $taxonomy);

            if (!is_wp_error($objects)) {
                foreach (array_chunk(array_map('intval', $objects), 100) as $chunk) {
                    foreach ($chunk as $object_id) {
                        wp_set_object_terms($object_id, array($master_term_id), $taxonomy, true);
                        wp_remove_object_terms($object_id, array((int) $term->term_id), $taxonomy);
                        $moved++;
                    }
                }
            }

            if (is_taxonomy_hierarchical($taxonomy)) {
                $wpdb->update(
                    $wpdb->term_taxonomy,
                    array('parent' => $master_term_id),
                    array('parent' => (int) $term->term_id, 'taxonomy' => $taxonomy)
                );
            }

            wp_delete_term((int) $term->term_id, $taxonomy);
        }

        clean_term_cache($term_ids, $taxonomy);
        self::scan_terms();

        return array(
            'master_term_id' => $master_term_id,
            'moved_relationships' => $moved,
        );
    }

    public static function run_auto_index_all() {
        return array(
            'updated'  => 0,
            'disabled' => true,
            'message'  => 'UNI Index/Noindex control is permanently disabled. Existing SEO rules were left unchanged.',
        );
    }

    public function queue_term_recheck($object_id, $terms, $tt_ids, $taxonomy, $append, $old_tt_ids) {
        return;
    }

    public function recheck_term($term_id, $tt_id = 0, $taxonomy = '') {
        return;
    }

    public function filter_wp_robots($robots) {
        return $robots;
    }

    public function filter_yoast_robots($robots) {
        return $robots;
    }

    public function filter_rankmath_robots($robots) {
        return $robots;
    }

    private static function current_term_state() {
        return '';
    }

    public static function actual_taxonomy($taxonomy) {
        $taxonomy = sanitize_key((string) $taxonomy);

        $aliases = array(
            'tag'       => array('post_tag'),
            'tags'      => array('post_tag'),
            'post_tag'  => array('post_tag'),
            'actor'     => array('actors', 'actor'),
            'actors'    => array('actors', 'actor'),
            'actress'   => array('actors', 'actor'),
            'studio'    => array('studio', 'studios'),
            'studios'   => array('studio', 'studios'),
            'category'  => array('category'),
            'categories' => array('category'),
        );

        $candidates = isset($aliases[$taxonomy]) ? $aliases[$taxonomy] : array($taxonomy);

        foreach ($candidates as $candidate) {
            if (taxonomy_exists($candidate)) {
                return $candidate;
            }
        }

        return $candidates[0];
    }

    private static function term_all_post_count($term_id, $taxonomy) {
        global $wpdb;

        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT tr.object_id) FROM {$wpdb->term_relationships} tr INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id=tr.term_taxonomy_id INNER JOIN {$wpdb->posts} p ON p.ID=tr.object_id WHERE tt.term_id=%d AND tt.taxonomy=%s AND p.post_type='post' AND p.post_status NOT IN ('trash','auto-draft')",
            (int) $term_id,
            $taxonomy
        ));
    }

    private static function relationship_count($term_taxonomy_id) {
        global $wpdb;

        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT tr.object_id) FROM {$wpdb->term_relationships} tr INNER JOIN {$wpdb->posts} p ON p.ID=tr.object_id WHERE tr.term_taxonomy_id=%d AND p.post_type='post' AND p.post_status NOT IN ('trash','auto-draft')",
            (int) $term_taxonomy_id
        ));
    }
}
