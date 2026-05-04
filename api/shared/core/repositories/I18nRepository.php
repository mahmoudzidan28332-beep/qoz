<?php
declare(strict_types=1);

class I18nRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function getActiveLanguageCodes(): array
    {
        $stmt = $this->pdo->prepare("SELECT code FROM languages WHERE is_active = 1");
        $stmt->execute();
        return array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'code');
    }
}
