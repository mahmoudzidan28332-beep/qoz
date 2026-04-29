<?php
declare(strict_types=1);

namespace Shared\Core\Cache;

class MemoryCacheDriver implements CacheDriverInterface
{
    private array $memory = [];
    private int $maxSize;

    public function __construct(int $maxSize = 1000)
    {
        $this->maxSize = $maxSize;
    }

    public function get(string $key): mixed
    {
        $this->cleanup();
        if (isset($this->memory[$key])) {
            return $this->memory[$key]['data'];
        }
        return null;
    }

    public function set(string $key, mixed $value, int $ttl = 3600): void
    {
        $this->cleanup();
        if (count($this->memory) >= $this->maxSize) {
            array_shift($this->memory);
        }
        $this->memory[$key] = [
            'data' => $value,
            'expires' => time() + $ttl,
        ];
    }

    public function delete(string $key): void
    {
        unset($this->memory[$key]);
    }

    public function has(string $key): bool
    {
        $this->cleanup();
        return isset($this->memory[$key]);
    }

    public function clear(): void
    {
        $this->memory = [];
    }

    public function isAvailable(): bool
    {
        return true;
    }

    private function cleanup(): void
    {
        $now = time();
        foreach ($this->memory as $key => $item) {
            if (($item['expires'] ?? 0) <= $now) {
                unset($this->memory[$key]);
            }
        }
    }

    public function count(): int
    {
        return count($this->memory);
    }
}
