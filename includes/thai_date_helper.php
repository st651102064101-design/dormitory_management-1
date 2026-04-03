<?php
/**
 * Thai Date Helper — ฟังก์ชันแปลงวันที่เป็นรูปแบบไทย (พ.ศ.)
 * 
 * การใช้งาน:
 *   require_once __DIR__ . '/../includes/thai_date_helper.php';  // หรือ path ที่ถูกต้อง
 *   echo thaiDate($dateString);                    // "4 เม.ย. 2569"
 *   echo thaiDate($dateString, 'long');            // "4 เมษายน 2569"
 *   echo thaiDate($dateString, 'full');            // "วันศุกร์ที่ 4 เมษายน พ.ศ. 2569"
 *   echo thaiDate($dateString, 'short_time');      // "4 เม.ย. 2569 14:30"
 *   echo thaiDate($dateString, 'long_time');       // "4 เมษายน 2569 14:30"
 *   echo thaiMonthYear($dateString);               // "เม.ย. 2569"
 *   echo thaiMonthYearLong($dateString);           // "เมษายน 2569"
 */

if (!defined('THAI_DATE_HELPER_LOADED')) {
    define('THAI_DATE_HELPER_LOADED', true);

    // ชื่อเดือนภาษาไทย (เต็ม)
    function _thaiMonthsFull(): array {
        return [
            1 => 'มกราคม', 2 => 'กุมภาพันธ์', 3 => 'มีนาคม',
            4 => 'เมษายน', 5 => 'พฤษภาคม', 6 => 'มิถุนายน',
            7 => 'กรกฎาคม', 8 => 'สิงหาคม', 9 => 'กันยายน',
            10 => 'ตุลาคม', 11 => 'พฤศจิกายน', 12 => 'ธันวาคม',
        ];
    }

    // ชื่อเดือนภาษาไทย (ย่อ)
    function _thaiMonthsShort(): array {
        return [
            1 => 'ม.ค.', 2 => 'ก.พ.', 3 => 'มี.ค.',
            4 => 'เม.ย.', 5 => 'พ.ค.', 6 => 'มิ.ย.',
            7 => 'ก.ค.', 8 => 'ส.ค.', 9 => 'ก.ย.',
            10 => 'ต.ค.', 11 => 'พ.ย.', 12 => 'ธ.ค.',
        ];
    }

    // ชื่อวันภาษาไทย
    function _thaiDayNames(): array {
        return ['อาทิตย์', 'จันทร์', 'อังคาร', 'พุธ', 'พฤหัสบดี', 'ศุกร์', 'เสาร์'];
    }

    /**
     * แปลงวันที่เป็นรูปแบบไทย
     * 
     * @param string|int|null $date  วันที่ (string, timestamp, หรือ null)
     * @param string $format  รูปแบบ: 'short' | 'long' | 'full' | 'short_time' | 'long_time'
     * @return string  วันที่ในรูปแบบไทย
     */
    function thaiDate($date, string $format = 'short'): string {
        if (empty($date)) return '-';

        $ts = is_numeric($date) ? (int)$date : strtotime((string)$date);
        if ($ts === false || $ts === -1) return (string)$date;

        $day   = (int)date('j', $ts);
        $month = (int)date('n', $ts);
        $year  = (int)date('Y', $ts) + 543;
        $time  = date('H:i', $ts);

        $monthsFull  = _thaiMonthsFull();
        $monthsShort = _thaiMonthsShort();

        switch ($format) {
            case 'short':
                return "{$day} {$monthsShort[$month]} {$year}";
            case 'long':
                return "{$day} {$monthsFull[$month]} {$year}";
            case 'full':
                $dayNames = _thaiDayNames();
                $dayName = $dayNames[(int)date('w', $ts)];
                return "วัน{$dayName}ที่ {$day} {$monthsFull[$month]} พ.ศ. {$year}";
            case 'short_time':
                return "{$day} {$monthsShort[$month]} {$year} {$time}";
            case 'long_time':
                return "{$day} {$monthsFull[$month]} {$year} {$time}";
            default:
                return "{$day} {$monthsShort[$month]} {$year}";
        }
    }

    /**
     * แสดงเดือน/ปีแบบไทย (ย่อ) — เช่น "เม.ย. 2569"
     */
    function thaiMonthYear($date): string {
        if (empty($date)) return '-';
        $ts = is_numeric($date) ? (int)$date : strtotime((string)$date);
        if ($ts === false || $ts === -1) return (string)$date;

        $month = (int)date('n', $ts);
        $year  = (int)date('Y', $ts) + 543;
        $monthsShort = _thaiMonthsShort();
        return "{$monthsShort[$month]} {$year}";
    }

    /**
     * แสดงเดือน/ปีแบบไทย (เต็ม) — เช่น "เมษายน 2569"
     */
    function thaiMonthYearLong($date): string {
        if (empty($date)) return '-';
        $ts = is_numeric($date) ? (int)$date : strtotime((string)$date);
        if ($ts === false || $ts === -1) return (string)$date;

        $month = (int)date('n', $ts);
        $year  = (int)date('Y', $ts) + 543;
        $monthsFull = _thaiMonthsFull();
        return "{$monthsFull[$month]} {$year}";
    }

    /**
     * แปลงปี ค.ศ. เป็น พ.ศ.
     */
    function toBuddhistYear(int $gregorianYear): int {
        return $gregorianYear + 543;
    }
}
