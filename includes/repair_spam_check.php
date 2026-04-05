<?php
/**
 * AI-based Repair Spam Filter
 * Rule-based classifier that catches nonsense/keyboard-mash repair submissions.
 *
 * Usage: $result = scoreRepairText($text);
 * Returns: ['score'=>int(0-100), 'label'=>'ok'|'suspect'|'spam', 'message'=>string]
 */

if (!function_exists('scoreRepairText')) {
    function scoreRepairText(string $text): array
    {
        $text = trim($text);
        $len  = mb_strlen($text, 'UTF-8');

        if ($len === 0) {
            return ['score' => 0, 'label' => 'empty', 'message' => ''];
        }

        /* ── Instant-reject rules ─────────────────────────────────── */
        if ($len < 2) {
            return ['score' => 0, 'label' => 'spam',
                'message' => 'กรุณาระบุรายละเอียดปัญหา'];
        }
        // All digits
        if (preg_match('/^\d+$/', $text)) {
            return ['score' => 0, 'label' => 'spam',
                'message' => 'กรุณาระบุรายละเอียดเป็นข้อความ ไม่ใช่ตัวเลข'];
        }
        // Single character repeated (e.g. "aaaa", "กกกก")
        if (preg_match('/^(.)\1+$/u', $text)) {
            return ['score' => 0, 'label' => 'spam',
                'message' => 'รายละเอียดไม่สมเหตุสมผล'];
        }
        // All punctuation / symbols
        if (preg_match('/^[\s[:punct:]]+$/u', $text)) {
            return ['score' => 0, 'label' => 'spam',
                'message' => 'รายละเอียดไม่สมเหตุสมผล'];
        }

        $score = 100;

        /* ── Length penalty ───────────────────────────────────────── */
        if ($len < 4)      $score -= 55;
        elseif ($len < 6)  $score -= 30;
        elseif ($len < 8)  $score -= 10;

        /* ── Repetition: same char 3+ consecutive ────────────────── */
        if (preg_match('/(.)\1{2,}/u', $text)) $score -= 50;

        /* ── Thai language analysis ───────────────────────────────── */
        $thaiCharCount = preg_match_all('/[\x{0E00}-\x{0E7F}]/u', $text) ?: 0;

        if ($thaiCharCount >= 4) {
            // Unambiguous Thai vowel / tone-mark range:
            // ะ า ิ-ู  (U+0E30-U+0E39)  +  ั (U+0E31)
            // เ แ โ ใ ไ (U+0E40-U+0E44)
            // ็ ่ ้ ๊ ๋ ์ ๎ ๏ (U+0E47-U+0E4E)
            $vowelCount = preg_match_all(
                '/[\x{0E30}-\x{0E39}\x{0E40}-\x{0E44}\x{0E47}-\x{0E4E}\x{0E31}]/u',
                $text
            ) ?: 0;
            $vowelRatio = $vowelCount / $thaiCharCount;

            // Low vowel ratio → likely consonant mash
            if ($vowelRatio < 0.20) $score -= 50;
            elseif ($vowelRatio < 0.30) $score -= 25;

            // Low consonant diversity → repeating same few keys (e.g. ดฟดฟดฟ)
            preg_match_all('/[\x{0E01}-\x{0E2E}]/u', $text, $cm);  // Thai consonants ก-ฮ
            $consonants = $cm[0] ?? [];
            $totalCons  = count($consonants);
            if ($totalCons >= 4) {
                $uniqueCons      = count(array_unique($consonants));
                $consUniqRatio   = $uniqueCons / $totalCons;
                if ($consUniqRatio < 0.45) $score -= 30;
            }
        } elseif ($thaiCharCount === 0) {
            // No Thai chars: check English/other for keyboard mash
            preg_match_all('/[a-z]/i', $text, $em);
            $alphaChars = $em[0] ?? [];
            $alphaCount = count($alphaChars);
            if ($alphaCount >= 4) {
                $uniqueAlpha = count(array_unique(array_map('strtolower', $alphaChars)));
                $alphaUniq   = $uniqueAlpha / $alphaCount;
                // Very high uniqueness in short text = likely keyboard mash
                if ($alphaUniq > 0.90 && $alphaCount <= 10) $score -= 45;
                elseif ($alphaUniq > 0.85 && $alphaCount <= 8)  $score -= 30;
            }
        }

        /* ── Keyword bonus (repair-relevant Thai/English terms) ────── */
        // Only apply keyword bonus when the base score is high enough that the text
        // is plausibly legitimate (prevents "ไฟ" inside "ดฟไดไฟด" mash from getting boosted).
        if ($score >= 50 || $len >= 8) {
            $keywords = [
                'ไฟ','ประตู','หน้าต่าง','น้ำ','พัดลม','ก๊อก','ท่อ','แอร์','เครื่อง',
                'พัง','เสีย','รั่ว','หัก','ร้าว','แตก','ซ่อม','เปลี่ยน','ปลั๊ก',
                'สาย','ไฟฟ้า','ห้องน้ำ','ฝักบัว','ชักโครก','เพดาน','ผนัง','พื้น',
                'กุญแจ','ล็อค','กลอน','หลอด','สวิตช์','เต้าเสียบ','เต้ารับ',
                'คอมพิวเตอร์','โต๊ะ','เตียง','ตู้','ระเบียง','ราว','กระจก','ฝ้า',
                'ระบาย','ควัน','กลิ่น','แมลง','มด','แมลงสาบ',
                'air','conditioner','water','door','window','light','fan','sink',
                'toilet','shower','pipe','leak','broken','fix','repair',
            ];
            foreach ($keywords as $kw) {
                if (mb_stripos($text, $kw) !== false) {
                    $score = min(100, $score + 25);
                    break;
                }
            }
        }

        $score = max(0, min(100, $score));

        if ($score >= 60) {
            return ['score' => $score, 'label' => 'ok', 'message' => ''];
        } elseif ($score >= 38) {
            return [
                'score'   => $score,
                'label'   => 'suspect',
                'message' => 'กรุณาอธิบายปัญหาให้ชัดเจนขึ้น เช่น "พัดลมไม่หมุน" หรือ "ก๊อกน้ำรั่ว"',
            ];
        } else {
            return [
                'score'   => $score,
                'label'   => 'spam',
                'message' => 'ข้อความไม่สมเหตุสมผล กรุณาระบุปัญหาที่ต้องการซ่อมให้ชัดเจน',
            ];
        }
    }
}
