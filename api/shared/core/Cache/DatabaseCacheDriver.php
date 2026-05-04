<?php
declare(strict_types=1);

namespace Shared\Core\Cache;

use PDO;
use Throwable;

class DatabaseCacheDriver implements CacheDriverInterface
{
    private ?PDO $pdo;
    private string $table;
    private string $prefix;
    private bool $available = false;

    public function __construct(?PDO $pdo, string $table = 'system_cache', string $prefix = 'app:')
    {
        $this->pdo = $pdo;
        $this->table = $table;
        $this->prefix = $prefix;
        
        if ($this->pdo instanceof PDO) {
            $this->available = $this->ensureTable();
        }
    }

    public function get(string $key): mixed
    {
        if (!$this->available) return null;
        try {
            $stmt = $this->pdo->prepare(
                "SELECT cache_value, expires_at FROM `{$this->table}` WHERE cache_key = ? LIMIT 1"
            );
            $stmt->execute([$this->prefix . $key]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row) return null;

            if (strtotime($row['expires_at']) < time()) {
                $this->delete($key);
                return null;
            }

            $payload = json_decode($row['cache_value'], true);
            return is_array($payload) && array_key_exists('data', $payload) ? $payload['data'] : null;
        } catch (\PDOException $e) {
            if (function_exists('safe_log')) {
                safe_log('error', 'DatabaseCacheDriver::get failure', ['key' => $key, 'error' => $e->getMessage()]);
            }
            return null;
        }
    }

    public function set(string $key, mixed $value, int $ttl = 3600): void
    {
        if (!$this->available) return;
        try {
            $sql = "INSERT INTO `{$this->table}` (cache_key, cache_value, expires_at, created_at, updated_at)
                    VALUES (:key, :val, :exp, NOW(), NOW())
                    ON DUPLICATE KEY UPDATE 
                        cache_value = VALUES(cache_value), 
                        expires_at = VALUES(expires_at), 
                        updated_at = NOW()";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':key' => $this->prefix . $key,
                ':val' => json_encode(['data' => $value], JSON_UNESCAPED_UNICODE),
                ':exp' => date('Y-m-d H:i:s', time() + $ttl)
            ]);
        } catch (\PDOException $e) {
            if (function_exists('safe_log')) {
                safe_log('error', 'DatabaseCacheDriver::set failure', ['key' => $key, 'error' => $e->getMessage()]);
            }
        }
    }

    public function delete(string $key): void
    {
        if (!$this->available) return;
        try {
            $stmt = $this->pdo->prepare("DELETE FROM `{$this->table}` WHERE cache_key = ?");
            $stmt->execute([$this->prefix . $key]);
        } catch (\PDOException $e) {
            if (function_exists('safe_log')) {
                safe_log('error', 'DatabaseCacheDriver::delete failure', ['key' => $key, 'error' => $e->getMessage()]);
            }
        }
    }

    public function has(string $key): bool
    {
        return $this->get($key) !== null;
    }

    public function clear(): void
    {
        if (!$this->available) return;
        try {
            $this->pdo->exec("DELETE FROM `{$this->table}` WHERE cache_key LIKE '" . addslashes($this->prefix) . "%'");
        } catch (\PDOException $e) {
            if (function_exists('safe_log')) {
                safe_log('error', 'DatabaseCacheDriver::clear failure', ['error' => $e->getMessage()]);
            }
        }
    }

    public function isAvailable(): bool
    {
        return $this->available;
    }

    private function ensureTable(): bool
    {
        try {
            $this->pdo->exec(
                "CREATE TABLE IF NOT EXISTS `{$this->table}` (
                    cache_key VARCHAR(191) NOT NULL PRIMARY KEY,
                    cache_value LONGTEXT NOT NULL,
                    expires_at DATETIME NOT NULL,
                    created_at DATETIME NOT NULL,
                    updated_at DATETIME NOT NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
            );
            return true;
        } catch (\PDOException $e) {
            if (function_exists('safe_log')) {
                safe_log('error', 'DatabaseCacheDriver::ensureTable failure', ['error' => $e->getMessage()]);
            }
            return false;
        }
    }
}
