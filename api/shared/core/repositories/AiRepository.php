<?php
declare(strict_types=1);

class AiRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function getTopViewedProducts(int $userId, int $limit): array
    {
        $stmt = $this->pdo->prepare("
            SELECT p.id, p.name, COUNT(uv.product_id) as views
            FROM user_views uv
            JOIN products p ON uv.product_id = p.id
            WHERE uv.user_id = ?
            GROUP BY p.id
            ORDER BY views DESC
            LIMIT ?
        ");
        $stmt->execute([$userId, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getDailySalesLast30Days(): array
    {
        $stmt = $this->pdo->query("
            SELECT DATE(created_at) as date, COUNT(*) as sales
            FROM orders /* tenant_id: platform-wide aggregate */
            WHERE created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY DATE(created_at)
            ORDER BY date
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
