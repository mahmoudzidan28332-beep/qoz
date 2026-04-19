<?php
declare(strict_types=1);

class SeoRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function upsertSeoMeta(
        ?int $tenantId,
        string $entityType,
        int $entityId,
        ?string $canonicalUrl,
        string $robots,
        string $schemaMarkup
    ): void {
        $sql = "INSERT INTO seo_meta
                    (tenant_id, entity_type, entity_id, canonical_url, robots, schema_markup)
                VALUES
                    (:tenant_id, :entity_type, :entity_id, :canonical_url, :robots, :schema_markup)
                ON DUPLICATE KEY UPDATE
                    canonical_url  = VALUES(canonical_url),
                    robots         = VALUES(robots),
                    schema_markup  = VALUES(schema_markup),
                    updated_at     = NOW()";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':tenant_id'      => $tenantId,
            ':entity_type'    => $entityType,
            ':entity_id'      => $entityId,
            ':canonical_url'  => $canonicalUrl,
            ':robots'         => $robots,
            ':schema_markup'  => $schemaMarkup,
        ]);
    }

    public function findSeoMetaId(string $entityType, int $entityId): ?int
    {
        $stmt = $this->pdo->prepare(
            "SELECT id FROM seo_meta WHERE entity_type = ? AND entity_id = ? LIMIT 1"
        );
        $stmt->execute([$entityType, $entityId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? (int)$row['id'] : null;
    }

    /**
     * Fetch translations for a given entity from the appropriate translations table.
     * $table, $fk, $nameColumn must come from a trusted internal whitelist.
     */
    public function getEntityTranslations(string $table, string $fk, string $nameColumn, int $entityId): array
    {
        $sql = "SELECT language_code, " . $nameColumn . " AS name, description";
        $sql .= " FROM " . $table . " WHERE " . $fk . " = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$entityId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function upsertSeoMetaTranslation(
        int $seoMetaId,
        string $langCode,
        ?string $metaTitle,
        ?string $metaDescription,
        ?string $metaKeywords,
        ?string $ogTitle,
        ?string $ogDescription
    ): void {
        $sql = "INSERT INTO seo_meta_translations
                    (seo_meta_id, language_code, meta_title, meta_description, meta_keywords, og_title, og_description)
                VALUES
                    (:seo_meta_id, :language_code, :meta_title, :meta_description, :meta_keywords, :og_title, :og_description)
                ON DUPLICATE KEY UPDATE
                    meta_title       = VALUES(meta_title),
                    meta_description = VALUES(meta_description),
                    meta_keywords    = VALUES(meta_keywords),
                    og_title         = VALUES(og_title),
                    og_description   = VALUES(og_description),
                    updated_at       = NOW()";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':seo_meta_id'      => $seoMetaId,
            ':language_code'    => $langCode,
            ':meta_title'       => $metaTitle,
            ':meta_description' => $metaDescription,
            ':meta_keywords'    => $metaKeywords,
            ':og_title'         => $ogTitle,
            ':og_description'   => $ogDescription,
        ]);
    }

    public function deleteSeoMetaTranslations(int $seoMetaId): void
    {
        $this->pdo->prepare("DELETE FROM seo_meta_translations WHERE seo_meta_id = ?")
            ->execute([$seoMetaId]);
    }

    public function deleteSeoMeta(string $entityType, int $entityId): void
    {
        $this->pdo->prepare("DELETE FROM seo_meta WHERE entity_type = ? AND entity_id = ?")
            ->execute([$entityType, $entityId]);
    }
}
