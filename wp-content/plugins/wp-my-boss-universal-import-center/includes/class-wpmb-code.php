<?php
class WPMB_UIC_Code {
    public static function normalize_code($value) {
        $value = trim((string) $value);
        $value = preg_replace('/\s+/u', '', $value);
        $value = preg_replace('/[^A-Za-z0-9_-]+/u', '', $value);
        return strtoupper($value);
    }

    public static function taxonomy_key($value) {
        $value = trim((string) $value);
        $value = preg_replace('/\s+/u', '', $value);
        $value = function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
        return $value;
    }

    public static function extract_code($value) {
        if (!is_string($value)) {
            $value = (string) $value;
        }
        if (preg_match('/[A-Z0-9]{3,}/', $value, $m)) {
            return $m[0];
        }
        return '';
    }

    public static function extract_english_names($value) {
        if (!is_string($value)) {
            $value = (string) $value;
        }
        $value = preg_replace('/[\x{0E00}-\x{0E7F}]/u', ' ', $value);
        $parts = preg_split('/\s+/u', trim($value));
        $out = array();
        foreach ($parts as $part) {
            if ($part !== '' && preg_match('/[A-Za-z]/', $part)) {
                $out[] = $part;
            }
        }
        return implode(' ', $out);
    }
}
