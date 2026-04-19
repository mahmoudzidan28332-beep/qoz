<?php
declare(strict_types=1);

/**
 * PdoSearchSuggestRepository
 *
 * Wraps the dynamic fulltext / LIKE search queries used by search_suggest.
 */
final class PdoSearchSuggestRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Try a FULLTEXT query first; on failure or empty result fall back to a LIKE query.
     *
     * @param string $sql        FULLTEXT SQL
     * @param array  $params     FULLTEXT params
     * @param string $likeSql    LIKE fallback SQL
     * @param array  $likeParams LIKE fallback params
     * @return array
     */
    public function fulltextSearch(string $sql, array $params, string $likeSql, array $likeParams): array
    {
        try {
            $st = $this->pdo->prepare($sql);
            $st->execute($params);
            $rows = $st->fetchAll(PDO::FETCH_ASSOC);
            if ($rows) return $rows;
        } catch (Throwable $e) {
            error_log('[PdoSearchSuggestRepository] fulltext search failed: ' . $e->getMessage());
        }
        try {
            $st = $this->pdo->prepare($likeSql);
            $st->execute($likeParams);
            return $st->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            return [];
        }
    }
}
