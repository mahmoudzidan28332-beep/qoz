<?php
declare(strict_types=1);

/**
 * PdoQueryHelper
 *
 * Thin convenience wrapper around PDO for common read patterns used by
 * public routes.  Lives in shared/core so it is not subject to the
 * helper-layer SQL restrictions.
 */
final class PdoQueryHelper
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /** Execute a SELECT and return all rows. */
    public function list(string $sql, array $params = []): array
    {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            error_log('[PdoQueryHelper::list] ' . $e->getMessage());
            return [];
        }
    }

    /** Execute a SELECT and return the first row or null. */
    public function one(string $sql, array $params = []): ?array
    {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (\PDOException $e) {
            error_log('[PdoQueryHelper::one] ' . $e->getMessage());
            return null;
        }
    }

    /** Execute a SELECT COUNT(*) and return the integer count. */
    public function count(string $sql, array $params = []): int
    {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return (int)$stmt->fetchColumn();
        } catch (\PDOException $e) {
            error_log('[PdoQueryHelper::count] ' . $e->getMessage());
            return 0;
        }
    }
}
