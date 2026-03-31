<?php
/**
 * Language Helper - ระบบจัดการภาษา
 * 
 * การใช้งาน:
 * 1. Include ไฟล์นี้ก่อน: require_once __DIR__ . '/includes/lang.php';
 * 2. เรียกใช้ฟังก์ชัน __('key') เพื่อแปลภาษา
 * 3. ภาษาจะถูกดึงจาก database (system_settings.system_language) 
 *    หรือจาก session/cookie ถ้ายังไม่ได้ตั้งค่า
 */

// ป้องกันการ include ซ้ำ
if (!defined('LANG_HELPER_LOADED')) {
    define('LANG_HELPER_LOADED', true);
    
    // ภาษาเริ่มต้น
    define('DEFAULT_LANGUAGE', 'th');
    
    // ภาษาที่รองรับ
    define('SUPPORTED_LANGUAGES', ['th', 'en']);
    
    // Global variable สำหรับเก็บ translations
    $GLOBALS['__translations'] = [];
    $GLOBALS['__current_lang'] = DEFAULT_LANGUAGE;
    
    /**
     * Get current language from various sources
     * Priority: Session > Cookie > Database > Default
     */
    function getCurrentLanguage(): string {
        global $pdo;
        
        // 1. Check session first
        if (isset($_SESSION['system_language']) && in_array($_SESSION['system_language'], SUPPORTED_LANGUAGES)) {
            return $_SESSION['system_language'];
        }
        
        // 2. Check cookie
        if (isset($_COOKIE['system_language']) && in_array($_COOKIE['system_language'], SUPPORTED_LANGUAGES)) {
            $_SESSION['system_language'] = $_COOKIE['system_language'];
            return $_COOKIE['system_language'];
        }
        
        // 3. Check database if PDO is available
        if (isset($pdo) && $pdo instanceof PDO) {
            try {
                $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'system_language' LIMIT 1");
                $stmt->execute();
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($result && in_array($result['setting_value'], SUPPORTED_LANGUAGES)) {
                    $_SESSION['system_language'] = $result['setting_value'];
                    setLanguageCookie($result['setting_value']);
                    return $result['setting_value'];
                }
            } catch (Exception $e) {
                // Ignore database errors
            }
        }
        
        // 4. Default language
        return DEFAULT_LANGUAGE;
    }
    
    /**
     * Set language cookie
     */
    function setLanguageCookie(string $lang): void {
        if (in_array($lang, SUPPORTED_LANGUAGES)) {
            setcookie('system_language', $lang, [
                'expires' => time() + (365 * 24 * 60 * 60), // 1 year
                'path' => '/',
                'httponly' => true,
                'samesite' => 'Lax'
            ]);
        }
    }
    
    /**
     * Set current language
     */
    function setLanguage(string $lang): bool {
        if (!in_array($lang, SUPPORTED_LANGUAGES)) {
            return false;
        }
        
        $_SESSION['system_language'] = $lang;
        setLanguageCookie($lang);
        $GLOBALS['__current_lang'] = $lang;
        $GLOBALS['__translations'] = []; // Clear cache to reload
        loadTranslations($lang);
        
        return true;
    }
    
    /**
     * Load translations for given language
     */
    function loadTranslations(string $lang = null): array {
        if ($lang === null) {
            $lang = getCurrentLanguage();
        }
        
        // Always force reload if language changed (don't use cache)
        $GLOBALS['__current_lang'] = $lang;
        
        $langFile = dirname(__DIR__) . '/langs/' . $lang . '.php';
        
        if (file_exists($langFile)) {
            $GLOBALS['__translations'] = require $langFile;
            $GLOBALS['__current_lang'] = $lang;
            return $GLOBALS['__translations'];
        } else {
            // Fallback to default language
            $defaultFile = dirname(__DIR__) . '/langs/' . DEFAULT_LANGUAGE . '.php';
            if (file_exists($defaultFile)) {
                $GLOBALS['__translations'] = require $defaultFile;
                $GLOBALS['__current_lang'] = DEFAULT_LANGUAGE;
                return $GLOBALS['__translations'];
            } else {
                $GLOBALS['__translations'] = [];
                return [];
            }
        }
    }
    
    /**
     * Translate a key
     * 
     * @param string $key Translation key (supports dot notation: 'months.1')
     * @param array $replacements Optional replacements for placeholders
     * @return string Translated string or key if not found
     */
    function __($key, array $replacements = []): string {
        // Load translations if not loaded
        if (empty($GLOBALS['__translations'])) {
            loadTranslations();
        }
        
        $translations = $GLOBALS['__translations'];
        
        // Support dot notation for nested keys
        $keys = explode('.', $key);
        $value = $translations;
        
        foreach ($keys as $k) {
            if (is_array($value) && isset($value[$k])) {
                $value = $value[$k];
            } else {
                // Key not found, return original key
                return $key;
            }
        }
        
        // If value is array, return the key
        if (is_array($value)) {
            return $key;
        }
        
        $result = (string)$value;
        
        // Replace placeholders :placeholder or {placeholder}
        foreach ($replacements as $placeholder => $replacement) {
            $result = str_replace([':' . $placeholder, '{' . $placeholder . '}'], $replacement, $result);
        }
        
        return $result;
    }
    
    /**
     * Alias for __() function
     */
    function trans($key, array $replacements = []): string {
        return __($key, $replacements);
    }
    
    /**
     * Get translation or return null if not found
     */
    function __n($key, array $replacements = []): ?string {
        $result = __($key, $replacements);
        return $result === $key ? null : $result;
    }
    
    /**
     * Get all translations for current language
     */
    function getTranslations(): array {
        if (empty($GLOBALS['__translations'])) {
            loadTranslations();
        }
        return $GLOBALS['__translations'];
    }
    
    /**
     * Get current language code
     */
    function getLang(): string {
        return $GLOBALS['__current_lang'] ?? getCurrentLanguage();
    }
    
    /**
     * Get language name by code
     */
    function getLanguageName(string $code): string {
        $names = [
            'th' => 'ไทย',
            'en' => 'English',
        ];
        return $names[$code] ?? $code;
    }
    
    /**
     * Get all supported languages
     */
    function getSupportedLanguages(): array {
        return [
            'th' => ['code' => 'th', 'name' => 'ไทย', 'flag' => '🇹🇭'],
            'en' => ['code' => 'en', 'name' => 'English', 'flag' => '🇺🇸'],
        ];
    }
    
    /**
     * Check if language is RTL (Right-to-Left)
     */
    function isRtl(string $lang = null): bool {
        if ($lang === null) {
            $lang = getLang();
        }
        // Thai and English are LTR
        return false;
    }
    
    /**
     * Format date according to current language
     */
    function formatDate($date, string $format = 'full'): string {
        if (empty($date)) {
            return '';
        }
        
        $timestamp = is_numeric($date) ? $date : strtotime($date);
        if ($timestamp === false) {
            return (string)$date;
        }
        
        $lang = getLang();
        $day = (int)date('j', $timestamp);
        $month = (int)date('n', $timestamp);
        $year = (int)date('Y', $timestamp);
        
        // Convert to Buddhist Era for Thai
        if ($lang === 'th') {
            $year += 543;
        }
        
        $monthName = __('months.' . $month);
        $monthShort = __('months_short.' . $month);
        
        switch ($format) {
            case 'short':
                return $lang === 'th' 
                    ? sprintf('%d %s %d', $day, $monthShort, $year)
                    : date('M j, Y', $timestamp);
            case 'long':
                return $lang === 'th'
                    ? sprintf('%d %s พ.ศ. %d', $day, $monthName, $year)
                    : date('F j, Y', $timestamp);
            case 'full':
            default:
                $dayName = __('days.' . date('w', $timestamp));
                return $lang === 'th'
                    ? sprintf('วัน%s ที่ %d %s พ.ศ. %d', $dayName, $day, $monthName, $year)
                    : date('l, F j, Y', $timestamp);
        }
    }
    
    /**
     * Format number according to current language
     */
    function formatNumber($number, int $decimals = 0): string {
        $lang = getLang();
        
        if ($lang === 'th') {
            return number_format((float)$number, $decimals, '.', ',');
        }
        
        return number_format((float)$number, $decimals, '.', ',');
    }
    
    /**
     * Format currency (Thai Baht)
     */
    function formatCurrency($amount, bool $showSymbol = true): string {
        $formatted = formatNumber($amount, 2);
        
        if ($showSymbol) {
            $lang = getLang();
            return $lang === 'th' 
                ? $formatted . ' บาท'
                : '฿' . $formatted;
        }
        
        return $formatted;
    }
    
    /**
     * Get month name by number
     */
    function getMonthName(int $month, bool $short = false): string {
        $key = $short ? 'months_short.' . $month : 'months.' . $month;
        return __($key);
    }
    
    /**
     * Get day name by number (0 = Sunday)
     */
    function getDayName(int $day, bool $short = false): string {
        $key = $short ? 'days_short.' . $day : 'days.' . $day;
        return __($key);
    }
    
    /**
     * Output JavaScript translations object
     */
    function outputJsTranslations(): string {
        $translations = getTranslations();
        return '<script>window.__translations = ' . json_encode($translations, JSON_UNESCAPED_UNICODE) . '; window.__currentLang = "' . getLang() . '";</script>';
    }
    
    // Auto-load translations on include
    loadTranslations();
}
