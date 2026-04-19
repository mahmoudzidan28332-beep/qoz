<?php declare(strict_types=1);
// helpers/SettingsHelper.php

require_once __DIR__ . '/../core/repositories/SettingsRepository.php';

class SettingsHelper {
    private static $cache = [];
    
    public static function get(string $key, PDO $pdo): mixed {
        if (isset(self::$cache[$key])) {
            return self::$cache[$key];
        }
        
        $repo = new SettingsRepository($pdo);
        $row = $repo->getSettingByKey($key);
        
        $value = $row ? json_decode($row['value'], true) : null;
        self::$cache[$key] = $value;
        
        return $value;
    }
    
    public static function set(string $key, mixed $value, PDO $pdo): void {
        $jsonValue = json_encode($value);
        $repo = new SettingsRepository($pdo);
        $repo->upsertSetting($key, $jsonValue);
        
        self::$cache[$key] = $value;
    }
}