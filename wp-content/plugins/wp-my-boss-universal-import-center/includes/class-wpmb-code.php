<?php
/**
 * ═══════════════════════════════════════════════════════════════
 * 📜 Code Parser ของ UNI-IM-PORT v1.0.15.3
 * ═══════════════════════════════════════════════════════════════
 *
 * - แยก CODE จากชื่อไฟล์ / Title / Slug
 * - จับคู่ Role รูปภาพ (cover/poster/gallery)
 * - Normalize CODE
 * - Natural Sort Media
 *
 * ═══════════════════════════════════════════════════════════════
 */

if (!defined('ABSPATH') && !defined('WPMB_UIC_TESTING')) {
    exit;
}

final class WPMB_UIC_Code {

    /* ============================================================
       🔢 NORMALIZE CODE
       ============================================================ */
    public static function normalize_code($value) {
        $candidate = self::extract_code($value);
        if ($candidate !== '') {
            return $candidate;
        }

        $value = self::basename_without_extension((string) $value);
        $value = strtoupper(trim($value));
        $value = preg_replace(
            '/(?:^|[-_.~\s])(COVER|POSTER|THUMB|FEATURED|GALLERY|IMAGE|IMG)(?:[-_.~\s]|$).*/i',
            '',
            $value
        );
        $value = preg_replace('/[^A-Z0-9]+/', '-', $value);

        return trim($value, '-');
    }

    /* ============================================================
       🔍 EXTRACT CODE
       ============================================================ */
    public static function extract_code($value) {
        $raw = strtoupper(rawurldecode((string) $value));
        $raw = str_replace(array('%2D', '%5F'), array('-', '_'), $raw);
        $base = self::basename_without_extension($raw);

        // Pattern: 1start00608jp-1 → START-608
        if (preg_match('/^[0-9]*([A-Z]{2,12})0*([0-9]{3,7})(?:JP)?(?:[-_.~\s].*)?$/u', $base, $m)) {
            return $m[1] . '-' . (int) $m[2];
        }

        if (!preg_match_all(
            '/(?<![A-Z0-9])([A-Z]{2,12})[-_\s]?0*([0-9]{2,7})(?![0-9])/u',
            $base,
            $matches,
            PREG_OFFSET_CAPTURE
        )) {
            return '';
        }

        $best = '';
        $best_score = -PHP_INT_MAX;
        $count = count($matches[0]);

        foreach ($matches[0] as $i => $whole) {
            $letters = $matches[1][$i][0];
            $digits = (string) ((int) $matches[2][$i][0]);
            $offset = (int) $whole[1];
            $end = $offset + strlen($whole[0]);
            $tail = substr($base, $end);

            $score = $offset;
            if ($i === $count - 1) {
                $score += 40;
            }

            if ($tail === ''
                || preg_match(
                    '/^[-_.~\s]*(?:POSTER|COVER|FEATURED|THUMB|MAIN|GALLERY|IMAGE|IMG)?(?:[-_.~\s]*\d{1,4})?[-_.~\s]*$/i',
                    $tail
                )
            ) {
                $score += 100;
            }

            $deny = array(
                'AV', 'IMG', 'IMAGE', 'PHOTO', 'PIC', 'GALLERY',
                'COVER', 'POSTER', 'FEATURED', 'THUMB', 'THUMBNAIL',
                'MAIN', 'SCREEN', 'SCREENSHOT', 'BACK', 'FRONT',
                'SIDE', 'TOP', 'BOTTOM'
            );
            if (in_array($letters, $deny, true)) {
                $score -= 200;
            }

            if ($score > $best_score) {
                $best_score = $score;
                $best = $letters . '-' . $digits;
            }
        }

        if ($best !== '') {
            $best_letters = preg_replace('/-[0-9]+$/', '', $best);
            if (in_array(strtoupper($best_letters), array(
                'GALLERY', 'COVER', 'POSTER', 'FEATURED', 'THUMB',
                'THUMBNAIL', 'MAIN', 'IMAGE', 'IMG', 'PHOTO', 'PIC',
                'SCREEN', 'SCREENSHOT', 'BACK', 'FRONT', 'SIDE',
                'TOP', 'BOTTOM', 'AV'
            ), true)) {
                return '';
            }
        }

        return $best;
    }

    /* ============================================================
       📂 FOLDER ROLE HINT
       ============================================================ */
    public static function folder_role_hint($path) {
        $dir = str_replace('\\', '/', (string) $path);
        $dir = dirname($dir);

        if ($dir === '.' || $dir === '' || $dir === '/') {
            return '';
        }

        $parts = array_filter(array_map('trim', explode('/', $dir)));
        if (!$parts) {
            return '';
        }

        foreach (array_reverse($parts) as $p) {
            $pu = strtoupper($p);

            if (in_array($pu, array(
                'COVER', 'COVERS', 'FEATURED', 'FEATUREDIMAGE',
                'THUMB', 'THUMBNAIL', 'MAIN'
            ), true)) {
                return 'cover';
            }

            if (in_array($pu, array('POSTER', 'POSTERS'), true)) {
                return 'poster';
            }

            if (in_array($pu, array(
                'GALLERY', 'GALLERIES', 'IMAGES', 'IMAGE', 'IMG',
                'PHOTOS', 'SCREEN', 'SCREENSHOTS', 'SCREENSHOT', 'PICS'
            ), true)) {
                return 'gallery';
            }
        }

        return '';
    }

    /* ============================================================
       📄 FILENAME INFO
       ============================================================
       จับคู่ Role จากชื่อไฟล์:
       RULE 0: Folder hint
       RULE 1: คำ POSTER → poster
       RULE 2: คำ COVER → cover
       RULE 3: คำ GALLERY/IMAGE + เลข → gallery
       RULE 4: CODE + เลข → gallery
       RULE 5: CODE เดี่ยว → cover
       RULE 6: CODE + คั่นว่าง → cover
       RULE 7: CODE + อื่นๆ → gallery
       ============================================================ */
    public static function filename_info($path, $known_code = '', $folder_hint = null) {
        $name = self::basename_without_extension((string) $path);
        $upper = strtoupper(rawurldecode($name));
        $code = $known_code !== '' ? self::normalize_code($known_code) : self::extract_code($upper);

        if ($folder_hint === null) {
            $folder_hint = self::folder_role_hint($path);
        }

        $role = 'cover';
        $sequence = 0;
        $confidence = 40;
        $tail = '';
        $before = '';

        if ($code !== '') {
            // ─── RULE 0: Folder hint ───
            if ($folder_hint === 'poster') {
                return array(
                    'code' => $code, 'role' => 'poster', 'sequence' => 0,
                    'base' => $name, 'extension' => strtolower(pathinfo((string) $path, PATHINFO_EXTENSION)),
                    'confidence' => 130, 'role_source' => 'folder',
                );
            }
            if ($folder_hint === 'cover') {
                return array(
                    'code' => $code, 'role' => 'cover', 'sequence' => 0,
                    'base' => $name, 'extension' => strtolower(pathinfo((string) $path, PATHINFO_EXTENSION)),
                    'confidence' => 128, 'role_source' => 'folder',
                );
            }
            if ($folder_hint === 'gallery') {
                $seq = 0;
                if (preg_match('/0*(\d{1,4})/', $upper, $nm)) {
                    $seq = max(1, (int) $nm[1]);
                }
                return array(
                    'code' => $code, 'role' => 'gallery', 'sequence' => $seq,
                    'base' => $name, 'extension' => strtolower(pathinfo((string) $path, PATHINFO_EXTENSION)),
                    'confidence' => 126, 'role_source' => 'folder',
                );
            }

            $code_pattern = self::code_pattern($code);
            $matched = array();

            if (preg_match_all($code_pattern, $upper, $all, PREG_OFFSET_CAPTURE) && !empty($all[0])) {
                $matched = end($all[0]);
            }

            if ($matched) {
                $offset = (int) $matched[1];
                $before = substr($upper, 0, $offset);
                $tail = substr($upper, $offset + strlen($matched[0]));
            } elseif (preg_match('/^[0-9]*[A-Z]{2,12}0*[0-9]{3,7}(?:JP)?(.*)$/u', $upper, $special_tail)) {
                $tail = isset($special_tail[1]) ? $special_tail[1] : '';
            }

            // ─── RULE 1: POSTER ───
            if (preg_match('/(?:^|[-_.~\s])POSTER(?:$|[-_.~\s])/i', $upper)) {
                $role = 'poster';
                $sequence = 0;
                $confidence = 125;
            }
            // ─── RULE 2: COVER ───
            elseif (preg_match(
                '/(?:^|[-_.~\s])(COVER|FEATURED|THUMB|THUMBNAIL|MAIN)(?:$|[-_.~\s])/i',
                $upper,
                $cover_m
            )) {
                $role = 'cover';
                $sequence = 0;
                $confidence = 120;
            }
            // ─── RULE 3: GALLERY + เลข ───
            elseif (preg_match(
                '/(?:GALLERY|IMAGE|IMG|PHOTO|PIC)[-_.~\s]*\(?0*(\d{1,4})\)?(?:[-_.~\s].*)?$/i',
                $upper,
                $m
            )) {
                $role = 'gallery';
                $sequence = max(1, (int) $m[1]);
                $confidence = 118;
            }
            // ─── RULE 4: CODE + เลข ───
            elseif ($tail !== '' && preg_match('/^[-_.~\s]*\(?0*(\d{1,4})\)?(.*)$/u', $tail, $m)) {
                $role = 'gallery';
                $sequence = max(1, (int) $m[1]);
                $suffix = trim((string) $m[2], "-_.~ \t\n\r\0\x0B");
                $confidence = $suffix === '' ? 115 : 94;
            }
            // ─── RULE 5: CODE เดี่ยว ───
            elseif (self::compact($upper) === self::compact($code)) {
                $role = 'cover';
                $sequence = 0;
                $confidence = 110;
            }
            // ─── RULE 6: CODE + คั่นว่าง ───
            elseif (trim($tail, "-_.~ \t\n\r\0\x0B") === '') {
                $role = 'cover';
                $sequence = 0;
                $confidence = trim($before, "-_.~ \t\n\r\0\x0B") === '' ? 105 : 85;
            }
            // ─── RULE 7: CODE + อื่นๆ → gallery ───
            else {
                $role = 'gallery';
                if (preg_match('/0*(\d{1,4})/', $tail, $num_m)) {
                    $sequence = max(1, (int) $num_m[1]);
                } else {
                    $sequence = 0;
                }
                $confidence = 70;
            }
        }

        return array(
            'code'       => $code,
            'role'       => $role,
            'sequence'   => $sequence,
            'base'       => $name,
            'extension'  => strtolower(pathinfo((string) $path, PATHINFO_EXTENSION)),
            'confidence' => $confidence,
        );
    }

    /* ============================================================
       🎯 CODE PATTERN (Private Helper)
       ============================================================ */
    private static function code_pattern($code) {
        if (!preg_match('/^([A-Z]{2,12})-([0-9]{1,7})$/', strtoupper((string) $code), $m)) {
            return '/(?!)/';  // Negative lookahead that never matches
        }

        return '/(?<![A-Z0-9])'
            . preg_quote($m[1], '/')
            . '[-_\s]?0*'
            . preg_quote((string) ((int) $m[2]), '/')
            . '(?![0-9])/i';
    }

    /* ============================================================
       🔤 COMPACT — ตัดอักขระพิเศษ
       ============================================================ */
    public static function compact($value) {
        return preg_replace('/[^A-Z0-9]/', '', strtoupper((string) $value));
    }

    /* ============================================================
       🏷️ TAXONOMY KEY — ตัดช่องว่าง
       ============================================================ */
    public static function taxonomy_key($name) {
        $name = html_entity_decode((string) $name, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $name = str_replace(array("\xC2\xA0", "\xE2\x80\x8B", "\xEF\xBB\xBF"), '', $name);
        return preg_replace('/\s+/u', '', trim($name));
    }

    /* ============================================================
       🔢 NATURAL MEDIA SORT
       ============================================================ */
    public static function natural_media_sort(array $items) {
        usort($items, function ($a, $b) {
            $weights = array('cover' => 0, 'poster' => 1, 'gallery' => 2, 'unused' => 3);
            $ar = isset($a['role']) && isset($weights[$a['role']]) ? $a['role'] : 'gallery';
            $br = isset($b['role']) && isset($weights[$b['role']]) ? $b['role'] : 'gallery';

            if ($weights[$ar] !== $weights[$br]) {
                return $weights[$ar] <=> $weights[$br];
            }

            $as = isset($a['sequence']) && $a['sequence'] !== null ? (int) $a['sequence'] : PHP_INT_MAX;
            $bs = isset($b['sequence']) && $b['sequence'] !== null ? (int) $b['sequence'] : PHP_INT_MAX;

            if ($as !== $bs) {
                return $as <=> $bs;
            }

            $ac = isset($a['confidence']) ? (int) $a['confidence'] : 0;
            $bc = isset($b['confidence']) ? (int) $b['confidence'] : 0;

            if ($ac !== $bc) {
                return $bc <=> $ac;
            }

            return strnatcasecmp(
                isset($a['name']) ? $a['name'] : (isset($a['file_name']) ? $a['file_name'] : ''),
                isset($b['name']) ? $b['name'] : (isset($b['file_name']) ? $b['file_name'] : '')
            );
        });

        return $items;
    }

    /* ============================================================
       📁 BASENAME WITHOUT EXTENSION
       ============================================================ */
    public static function basename_without_extension($path) {
        $path = str_replace('\\', '/', (string) $path);
        $base = basename($path);
        return preg_replace('/\.[^.]+$/', '', $base);
    }

    /* ============================================================
       🛡️ SAFE RELATIVE PATH
       ============================================================ */
    public static function safe_relative_path($path) {
        $path = str_replace('\\', '/', (string) $path);
        $path = preg_replace('#/+#', '/', $path);
        $path = ltrim($path, '/');

        $parts = array();

        foreach (explode('/', $path) as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }
            if ($part === '..') {
                return '';
            }
            $parts[] = preg_replace('/[^A-Za-z0-9._\-ก-๙]/u', '_', $part);
        }

        return implode('/', $parts);
    }

    /* ============================================================
       👤 EXTRACT ENGLISH NAMES
       ============================================================ */
    public static function extract_english_names($value) {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        $parts = preg_split('/[,;|\n]+/u', $value);
        $english = array();

        foreach ($parts as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }

            $has_thai = (bool) preg_match('/[\x{0E00}-\x{0E7F}]/u', $part);
            $has_english = (bool) preg_match('/[A-Za-z]/', $part);

            if (!$has_thai && $has_english) {
                $english[] = $part;
            }
        }

        return implode(', ', array_unique($english));
    }
}
