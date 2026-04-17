<?php
declare(strict_types=1);

class MailRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function insertEmailLog(string $recipient, string $subject, string $body, string $status, string $language): bool
    {
        $stmt = $this->pdo->prepare("INSERT INTO email_logs (recipient, subject, body, status, language, sent_at) VALUES (?, ?, ?, ?, ?, NOW())");
        return $stmt->execute([$recipient, $subject, $body, $status, $language]);
    }
}
