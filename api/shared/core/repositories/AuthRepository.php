<?php

declare(strict_types=1);

// ===========================================
// AuthRepository.php  —  PRODUCTION VERSION
// ===========================================

class AuthRepository
{
    public function __construct(private readonly PDO $pdo) {}

    // ------------------------------------------
    // البحث عن مستخدم بالـ username
    // ------------------------------------------

    public function findUserByUsername(string $username): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT id, username, email, password, user_type, is_active
             FROM users
             WHERE username = ?
             LIMIT 1"
        );
        $stmt->bindValue(1, $username, PDO::PARAM_STR);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    // ------------------------------------------
    // البحث عن مستخدم بالـ email
    // ------------------------------------------

    public function findUserByEmail(string $email): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT id, username, email, password, user_type, is_active
             FROM users
             WHERE email = ?
             LIMIT 1"
        );
        $stmt->bindValue(1, $email, PDO::PARAM_STR);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    // ------------------------------------------
    // البحث عن مستخدم بالـ ID
    // ------------------------------------------

    public function findUserById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT id, username, email, user_type, is_active
             FROM users
             WHERE id = ?
             LIMIT 1"
        );
        $stmt->bindValue(1, $id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    // ------------------------------------------
    // تحديث آخر تسجيل دخول
    // ------------------------------------------

    public function updateLastLogin(int $userId): bool
    {
        $stmt = $this->pdo->prepare(
            "UPDATE users SET last_login_at = NOW() WHERE id = ?"
        );
        $stmt->bindValue(1, $userId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->rowCount() > 0;
    }

    // ------------------------------------------
    // تحديث كلمة المرور
    // ------------------------------------------

    public function updatePassword(int $userId, string $hashedPassword): bool
    {
        $stmt = $this->pdo->prepare(
            "UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?"
        );
        $stmt->execute([$hashedPassword, $userId]);

        return $stmt->rowCount() > 0;
    }

    // ------------------------------------------
    // التحقق من وجود username أو email
    // ------------------------------------------

    public function existsByUsernameOrEmail(string $username, string $email): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT 1 FROM users WHERE username = ? OR email = ? LIMIT 1"
        );
        $stmt->execute([$username, $email]);

        return (bool) $stmt->fetchColumn();
    }
}