<?php
declare(strict_types=1);

trait UsersControllerVerificationTrait
{
    public function findPendingVerification(string $tokenHash): ?array
    {
        return $this->service->findPendingVerification($tokenHash);
    }

    public function findUsedTokenUserStatus(string $tokenHash): ?array
    {
        return $this->service->findUsedTokenUserStatus($tokenHash);
    }

    public function markVerificationUsed(int $id): void
    {
        $this->service->markVerificationUsed($id);
    }

    public function countRecentVerificationsByIp(string $ip): int
    {
        return $this->service->countRecentVerificationsByIp($ip);
    }

    public function countRecentVerificationsByUserId(int $userId): int
    {
        return $this->service->countRecentVerificationsByUserId($userId);
    }

    public function createPhoneVerification(int $userId, string $tokenHash, string $deviceHash, string $sessionId, string $userAgent, string $ip, string $expiresAt): int
    {
        return $this->service->createPhoneVerification($userId, $tokenHash, $deviceHash, $sessionId, $userAgent, $ip, $expiresAt);
    }
}