<?php
declare(strict_types=1);

/**
 * PDO repository for the certificates_issued table.
 */
final class PdoCertificatesIssuedRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM certificates_issued WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function updateFilePaths(int $id, string $qrCodePath, string $pdfPath): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE certificates_issued SET qr_code_path = :qr, pdf_path = :pdf WHERE id = :id"
        );
        $stmt->execute([
            ':qr'  => $qrCodePath,
            ':pdf' => $pdfPath,
            ':id'  => $id,
        ]);
    }
}
