<?php
declare(strict_types=1);

/**
 * PdoSearchLogsRepository
 *
 * Repository for search_logs queries used by the public search_suggest route.
 */
final class PdoSearchLogsRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Upsert a search query into search_logs.
     */
    public function trackQuery(string $query, ?int $tenantId, ?int $userId, ?int $entityId, string $lang): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO search_logs (query, tenant_id, user_id, entity_id, lang, count, last_searched_at)
            VALUES (?, ?, ?, ?, ?, 1, NOW())
            ON DUPLICATE KEY UPDATE count = count + 1, last_searched_at = NOW()
        ");
        $stmt->execute([$query, $tenantId ?: null, $userId, $entityId, $lang]);
    }

    /**
     * Return popular search queries (global rows only — user_id IS NULL).
     */
    public function popular(string $lang, ?int $tenantId, int $limit = 8): array
    {
        $tenantCond  = $tenantId ? ' AND (tenant_id = ? OR tenant_id IS NULL)' : '';
        $tenantParam = $tenantId ? [$lang, $tenantId] : [$lang];

        $stmt = $this->pdo->prepare("
            SELECT query, SUM(count) AS total
            FROM search_logs
            WHERE lang = ? AND user_id IS NULL $tenantCond
            GROUP BY query
            ORDER BY total DESC
            LIMIT $limit
        ");
        $stmt->execute($tenantParam);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
