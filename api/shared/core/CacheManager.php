<?php
declare(strict_types=1);

require_once __DIR__ . '/Cache/CacheDriverInterface.php';
require_once __DIR__ . '/Cache/MemoryCacheDriver.php';
require_once __DIR__ . '/Cache/FileCacheDriver.php';
require_once __DIR__ . '/Cache/RedisCacheDriver.php';
require_once __DIR__ . '/Cache/DatabaseCacheDriver.php';
require_once __DIR__ . '/Cache/CacheTagManager.php';

use Shared\Core\Cache\CacheDriverInterface;
use Shared\Core\Cache\MemoryCacheDriver;
use Shared\Core\Cache\FileCacheDriver;
use Shared\Core\Cache\RedisCacheDriver;
use Shared\Core\Cache\DatabaseCacheDriver;
use Shared\Core\Cache\CacheTagManager;

final class CacheManager
{
    private static ?CacheManager $instance = null;
    
    /** @var CacheDriverInterface[] */
    private array $drivers = [];
    private string $primaryBackend = 'memory';
    private array $stats = ['hits' => 0, 'misses' => 0, 'sets' => 0];
    private CacheTagManager $tagManager;

    private function __construct()
    {
        $baseDir = defined('BASE_DIR') ? BASE_DIR : dirname(__DIR__, 2);
        $fileCacheDir = rtrim($baseDir, '/\\') . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR;
        
        $this->tagManager = new CacheTagManager($fileCacheDir . 'tags');

        // Initialize drivers
        $this->drivers['memory'] = new MemoryCacheDriver((int)(getenv('CACHE_MEMORY_MAX_ITEMS') ?: 1000));
        $this->drivers['file'] = new FileCacheDriver($fileCacheDir);
        
        $pdo = $GLOBALS['ADMIN_DB'] ?? $GLOBALS['CONTAINER']['pdo'] ?? null;
        $this->drivers['database'] = new DatabaseCacheDriver($pdo, (string)(getenv('CACHE_DB_TABLE') ?: 'system_cache'));
        
        $this->drivers['redis'] = new RedisCacheDriver([
            'host' => getenv('REDIS_HOST') ?: '127.0.0.1',
            'port' => getenv('REDIS_PORT') ?: 6379,
            'password' => getenv('REDIS_PASSWORD') ?: (defined('REDIS_AUTH') ? (string)REDIS_AUTH : ''),
            'db' => getenv('REDIS_DB') ?: (defined('REDIS_DB') ? (int)REDIS_DB : 0)
        ]);

        if ($this->drivers['redis']->isAvailable()) {
            $this->primaryBackend = 'redis';
        } elseif ($this->drivers['file']->isAvailable()) {
            $this->primaryBackend = 'file';
        } else {
            $this->primaryBackend = 'memory';
        }
    }

    public static function getInstance(): CacheManager
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function get(string $key): mixed
    {
        $val = $this->drivers['memory']->get($key);
        if ($val !== null) {
            $this->stats['hits']++;
            return $val;
        }

        $val = $this->drivers[$this->primaryBackend]->get($key);
        if ($val !== null) {
            $this->stats['hits']++;
            $this->drivers['memory']->set($key, $val, 300);
            return $val;
        }

        foreach ($this->drivers as $name => $driver) {
            if ($name === 'memory' || $name === $this->primaryBackend) continue;
            $val = $driver->get($key);
            if ($val !== null) {
                $this->stats['hits']++;
                $this->drivers['memory']->set($key, $val, 300);
                return $val;
            }
        }

        $this->stats['misses']++;
        return null;
    }

    public function set(string $key, mixed $value, int $ttl = 3600, array $tags = []): void
    {
        $this->stats['sets']++;
        foreach ($this->drivers as $driver) {
            $driver->set($key, $value, $ttl);
        }

        if ($tags !== []) {
            $this->tagManager->addTags($key, $tags);
        }
    }

    public function remember(string $key, int $ttl, callable $resolver, array $tags = []): mixed
    {
        $cached = $this->get($key);
        if ($cached !== null) return $cached;

        $value = $resolver();
        $this->set($key, $value, $ttl, $tags);
        return $value;
    }

    public function delete(string $key): void
    {
        foreach ($this->drivers as $driver) {
            $driver->delete($key);
        }
        $this->tagManager->removeKeyFromTags($key);
    }

    public function has(string $key): bool
    {
        foreach ($this->drivers as $driver) {
            if ($driver->has($key)) return true;
        }
        return false;
    }

    public function clear(): void
    {
        foreach ($this->drivers as $driver) {
            $driver->clear();
        }
        $this->tagManager->clear();
    }

    public function invalidateByTag(string $tag): void
    {
        $keys = $this->tagManager->getKeysByTag($tag);
        foreach ($keys as $key) {
            $this->delete((string) $key);
        }
    }

    public function getStats(): array { return $this->stats; }

    public function getHitRate(): float
    {
        $total = $this->stats['hits'] + $this->stats['misses'];
        return $total > 0 ? round(($this->stats['hits'] / $total) * 100, 2) : 0.0;
    }
}
