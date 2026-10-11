<?php
if (!defined('ABSPATH')) {
    exit;
}

final class WPMB_UIC_Readers {
    const DETECT_CSV = 'csv';
    const DETECT_JSON = 'json';
    const DETECT_XML = 'xml';
    const DETECT_XLSX = 'xlsx';
    const DETECT_ZIP = 'zip';
    const DETECT_OTHER = 'other';

    public static function detect_type($path, $name = '') {
        if (!file_exists($path)) {
            return self::DETECT_OTHER;
        }

        $name = $name ?: wp_basename($path);
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));

        $magic = file_get_contents($path, false, null, 0, 4);
        if (!$magic) {
            return self::DETECT_OTHER;
        }

        // ZIP file signature
        if (substr($magic, 0, 2) === 'PK') {
            return self::DETECT_ZIP;
        }

        // JSON detection
        if (in_array($ext, array('json'), true)) {
            return self::DETECT_JSON;
        }

        // CSV detection
        if (in_array($ext, array('csv', 'tsv', 'txt'), true)) {
            return self::DETECT_CSV;
        }

        // XML detection
        if (in_array($ext, array('xml', 'xhtml'), true) || strpos($magic, '<?xml') === 0) {
            return self::DETECT_XML;
        }

        // XLSX detection
        if (in_array($ext, array('xlsx', 'xls'), true)) {
            return self::DETECT_XLSX;
        }

        return self::DETECT_OTHER;
    }

    public static function analyze_file_batch($project_id, $file_id, $path, $name, $limit = 12) {
        $project_id = absint($project_id);
        $file_id = absint($file_id);
        $limit = absint($limit);

        if (!file_exists($path)) {
            return array(
                'type' => self::DETECT_OTHER,
                'done' => true,
                'items' => 0,
                'media' => 0,
                'error' => 'ไฟล์ไม่พบ'
            );
        }

        $type = self::detect_type($path, $name);
        $result = array(
            'type' => $type,
            'done' => false,
            'items' => 0,
            'media' => 0,
            'processed' => 0,
        );

        switch ($type) {
            case self::DETECT_JSON:
                $result = array_merge($result, self::read_json($path, $project_id, $file_id, $limit));
                break;
            case self::DETECT_CSV:
                $result = array_merge($result, self::read_csv($path, $project_id, $file_id, $limit));
                break;
            case self::DETECT_ZIP:
                $result = array_merge($result, self::read_zip($path, $project_id, $file_id, $limit));
                break;
            default:
                $result['done'] = true;
                $result['error'] = 'ประเภทไฟล์ไม่รองรับ';
        }

        return $result;
    }

    private static function read_json($path, $project_id, $file_id, $limit) {
        $content = file_get_contents($path);
        if (!$content) {
            return array('done' => true, 'items' => 0, 'media' => 0, 'error' => 'ไม่สามารถอ่านไฟล์');
        }

        $data = json_decode($content, true);
        if (!is_array($data)) {
            return array('done' => true, 'items' => 0, 'media' => 0, 'error' => 'JSON ไม่ถูกต้อง');
        }

        $items = 0;
        $media = 0;

        // Process up to $limit items
        $processed = 0;
        foreach ($data as $code => $payload) {
            if ($processed >= $limit) {
                break;
            }

            if (is_array($payload)) {
                $item_id = WPMB_UIC_DB::instance()->upsert_item($project_id, $code, $payload);
                if (!is_wp_error($item_id)) {
                    $items++;
                }
                $processed++;
            }
        }

        return array(
            'done' => $processed < count($data) ? false : true,
            'items' => $items,
            'media' => 0,
            'processed' => $processed,
        );
    }

    private static function read_csv($path, $project_id, $file_id, $limit) {
        $handle = fopen($path, 'r');
        if (!$handle) {
            return array('done' => true, 'items' => 0, 'media' => 0, 'error' => 'ไม่สามารถเปิดไฟล์');
        }

        $items = 0;
        $processed = 0;
        $headers = null;

        while (($row = fgetcsv($handle, 0, ',')) !== false) {
            if ($processed >= $limit) {
                break;
            }

            if ($headers === null) {
                $headers = $row;
                continue;
            }

            $payload = array();
            foreach ($headers as $i => $header) {
                if (isset($row[$i])) {
                    $payload[$header] = $row[$i];
                }
            }

            $code = isset($payload['code']) ? $payload['code'] : '';
            if ($code !== '') {
                $item_id = WPMB_UIC_DB::instance()->upsert_item($project_id, $code, $payload);
                if (!is_wp_error($item_id)) {
                    $items++;
                }
            }
            $processed++;
        }

        fclose($handle);

        return array(
            'done' => true,
            'items' => $items,
            'media' => 0,
            'processed' => $processed,
        );
    }

    private static function read_zip($path, $project_id, $file_id, $limit) {
        // Stub: ZIP processing would require ZipArchive
        return array(
            'done' => true,
            'items' => 0,
            'media' => 0,
            'error' => 'ZIP processing coming soon'
        );
    }
}
