<?php
declare(strict_types=1);

require_once __DIR__ . '/../BaseRepository.php';

class AiRepository extends BaseRepository
{
    public function __construct(PDO $pdo)
    {
        parent::__construct($pdo);
    }

    public function getTopViewedProducts(int $userId, int $limit): array
    {
        $sql = "
            SELECT p.id, 
                   COALESCE(pt.name, '') AS name, 
                   COUNT(uv.product_id) as views
            FROM user_views uv
            JOIN products p ON uv.product_id = p.id
            LEFT JOIN product_translations pt 
                ON pt.product_id = p.id AND pt.language_code = 'ar'
            WHERE uv.user_id = :user_id 
              AND p.tenant_id = :tenant_id
            GROUP BY p.id, pt.name
            ORDER BY views DESC
            LIMIT :limit
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':tenant_id', $this->getTenantId(), PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getDailySalesLast30Days(): array
    {
        // This is a tenant-scoped aggregate by default now
        $sql = "
            SELECT DATE(created_at) as date, COUNT(*) as sales
            FROM orders
            WHERE created_at >= CURDATE() - INTERVAL 30 DAY
              AND tenant_id = :tenant_id
            GROUP BY DATE(created_at)
            ORDER BY date
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':tenant_id' => $this->getTenantId()]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}