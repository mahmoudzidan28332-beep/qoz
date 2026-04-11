<?php
declare(strict_types=1);

/**
 * DeviceDetector — shared helper for parsing device info from User-Agent.
 * Used by auth.php (login auto-registration) and public/user_devices.php.
 */
final class DeviceDetector
{
    /**
     * Detect device type from user agent string.
     *
     * @return string One of: web, android, ios, other
     */
    public static function detectType(string $ua): string
    {
        if ($ua === '') return 'other';
        $uaLower = strtolower($ua);
        if (str_contains($uaLower, 'android')) return 'android';
        if (str_contains($uaLower, 'iphone') || str_contains($uaLower, 'ipad')) return 'ios';
        return 'web';
    }

    /**
     * Parse a human-readable device name from user agent string.
     * Returns e.g. "Chrome on Windows", "Safari on iOS".
     */
    public static function detectName(string $ua): string
    {
        if ($ua === '') return 'Unknown';

        // Browser detection (order matters: Edge contains "Chrome", Chrome contains "Safari")
        if (preg_match('/(?:Edg|Edge)\/[\d.]+/', $ua)) {
            $browser = 'Edge';
        } elseif (preg_match('/(?:Chrome|CriOS)\/[\d.]+/', $ua)) {
            $browser = 'Chrome';
        } elseif (preg_match('/Firefox\/[\d.]+/', $ua)) {
            $browser = 'Firefox';
        } elseif (preg_match('/Safari\/[\d.]+/', $ua) && !str_contains($ua, 'Chrome')) {
            $browser = 'Safari';
        } else {
            $browser = 'Browser';
        }

        // OS detection
        $uaLower = strtolower($ua);
        if (str_contains($uaLower, 'windows'))      $os = ' on Windows';
        elseif (str_contains($uaLower, 'macintosh')) $os = ' on macOS';
        elseif (str_contains($uaLower, 'android'))   $os = ' on Android';
        elseif (str_contains($uaLower, 'iphone'))    $os = ' on iOS';
        elseif (str_contains($uaLower, 'ipad'))      $os = ' on iPadOS';
        elseif (str_contains($uaLower, 'linux'))     $os = ' on Linux';
        else                                          $os = '';

        return $browser . $os;
    }
}