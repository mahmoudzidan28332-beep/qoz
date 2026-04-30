<?php declare(strict_types=1);
// htdocs/api/helpers/utils.php
// ملف الدوال المساعدة العامة (Utility Functions)
// دوال عامة تُستخدم في جميع أنحاء التطبيق

// ===========================================
// تحميل الملفات المطلوبة
// ===========================================

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/constants.php';

// ===========================================
// Utils Class
// ===========================================

class Utils {
    
    // ===========================================
    // 1️⃣ دوال التاريخ والوقت (Date & Time)
    // ===========================================
    
    /**
     * تنسيق التاريخ للعرض
     * 
     * @param string|int $datetime التاريخ أو timestamp
     * @param string $format صيغة التاريخ
     * @param string $timezone المنطقة الزمنية
     * @return string
     */
    public static function formatDate($datetime, $format = 'Y-m-d H:i:s', $timezone = null) {
        $timezone = $timezone ?? DEFAULT_TIMEZONE;
        
        try {
            if (is_numeric($datetime)) {
                $date = new DateTime('@' . $datetime);
            } else {
                $date = new DateTime($datetime);
            }
            
            $date->setTimezone(new DateTimeZone($timezone));
            return $date->format($format);
            
        } catch (\RuntimeException $e) {
            return $datetime;
        }
    }
    
    /**
     * تحويل التاريخ إلى "منذ" (مثل:   منذ ساعتين)
     * 
     * @param string|int $datetime
     * @return string
     */
    public static function timeAgo($datetime) {
        if (is_numeric($datetime)) {
            $timestamp = $datetime;
        } else {
            $timestamp = strtotime($datetime);
        }
        
        $diff = time() - $timestamp;
        
        if ($diff < 60) {
            return 'منذ لحظات - Just now';
        } elseif ($diff < 3600) {
            $minutes = floor($diff / 60);
            return "منذ {$minutes} دقيقة - {$minutes} minute(s) ago";
        } elseif ($diff < 86400) {
            $hours = floor($diff / 3600);
            return "منذ {$hours} ساعة - {$hours} hour(s) ago";
        } elseif ($diff < 604800) {
            $days = floor($diff / 86400);
            return "منذ {$days} يوم - {$days} day(s) ago";
        } elseif ($diff < 2592000) {
            $weeks = floor($diff / 604800);
            return "منذ {$weeks} أسبوع - {$weeks} week(s) ago";
        } elseif ($diff < 31536000) {
            $months = floor($diff / 2592000);
            return "منذ {$months} شهر - {$months} month(s) ago";
        } else {
            $years = floor($diff / 31536000);
            return "منذ {$years} سنة - {$years} year(s) ago";
        }
    }
    
    /**
     * التحقق من أن التاريخ في المستقبل
     * 
     * @param string|int $datetime
     * @return bool
     */
    public static function isFutureDate($datetime) {
        if (is_numeric($datetime)) {
            return $datetime > time();
        }
        return strtotime($datetime) > time();
    }
    
    /**
     * التحقق من أن التاريخ في الماضي
     * 
     * @param string|int $datetime
     * @return bool
     */
    public static function isPastDate($datetime) {
        if (is_numeric($datetime)) {
            return $datetime < time();
        }
        return strtotime($datetime) < time();
    }
    
    // ===========================================
    // 2️⃣ دوال النصوص (String Functions)
    // ===========================================
    
    /**
     * اختصار النص
     * 
     * @param string $text
     * @param int $length
     * @param string $suffix
     * @return string
     */
    public static function truncate($text, $length = 100, $suffix = '... ') {
        if (mb_strlen($text, 'UTF-8') <= $length) {
            return $text;
        }
        
        return mb_substr($text, 0, $length, 'UTF-8') . $suffix;
    }
    
    /**
     * إنشاء Slug من النص
     * 
     * @param string $text
     * @return string
     */
    public static function createSlug($text) {
        // تحويل إلى lowercase
        $text = strtolower($text);
        
        // استبدال المسافات بـ -
        $text = preg_replace('/\s+/', '-', $text);
        
        // إزالة الرموز الخاصة
        $text = preg_replace('/[^a-z0-9\-\_]/', '', $text);
        
        // إزالة - المتكررة
        $text = preg_replace('/-+/', '-', $text);
        
        // إزالة - من البداية والنهاية
        $text = trim($text, '-');
        
        return $text;
    }
    
    /**
     * تحويل النص إلى CamelCase
     * 
     * @param string $text
     * @return string
     */
    public static function toCamelCase($text) {
        $text = str_replace(['-', '_'], ' ', $text);
        $text = ucwords($text);
        return str_replace(' ', '', $text);
    }
    
    /**
     * تحويل النص إلى snake_case
     * 
     * @param string $text
     * @return string
     */
    public static function toSnakeCase($text) {
        $text = preg_replace('/([a-z])([A-Z])/', '$1_$2', $text);
        return strtolower($text);
    }
    
    /**
     * إخفاء جزء من النص (مثل:   البريد الإلكتروني)
     * 
     * @param string $text
     * @param int $showFirst عدد الأحرف الأولى
     * @param int $showLast عدد الأحرف الأخيرة
     * @param string $mask رمز الإخفاء
     * @return string
     */
    public static function maskString($text, $showFirst = 3, $showLast = 3, $mask = '*') {
        $length = mb_strlen($text, 'UTF-8');
        
        if ($length <= ($showFirst + $showLast)) {
            return $text;
        }
        
        $first = mb_substr($text, 0, $showFirst, 'UTF-8');
        $last = mb_substr($text, -$showLast, $showLast, 'UTF-8');
        $middle = str_repeat($mask, $length - $showFirst - $showLast);
        
        return $first .  $middle . $last;
    }
    
    /**
     * إخفاء البريد الإلكتروني
     * 
     * @param string $email
     * @return string
     */
    public static function maskEmail($email) {
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $email;
        }
        
        list($username, $domain) = explode('@', $email);
        
        $maskedUsername = self::maskString($username, 2, 1);
        
        return $maskedUsername . '@' . $domain;
    }
    
    /**
     * إخفاء رقم الجوال
     * 
     * @param string $phone
     * @return string
     */
    public static function maskPhone($phone) {
        return self::maskString($phone, 3, 2);
    }
    
    // ===========================================
    // 3️⃣ دوال الأرقام (Number Functions)
    // ===========================================
    
    /**
     * تنسيق الرقم بالفواصل
     * 
     * @param float $number
     * @param int $decimals
     * @return string
     */
    public static function formatNumber($number, $decimals = 2) {
        return number_format($number, $decimals, '.', ',');
    }
    
    /**
     * تنسيق المبلغ المالي
     * 
     * @param float $amount
     * @param string $currency
     * @param bool $showSymbol
     * @return string
     */
    public static function formatMoney($amount, $currency = null, $showSymbol = true) {
        $currency = $currency ?? DEFAULT_CURRENCY;
        $formattedAmount = self::formatNumber($amount, 2);
        
        if ($showSymbol) {
            $symbols = [
                'SAR' => 'ر.س',
                'USD' => '$',
                'EUR' => '€',
                'AED' => 'د.إ',
                'EGP' => 'ج.م',
                'KWD' => 'د. ك',
                'GBP' => '£',
                'JPY' => '¥',
                'INR' => '₹'
            ];
            
            $symbol = $symbols[$currency] ?? $currency;
            
            // العربية من اليمين، باقي العملات من اليسار
            if (in_array($currency, ['SAR', 'AED', 'EGP', 'KWD'])) {
                return $formattedAmount . ' ' . $symbol;
            } else {
                return $symbol . $formattedAmount;
            }
        }
        
        return $formattedAmount;
    }
    
    /**
     * تحويل العملة (بسيط، يمكن توسيعه)
     * 
     * @param float $amount
     * @param string $from
     * @param string $to
     * @return float
     */
    public static function convertCurrency($amount, $from, $to) {
        if ($from === $to) {
            return $amount;
        }
        
        $rates = EXCHANGE_RATES ?? [
            'SAR' => 1,
            'USD' => 3.75,
            'EUR' => 4.0,
            'AED' => 1.0,
            'EGP' => 0.24,
            'KWD' => 12.3
        ];
        
        // تحويل إلى SAR أولاً
        $inSAR = $amount / ($rates[$from] ?? 1);
        
        // ثم إلى العملة المطلوبة
        return $inSAR * ($rates[$to] ?? 1);
    }
    
    /**
     * حساب النسبة المئوية
     * 
     * @param float $part الجزء
     * @param float $total الكل
     * @param int $decimals
     * @return float
     */
    public static function calculatePercentage($part, $total, $decimals = 2) {
        if ($total == 0) {
            return 0;
        }
        
        return round(($part / $total) * 100, $decimals);
    }
    
    /**
     * حساب قيمة النسبة المئوية
     * 
     * @param float $amount
     * @param float $percentage
     * @return float
     */
    public static function applyPercentage($amount, $percentage) {
        return $amount * ($percentage / 100);
    }
    
    /**
     * تقريب إلى أقرب 5
     * 
     * @param float $number
     * @return float
     */
    public static function roundToNearest5($number) {
        return round($number / 5) * 5;
    }
    
    // ===========================================
    // 4️⃣ دوال المصفوفات (Array Functions)
    // ===========================================
    
    /**
     * البحث في مصفوفة متعددة الأبعاد
     * 
     * @param array $array
     * @param string $key
     * @param mixed $value
     * @return array|null
     */
    public static function searchInArray($array, $key, $value) {
        foreach ($array as $item) {
            if (isset($item[$key]) && $item[$key] == $value) {
                return $item;
            }
        }
        return null;
    }
    
    /**
     * استخراج عمود من مصفوفة متعددة الأبعاد
     * 
     * @param array $array
     * @param string $column
     * @return array
     */
    public static function pluck($array, $column) {
        return array_column($array, $column);
    }
    
    /**
     * مجموع عمود في مصفوفة
     * 
     * @param array $array
     * @param string $column
     * @return float
     */
    public static function sumColumn($array, $column) {
        return array_sum(array_column($array, $column));
    }
    
    /**
     * تصفية مصفوفة حسب شرط
     * 
     * @param array $array
     * @param callable $callback
     * @return array
     */
    public static function filterArray($array, $callback) {
        return array_filter($array, $callback);
    }
    
    /**
     * ترتيب مصفوفة متعددة الأبعاد
     * 
     * @param array $array
     * @param string $key
     * @param string $direction ASC or DESC
     * @return array
     */
    public static function sortArray($array, $key, $direction = 'ASC') {
        usort($array, function($a, $b) use ($key, $direction) {
            if ($direction === 'ASC') {
                return $a[$key] <=> $b[$key];
            } else {
                return $b[$key] <=> $a[$key];
            }
        });
        
        return $array;
    }
    
    // ===========================================
    // 5️⃣ دوال الملفات (File Functions)
    // ===========================================
    
    /**
     * تحويل حجم الملف من بايت
     * 
     * @param int $bytes
     * @param int $precision
     * @return string
     */
    public static function formatBytes($bytes, $precision = 2) {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
    
    /**
     * الحصول على امتداد الملف
     * 
     * @param string $filename
     * @return string
     */
    public static function getFileExtension($filename) {
        return strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    }
    
    /**
     * الحصول على اسم الملف بدون الامتداد
     * 
     * @param string $filename
     * @return string
     */
    public static function getFileNameWithoutExtension($filename) {
        return pathinfo($filename, PATHINFO_FILENAME);
    }
    
    /**
     * التحقق من نوع الملف
     * 
     * @param string $filename
     * @param array $allowedTypes
     * @return bool
     */
    public static function isAllowedFileType($filename, $allowedTypes) {
        $extension = self::getFileExtension($filename);
        return in_array($extension, $allowedTypes);
    }
    
    // ===========================================
    // 6️⃣ دوال URL و Redirect
    // ===========================================
    
    /**
     * بناء URL مع Query Parameters
     * 
     * @param string $baseUrl
     * @param array $params
     * @return string
     */
    public static function buildUrl($baseUrl, $params = []) {
        if (empty($params)) {
            return $baseUrl;
        }
        
        $query = http_build_query($params);
        $separator = strpos($baseUrl, '?') !== false ? '&' : '?';
        
        return $baseUrl . $separator . $query;
    }
    
    /**
     * الحصول على URL الحالي
     * 
     * @return string
     */
    public static function getCurrentUrl() {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        
        return $protocol . '://' . $host . $uri;
    }
    
    /**
     * إعادة توجيه
     * 
     * @param string $url
     * @param int $statusCode
     */
    public static function redirect($url, $statusCode = 302) {
        header('Location: ' . $url, true, $statusCode);
        exit;
    }
    
    // ===========================================
    // 7️⃣ دوال JSON
    // ===========================================
    
    /**
     * تحويل إلى JSON بأمان
     * 
     * @param mixed $data
     * @param bool $pretty
     * @return string|false
     */
    public static function toJSON($data, $pretty = false) {
        $options = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
        
        if ($pretty) {
            $options |= JSON_PRETTY_PRINT;
        }
        
        return json_encode($data, $options);
    }
    
    /**
     * تحويل من JSON بأمان
     * 
     * @param string $json
     * @param bool $assoc
     * @return mixed
     */
    public static function fromJSON($json, $assoc = true) {
        return json_decode($json, $assoc);
    }
    
    /**
     * التحقق من صحة JSON
     * 
     * @param string $json
     * @return bool
     */
    public static function isValidJSON($json) {
        json_decode($json);
        return json_last_error() === JSON_ERROR_NONE;
    }
    
    // ===========================================
    // 8️⃣ دوال عشوائية (Random)
    // ===========================================
    
    /**
     * اختيار عنصر عشوائي من مصفوفة
     * 
     * @param array $array
     * @return mixed
     */
    public static function randomElement($array) {
        if (empty($array)) {
            return null;
        }
        
        return $array[array_rand($array)];
    }
    
    /**
     * توليد لون عشوائي (Hex)
     * 
     * @return string
     */
    public static function randomColor() {
        return '#' . str_pad(dechex(mt_rand(0, 0xFFFFFF)), 6, '0', STR_PAD_LEFT);
    }
    
    // ===========================================
    // 9️⃣ دوال الترجمة (Translation)
    // ===========================================
    
    /**
     * ترجمة نص بسيطة (يمكن ربطها بـ i18n.php للمزيد من الدعم)
     * 
     * @param string $key
     * @param string $lang
     * @return string
     */
    public static function translate($key, $lang = null) {
        $lang = $lang ?? DEFAULT_LANGUAGE;
        
        // قاموس بسيط للترجمة (يمكن توسيعه أو ربطه بـ i18n)
        $translations = [
            'ar' => [
                'welcome' => 'مرحباً',
                'thank_you' => 'شكراً لك',
                'success' => 'نجاح',
                'error' => 'خطأ',
                'loading' => 'جاري التحميل...',
            ],
            'en' => [
                'welcome' => 'Welcome',
                'thank_you' => 'Thank you',
                'success' => 'Success',
                'error' => 'Error',
                'loading' => 'Loading...',
            ],
            // أضف لغات إضافية هنا للدعم العالمي
            'fr' => [
                'welcome' => 'Bienvenue',
                'thank_you' => 'Merci',
                'success' => 'Succès',
                'error' => 'Erreur',
                'loading' => 'Chargement...',
            ],
            'es' => [
                'welcome' => 'Bienvenido',
                'thank_you' => 'Gracias',
                'success' => 'Éxito',
                'error' => 'Error',
                'loading' => 'Cargando...',
            ]
        ];
        
        return $translations[$lang][$key] ?? $key;
    }
    
    // ===========================================
    // 🔟 دوال متنوعة (Miscellaneous)
    // ===========================================
    
    /**
     * توليد UUID v4
     * 
     * @return string
     */
    public static function generateUUID() {
        $data = random_bytes(16);
        
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
    
    /**
     * التحقق من قيمة فارغة
     * 
     * @param mixed $value
     * @return bool
     */
    public static function isEmpty($value) {
        return empty($value) && $value !== '0' && $value !== 0;
    }
    
    /**
     * الحصول على قيمة افتراضية إذا كانت فارغة
     * 
     * @param mixed $value
     * @param mixed $default
     * @return mixed
     */
    public static function getOrDefault($value, $default = null) {
        return self::isEmpty($value) ? $default : $value;
    }
    
    /**
     * طباعة بيانات للتطوير (Debug)
     * 
     * @param mixed $data
     * @param bool $die
     */
    public static function dd($data, $die = true) {
        if (DEBUG_MODE) {
            echo '<pre>';
            print_r($data);
            echo '</pre>';
            
            if ($die) {
                die();
            }
        }
    }
    
    /**
     * تسجيل معلومات
     * 
     * @param string $message
     * @param string $level
     */
    public static function log($message, $level = 'INFO') {
        if (LOG_ENABLED) {
            $logMessage = sprintf(
                "[%s] [%s] %s\n",
                date('Y-m-d H:i:s'),
                $level,
                $message
            );
            
            error_log($logMessage, 3, LOG_FILE_API);
        }
    }
}

// ===========================================
// دوال عامة سريعة (Global Helper Functions)
// ===========================================

if (!function_exists('formatBytes')) {
    function formatBytes($bytes, $precision = 2) {
        return Utils::formatBytes($bytes, $precision);
    }
}

if (!function_exists('formatMoney')) {
    function formatMoney($amount, $currency = null) {
        return Utils::formatMoney($amount, $currency);
    }
}

if (!function_exists('timeAgo')) {
    function timeAgo($datetime) {
        return Utils::timeAgo($datetime);
    }
}

if (! function_exists('dd')) {
    function dd($data) {
        Utils::dd($data, true);
    }
}

// ===========================================
// ✅ تم تحميل Utils Helper بنجاح
// ===========================================

?>