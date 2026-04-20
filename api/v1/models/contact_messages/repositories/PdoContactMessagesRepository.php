<?php
declare(strict_types=1);

/**
 * Contact Messages Repository
 * Handles database operations for the contact_messages table.
 */
final class PdoContactMessagesRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Insert a new contact message and return the new ID.
     */
    public function createMessage(int $tenantId, int $userId, string $name, string $email, string $subject, string $message): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO contact_messages (tenant_id, user_id, name, email, subject, message)
             VALUES (:tenant_id, :user_id, :name, :email, :subject, :message)"
        );
        $stmt->execute([
            ':tenant_id' => $tenantId,
            ':user_id'   => $userId,
            ':name'      => $name,
            ':email'     => $email,
            ':subject'   => $subject,
            ':message'   => $message,
        ]);

        return (int)$this->pdo->lastInsertId();
    }
}
