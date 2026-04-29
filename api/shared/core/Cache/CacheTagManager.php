<?php
declare(strict_types=1);

namespace Shared\Core\Cache;

/**
 * CacheTagManager
 * 
 * Handles tag-based cache invalidation logic.
 */
class CacheTagManager
{
    private string $tagDir;

    public function __construct(string $tagDir)
    {
        $this->tagDir = rtrim($tagDir, '/\\') . DIRECTORY_SEPARATOR;
        if (!is_dir($this->tagDir)) {
            @mkdir($this->tagDir, 0755, true);
        }
    }

    public function addTags(string $key, array $tags): void
    {
        foreach ($tags as $tag) {
            $tag = trim((string) $tag);
            if ($tag === '') continue;

            $members = $this->readTagIndex($tag);
            if (!in_array($key, $members, true)) {
                $members[] = $key;
                $this->writeTagIndex($tag, $members);
            }
        }
    }

    public function getKeysByTag(string $tag): array
    {
        $keys = $this->readTagIndex($tag);
        $this->deleteTagIndex($tag);
        return array_values(array_unique($keys));
    }

    public function removeKeyFromTags(string $key): void
    {
        $files = glob($this->tagDir . '*.json');
        if (!$files) return;

        foreach ($files as $tagFile) {
            $content = @file_get_contents($tagFile);
            if (!$content) continue;

            $members = json_decode($content, true);
            if (!is_array($members)) {
                @unlink($tagFile);
                continue;
            }

            $filtered = array_values(array_filter($members, static fn ($member): bool => (string)$member !== $key));
            if ($filtered === []) {
                @unlink($tagFile);
            } elseif (count($filtered) !== count($members)) {
                @file_put_contents($tagFile, json_encode($filtered, JSON_UNESCAPED_UNICODE), LOCK_EX);
            }
        }
    }

    public function clear(): void
    {
        $files = glob($this->tagDir . '*.json');
        if (!$files) return;
        foreach ($files as $file) {
            @unlink($file);
        }
    }

    private function readTagIndex(string $tag): array
    {
        $file = $this->tagDir . md5($tag) . '.json';
        if (!is_file($file)) return [];
        $content = @file_get_contents($file);
        if (!$content) return [];
        $members = json_decode($content, true);
        return is_array($members) ? array_values(array_unique(array_map('strval', $members))) : [];
    }

    private function writeTagIndex(string $tag, array $members): void
    {
        @file_put_contents(
            $this->tagDir . md5($tag) . '.json',
            json_encode(array_values(array_unique($members)), JSON_UNESCAPED_UNICODE),
            LOCK_EX
        );
    }

    private function deleteTagIndex(string $tag): void
    {
        @unlink($this->tagDir . md5($tag) . '.json');
    }
}
