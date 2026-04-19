<?php
declare(strict_types=1);

class SmsRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function insertSmsLog(string $phone, string $message, string $status, ?string $messageId, string $language): bool
    {
        $stmt = $this->pdo->prepare("INSERT INTO sms_logs (phone, message, status, message_id, language, sent_at) VALUES (?, ?, ?, ?, ?, NOW())");
        return $stmt->execute([$phone, $message, $status, $messageId, $language]);
    }
}
