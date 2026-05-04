<?php
declare(strict_types=1);

class SettingsRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function getSettingByKey(string $key): ?array
    {
        $stmt = $this->pdo->prepare("SELECT value FROM system_settings WHERE `key` = ?");
        $stmt->execute([$key]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function upsertSetting(string $key, string $jsonValue): void
    {
        $stmt = $this->pdo->prepare("INSERT INTO system_settings (`key`, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = ?");
        $stmt->execute([$key, $jsonValue, $jsonValue]);
    }
}
