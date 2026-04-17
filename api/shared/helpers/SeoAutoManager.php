<?php
declare(strict_types=1);

require_once __DIR__ . '/../core/repositories/SeoRepository.php';

/**
 * SeoAutoManager - Auto-populate seo_meta + seo_meta_translations
 *
 * Usage (in any route after entity creation/update):
 *   require_once __DIR__ . '/../shared/helpers/SeoAutoManager.php';
 *   SeoAutoManager::sync($pdo, 'entity', $entityId, [
 *       'name'        => 'Store Name',
 *       'slug'        => 'store-slug',
 *       'description' => 'Description text',
 *       'tenant_id'   => 1
 *   ]);
 *
 *   SeoAutoManager::syncTranslation($pdo, 'entity', $entityId, 'ar', [
 *       'name'        => 'اسم المتجر',
 *       'description' => 'وصف المتجر'
 *   ]);
 */
class SeoAutoManager
{
    /**
     * Sync seo_meta row for an entity.
     * Uses INSERT ... ON DUPLICATE KEY UPDATE for upsert.
     *
     * @param PDO    $pdo
     * @param string $entityType  e.g. entity, product, category, page
     * @param int    $entityId
     * @param array  $data        Keys: name, slug, description, tenant_id
     */
    public static function sync(PDO $pdo, string $entityType, int $entityId, array $data): void
    {
        $slug        = $data['slug'] ?? '';
        $tenantId    = isset($data['tenant_id']) ? (int)$data['tenant_id'] : null;
        $canonical   = $slug ? '/' . $entityType . '/' . $slug : null;
        $robots      = 'index, follow';

        // Build simple JSON-LD schema
        $name        = $data['name'] ?? '';
        $description = $data['description'] ?? '';
        $schema      = json_encode([
            '@context' => 'https://schema.org',
            '@type'    => self::schemaType($entityType),
            'name'     => $name,
            'description' => mb_substr($description, 0, 300),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $repo = new SeoRepository($pdo);
        $repo->upsertSeoMeta($tenantId, $entityType, $entityId, $canonical, $robots, $schema);

        // If language_code is provided, auto-create translation too
        $langCode = $data['language_code'] ?? null;
        if ($langCode) {
            self::syncTranslation($pdo, $entityType, $entityId, $langCode, $data);
        }
    }

    /**
     * Sync SEO translations for ALL existing translations of an entity.
     * Queries the appropriate translations table and syncs each language.
     *
     * @param PDO    $pdo
     * @param string $entityType  e.g. entity, product, category
     * @param int    $entityId
     */
    public static function syncAllTranslations(PDO $pdo, string $entityType, int $entityId): void
    {
        $seoMetaId = self::getSeoMetaId($pdo, $entityType, $entityId);
        if (!$seoMetaId) {
            return;
        }

        $tableMap = [
            'entity'   => ['table' => 'entity_translations',  'fk' => 'entity_id',  'name' => 'store_name'],
            'product'  => ['table' => 'product_translations',  'fk' => 'product_id', 'name' => 'name'],
            'category' => ['table' => 'category_translations', 'fk' => 'category_id','name' => 'name'],
        ];

        $config = $tableMap[$entityType] ?? null;
        if (!$config) {
            return;
        }

        // Whitelist validation - table/column names come from internal array only
        $allowedTables = ['entity_translations', 'product_translations', 'category_translations'];
        if (!in_array($config['table'], $allowedTables, true)) {
            return;
        }

        $repo = new SeoRepository($pdo);
        $translations = $repo->getEntityTranslations($config['table'], $config['fk'], $config['name'], $entityId);

        foreach ($translations as $tr) {
            self::syncTranslation($pdo, $entityType, $entityId, $tr['language_code'], [
                'name'        => $tr['name'] ?? '',
                'description' => $tr['description'] ?? '',
            ]);
        }
    }

    /**
     * Sync a specific language translation for seo_meta.
     * Called when entity translations are saved.
     *
     * @param PDO    $pdo
     * @param string $entityType
     * @param int    $entityId
     * @param string $langCode
     * @param array  $data  Keys: name, description
     */
    public static function syncTranslation(
        PDO $pdo,
        string $entityType,
        int $entityId,
        string $langCode,
        array $data
    ): void {
        $seoMetaId = self::getSeoMetaId($pdo, $entityType, $entityId);
        if (!$seoMetaId) {
            return;
        }

        $name        = $data['name'] ?? '';
        $description = $data['description'] ?? '';

        $metaTitle       = $name ? mb_substr($name, 0, 255) : null;
        $metaDescription = $description ? mb_substr($description, 0, 160) : null;
        $metaKeywords    = self::generateKeywords($name, $description);

        self::upsertTranslation($pdo, $seoMetaId, $langCode, [
            'meta_title'       => $metaTitle,
            'meta_description' => $metaDescription,
            'meta_keywords'    => $metaKeywords,
            'og_title'         => $metaTitle,
            'og_description'   => $metaDescription,
        ]);
    }

    /**
     * Delete seo_meta and translations for a deleted entity.
     */
    public static function delete(PDO $pdo, string $entityType, int $entityId): void
    {
        $seoMetaId = self::getSeoMetaId($pdo, $entityType, $entityId);
        $repo = new SeoRepository($pdo);
        if ($seoMetaId) {
            $repo->deleteSeoMetaTranslations($seoMetaId);
        }
        $repo->deleteSeoMeta($entityType, $entityId);
    }

    // ─── Private helpers ───────────────────────────────────

    private static function getSeoMetaId(PDO $pdo, string $entityType, int $entityId): ?int
    {
        $repo = new SeoRepository($pdo);
        return $repo->findSeoMetaId($entityType, $entityId);
    }

    private static function upsertTranslation(PDO $pdo, int $seoMetaId, string $langCode, array $fields): void
    {
        $repo = new SeoRepository($pdo);
        $repo->upsertSeoMetaTranslation(
            $seoMetaId,
            $langCode,
            $fields['meta_title'],
            $fields['meta_description'],
            $fields['meta_keywords'],
            $fields['og_title'],
            $fields['og_description']
        );
    }

    private static function generateKeywords(string $name, string $description): string
    {
        $text  = $name . ' ' . $description;
        $text  = strip_tags($text);
        $words = preg_split('/[\s,;.!?\-_\/\\\\]+/u', $text, -1, PREG_SPLIT_NO_EMPTY);
        $words = array_filter($words, function ($w) {
            return mb_strlen($w) > 2;
        });
        $words = array_unique(array_map('mb_strtolower', $words));
        return mb_substr(implode(', ', array_slice($words, 0, 15)), 0, 255);
    }

    private static function schemaType(string $entityType): string
    {
        $map = [
            'entity'   => 'LocalBusiness',
            'product'  => 'Product',
            'category' => 'ItemList',
            'page'     => 'WebPage',
        ];
        return $map[$entityType] ?? 'Thing';
    }
}