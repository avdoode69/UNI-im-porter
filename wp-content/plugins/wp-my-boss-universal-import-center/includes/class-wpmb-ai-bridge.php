<?php
if (!defined('ABSPATH')) {
    exit;
}

final class WPMB_UIC_AI_Bridge {

    private static $registered = false;

    public static function register() {
        if (self::$registered) {
            return true;
        }

        self::$registered = true;
        return true;
    }

    public static function enhance_payload($payload) {
        if (empty($payload['code'])) {
            return $payload;
        }

        if (empty($payload['title'])) {
            $payload['title'] = $payload['code'];
        }

        return $payload;
    }
}
