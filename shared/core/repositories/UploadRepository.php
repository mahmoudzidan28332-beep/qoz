<?php
declare(strict_types=1);

class UploadRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function insertFile(
        string $fileName,
        string $filePath,
        string $folder,
        string $type,
        string $mimeType,
        int $size,
        ?int $width = null,
        ?int $height = null,
        ?int $userId = null,
        ?string $thumbnailPath = null
    ): ?int {
        $stmt = $this->pdo->prepare("
            INSERT INTO files (file_name, file_path, folder, type, mime_type, size, width, height, user_id, thumbnail_path, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");
        $stmt->execute([$fileName, $filePath, $folder, $type, $mimeType, $size, $width, $height, $userId, $thumbnailPath]);
        $id = $this->pdo->lastInsertId();
        return $id ? (int)$id : null;
    }

    public function deleteFile(int $fileId): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM files WHERE id = ?");
        $stmt->execute([$fileId]);
        return $stmt->rowCount() > 0;
    }

    public function findFileById(int $fileId): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM files WHERE id = ?");
        $stmt->execute([$fileId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
}
