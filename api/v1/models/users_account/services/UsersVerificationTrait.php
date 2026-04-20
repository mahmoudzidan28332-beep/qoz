<?php
declare(strict_types=1);

trait UsersVerificationTrait
{
    public function findPendingVerification(string $tokenHash): ?array
    {
        return $this->phoneVerifRepo->findPendingByTokenHash($tokenHash);
    }

    public function findUsedTokenUserStatus(string $tokenHash): ?array
    {
        return $this->phoneVerifRepo->findUsedTokenUserStatus($tokenHash);
    }

    public function markVerificationUsed(int $id): void
    {
        $this->phoneVerifRepo->markUsed($id);
    }

    public function countRecentVerificationsByIp(string $ip): int
    {
        return $this->phoneVerifRepo->countRecentByIp($ip);
    }

    public function countRecentVerificationsByUserId(int $userId): int
    {
        return $this->phoneVerifRepo->countRecentByUserId($userId);
    }
}
