<?php
declare(strict_types=1);

final class PdoAuctionTranslationsRepository implements AuctionTranslationsRepositoryInterface
{
    private PDO $pdo;
    private const TABLE = 'auction_translations';
    private const ALLOWED_COLUMNS = [
        'auction_id', 'language_code', 'title', 'description', 'terms_conditions'
    ];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function all(int $auctionId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT id, auction_id, language_code, title, description, terms_conditions FROM " . self::TABLE . " WHERE auction_id = :auction_id ORDER BY language_code ASC"
        );
        $stmt->execute([':auction_id' => $auctionId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function find(int $auctionId, string $languageCode): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT id, auction_id, language_code, title, description, terms_conditions FROM " . self::TABLE . " WHERE auction_id = :auction_id AND language_code = :language_code LIMIT 1"
        );
        $stmt->execute([':auction_id' => $auctionId, ':language_code' => $languageCode]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function save(array $data): int
    {
        $data = array_intersect_key($data, array_flip(self::ALLOWED_COLUMNS));
        $stmt = $this->pdo->prepare("
            INSERT INTO " . self::TABLE . " (auction_id, language_code, title, description, terms_conditions)
            VALUES (:auction_id, :language_code, :title, :description, :terms_conditions)
            ON DUPLICATE KEY UPDATE
                title             = VALUES(title),
                description       = VALUES(description),
                terms_conditions  = VALUES(terms_conditions)
        ");
        $stmt->execute([
            ':auction_id'       => (int)$data['auction_id'],
            ':language_code'    => $data['language_code'],
            ':title'            => $data['title'],
            ':description'      => $data['description'] ?? null,
            ':terms_conditions' => $data['terms_conditions'] ?? null,
        ]);
        return (int)$this->pdo->lastInsertId() ?: (int)$data['auction_id'];
    }

    public function delete(int $auctionId, string $languageCode): bool
    {
        $stmt = $this->pdo->prepare(
            "DELETE FROM " . self::TABLE . " WHERE auction_id = :auction_id AND language_code = :language_code"
        );
        return $stmt->execute([':auction_id' => $auctionId, ':language_code' => $languageCode]);
    }
}