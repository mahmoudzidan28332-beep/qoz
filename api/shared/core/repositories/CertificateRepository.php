<?php
declare(strict_types=1);

class CertificateRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function getCertificateRequestData(int $requestId): ?array
    {
        $sql = "
            SELECT cr.*,
                   c_imp.name AS importer_country,
                   e.store_name AS exporter_name,
                   ce.certificate_version,
                   ce.scope,
                   ci.certificate_number,
                   ci.issued_at,
                   ci.verification_code,
                   ci.qr_code_path,
                   ci.pdf_path,
                   mo.name     AS official_name,
                   mo.position AS official_position
            FROM certificates_requests cr
            LEFT JOIN countries c_imp ON c_imp.id = cr.importer_country_id
            LEFT JOIN entities e ON e.id = cr.entity_id
            LEFT JOIN certificate_editions ce ON ce.id = cr.certificate_edition_id
            LEFT JOIN certificates_issued ci ON ci.id = cr.issued_id
            LEFT JOIN certificates_versions cv ON cv.id = ci.version_id
            LEFT JOIN municipality_officials mo ON mo.id = cv.municipality_official_id
            WHERE cr.id = :id
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $requestId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
}
