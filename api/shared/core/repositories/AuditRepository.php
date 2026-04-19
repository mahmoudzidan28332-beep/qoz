<?php
declare(strict_types=1);

class AuditRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function insertAuditLog(
        ?int $tenantId,
        ?string $entityType,
        ?int $entityId,
        ?int $userId,
        ?string $action,
        ?string $ipAddress,
        ?string $userAgent,
        ?string $payloadJson
    ): void {
        $stmt = $this->pdo->prepare(
            "INSERT INTO audit_logs (tenant_id, entity_type, entity_id, user_id, action, ip_address, user_agent, payload) "
            . "VALUES (:tenant_id, :entity_type, :entity_id, :user_id, :action, :ip, :ua, :payload)"
        );
        $stmt->execute([
            ':tenant_id' => $tenantId,
            ':entity_type' => $entityType,
            ':entity_id' => $entityId,
            ':user_id' => $userId,
            ':action' => $action,
            ':ip' => $ipAddress,
            ':ua' => $userAgent,
            ':payload' => $payloadJson,
        ]);
    }
}
