<?php
declare(strict_types=1);

namespace Shared\Core\Cache;

interface CacheDriverInterface
{
    public function get(string $key): mixed;
    public function set(string $key, mixed $value, int $ttl = 3600): void;
    public function delete(string $key): void;
    public function has(string $key): bool;
    public function clear(): void;
    public function isAvailable(): bool;
}
