<?php
declare(strict_types=1);

namespace Shared\Core\Cache;

use Redis;
use Throwable;

class RedisCacheDriver implements CacheDriverInterface
{
    private ?Redis $redis = null;
    private bool $available = false;
    private string $prefix;

    public function __construct(array $config = [])
    {
        if (!extension_loaded('redis')) {
            return;
        }

        $this->prefix = $config['prefix'] ?? 'app:';

        try {
            $this->redis = new Redis();
            $connected = $this->redis->connect(
                $config['host'] ?? '127.0.0.1',
                (int) ($config['port'] ?? 6379),
                (float) ($config['timeout'] ?? 1.5)
            );

            if ($connected) {
                if (!empty($config['password'])) {
                    $this->redis->auth($config['password']);
                }
                $this->redis->select((int)($config['db'] ?? 0));
                $this->redis->setOption(Redis::OPT_SERIALIZER, Redis::SERIALIZER_PHP);
                $this->redis->setOption(Redis::OPT_PREFIX, $this->prefix);
                $this->available = true;
            }
        } catch (\RedisException $e) {
            $this->available = false;
            if (function_exists('safe_log')) {
                safe_log('warning', 'RedisCacheDriver: Connection failed', ['error' => $e->getMessage()]);
            }
        }
    }

    public function get(string $key): mixed
    {
        if (!$this->available || !$this->redis) return null;
        try {
            $value = $this->redis->get($key);
            return $value === false ? null : $value;
        } catch (\RedisException $e) {
            if (function_exists('safe_log')) {
                safe_log('error', 'RedisCacheDriver::get failure', ['key' => $key, 'error' => $e->getMessage()]);
            }
            return null;
        }
    }

    public function set(string $key, mixed $value, int $ttl = 3600): void
    {
        if (!$this->available || !$this->redis) return;
        try {
            $this->redis->setex($key, max(1, $ttl), $value);
        } catch (\RedisException $e) {
            if (function_exists('safe_log')) {
                safe_log('error', 'RedisCacheDriver::set failure', ['key' => $key, 'error' => $e->getMessage()]);
            }
        }
    }

    public function delete(string $key): void
    {
        if (!$this->available || !$this->redis) return;
        try {
            $this->redis->del($key);
        } catch (\RedisException $e) {
            if (function_exists('safe_log')) {
                safe_log('error', 'RedisCacheDriver::delete failure', ['key' => $key, 'error' => $e->getMessage()]);
            }
        }
    }

    public function has(string $key): bool
    {
        if (!$this->available || !$this->redis) return false;
        try {
            return (bool)$this->redis->exists($key);
        } catch (\RedisException $e) {
            if (function_exists('safe_log')) {
                safe_log('error', 'RedisCacheDriver::has failure', ['key' => $key, 'error' => $e->getMessage()]);
            }
            return false;
        }
    }

    public function clear(): void
    {
        if (!$this->available || !$this->redis) return;
        try {
            $iterator = null;
            while (($keys = $this->redis->scan($iterator, '*', 100)) !== false) {
                if ($keys) $this->redis->del($keys);
            }
        } catch (\RedisException $e) {
            if (function_exists('safe_log')) {
                safe_log('error', 'RedisCacheDriver::clear failure', ['error' => $e->getMessage()]);
            }
        }
    }

    public function isAvailable(): bool
    {
        return $this->available;
    }

    public function getRedis(): ?Redis
    {
        return $this->redis;
    }
}
