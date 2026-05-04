<?php
declare(strict_types=1);

namespace Shared\Core\Cache;

class FileCacheDriver implements CacheDriverInterface
{
    private string $cacheDir;

    public function __construct(string $cacheDir)
    {
        $this->cacheDir = rtrim($cacheDir, '/\\') . DIRECTORY_SEPARATOR;
        if (!is_dir($this->cacheDir)) {
            @mkdir($this->cacheDir, 0755, true);
        }
    }

    public function get(string $key): mixed
    {
        $file = $this->getFilePath($key);
        if (!is_file($file)) {
            return null;
        }

        $raw = @file_get_contents($file);
        $data = is_string($raw) ? @unserialize($raw) : false;

        if (!is_array($data) || !isset($data['expires'])) {
            @unlink($file);
            return null;
        }

        if ((int) $data['expires'] < time()) {
            @unlink($file);
            return null;
        }

        return $data['data'] ?? null;
    }

    public function set(string $key, mixed $value, int $ttl = 3600): void
    {
        $payload = [
            'data' => $value,
            'expires' => time() + $ttl,
        ];
        @file_put_contents($this->getFilePath($key), serialize($payload), LOCK_EX);
    }

    public function delete(string $key): void
    {
        @unlink($this->getFilePath($key));
    }

    public function has(string $key): bool
    {
        return $this->get($key) !== null;
    }

    public function clear(): void
    {
        foreach (glob($this->cacheDir . '*.cache') ?: [] as $file) {
            @unlink($file);
        }
    }

    public function isAvailable(): bool
    {
        return is_writable($this->cacheDir);
    }

    private function getFilePath(string $key): string
    {
        return $this->cacheDir . md5($key) . '.cache';
    }
}
