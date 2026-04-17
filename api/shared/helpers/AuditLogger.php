<?php
declare(strict_types=1);

require_once __DIR__ . '/../core/repositories/AuditRepository.php';

class AuditLogger {
    private static ?PDO $pdo = null;

    public static function init(PDO $pdo): void {
        self::$pdo = $pdo;
    }

    public static function log(string $action, string $entityType, ?int $entityId = null, ?array $payload = null): void {
        if (!self::$pdo) return;
        try {
            $tenantId = $_SESSION['tenant_id'] ?? null;
            $userId = $_SESSION['user_id'] ?? null;
            $ip = $_SERVER['REMOTE_ADDR'] ?? null;
            $ua = isset($_SERVER['HTTP_USER_AGENT']) ? substr($_SERVER['HTTP_USER_AGENT'], 0, 255) : null;
            $repo = new AuditRepository(self::$pdo);
            $repo->insertAuditLog(
                $tenantId,
                $entityType,
                $entityId,
                $userId,
                $action,
                $ip,
                $ua,
                $payload ? json_encode($payload, JSON_UNESCAPED_UNICODE) : null
            );
        } catch (\Throwable $e) {
            // silently fail - audit should never break main operations
        }
    }
}