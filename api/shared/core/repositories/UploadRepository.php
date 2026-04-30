<?php

declare(strict_types=1);

// ===========================================
// UploadRepository.php  —  PRODUCTION VERSION
// ===========================================

class UploadRepository
{
    public function __construct(private readonly PDO $pdo) {}

    // ------------------------------------------
    // إدراج ملف جديد
    // ------------------------------------------

    public function insertFile(
        string  $fileName,
        string  $filePath,
        string  $folder,
        string  $type,
        string  $mimeType,
        int     $size,
        ?int    $width         = null,
        ?int    $height        = null,
        ?int    $userId        = null,
        ?string $thumbnailPath = null,
    ): ?int {
        // التحقق من القيم الأساسية
        if (empty($fileName) || empty($filePath)) {
            throw new \InvalidArgumentException('fileName and filePath are required');
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO files
                (file_name, file_path, folder, type, mime_type, size,
                 width, height, user_id, thumbnail_path, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");

        $stmt->execute([
            $fileName,
            $filePath,
            $folder,
            $type,
            $mimeType,
            $size,
            $width,
            $height,
            $userId,
            $thumbnailPath,
        ]);

        $id = $this->pdo->lastInsertId();

        return $id ? (int) $id : null;
    }

    // ------------------------------------------
    // حذف ملف بالـ ID
    // ------------------------------------------

    public function deleteFile(int $fileId): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM files WHERE id = ?");
        $stmt->bindValue(1, $fileId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->rowCount() > 0;
    }

    // ------------------------------------------
    // البحث عن ملف بالـ ID
    // ------------------------------------------

    public function findFileById(int $fileId): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT id, file_name, file_path, folder, type, mime_type, size, width, height, user_id, thumbnail_path, created_at, updated_at FROM files WHERE id = ? LIMIT 1"
        );
        $stmt->bindValue(1, $fileId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    // ------------------------------------------
    // جلب ملفات مستخدم معين
    // ------------------------------------------

    public function findFilesByUserId(int $userId, int $limit = 50, int $offset = 0): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT id, file_name, file_path, folder, type, mime_type, size, width, height, user_id, thumbnail_path, created_at, updated_at FROM files WHERE user_id = ? ORDER BY created_at DESC LIMIT ? OFFSET ?"
        );
        $stmt->bindValue(1, $userId, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit,  PDO::PARAM_INT);
        $stmt->bindValue(3, $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ------------------------------------------
    // تحديث thumbnail لملف
    // ------------------------------------------

    public function updateThumbnail(int $fileId, string $thumbnailPath): bool
    {
        $stmt = $this->pdo->prepare(
            "UPDATE files SET thumbnail_path = ?, updated_at = NOW() WHERE id = ?"
        );
        $stmt->execute([$thumbnailPath, $fileId]);

        return $stmt->rowCount() > 0;
    }
}