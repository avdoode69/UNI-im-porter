<?php
if (!defined('ABSPATH')) {
    exit;
}

final class WPMB_UIC_Workpad_Batch_API {

    private static $registered = false;

    public static function register() {
        if (self::$registered) {
            return true;
        }

        self::$registered = true;
        add_action('rest_api_init', array(__CLASS__, 'register_rest_routes'));

        return true;
    }

    public static function register_rest_routes() {
        register_rest_route('wpmb-uic/v1', '/batch/process', array(
            'methods'             => 'POST',
            'callback'            => array(__CLASS__, 'process_batch'),
            'permission_callback' => function() {
                return current_user_can('manage_options');
            },
        ));
    }

    public static function process_batch($request) {
        $project_id = $request->get_param('project_id');
        $limit = $request->get_param('limit');

        $project_id = absint($project_id);
        $limit = absint($limit);

        if (!$project_id || !$limit) {
            return new WP_Error('invalid_params', 'จำเป็นต้องมี project_id และ limit');
        }

        $processed = 0;
        $errors = array();

        for ($i = 0; $i < $limit; $i++) {
            $result = WPMB_UIC_Importer::import_next($project_id);
            if (!empty($result['post_id'])) {
                $processed++;
            } elseif (!empty($result['message'])) {
                $errors[] = $result['message'];
                break;
            }
        }

        return array(
            'processed' => $processed,
            'errors' => $errors,
            'counts' => WPMB_UIC_Importer::status_counts($project_id),
        );
    }
}
