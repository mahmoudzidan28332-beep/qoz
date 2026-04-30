<?php
declare(strict_types=1);

final class CategoriesService
{
    private PdoCategoriesRepository $repo;
    private CategoriesValidator $validator;

    public function __construct(
        PdoCategoriesRepository $repo,
        CategoriesValidator $validator
    ) {
        $this->repo = $repo;
        $this->validator = $validator;
    }

    /* ============================================================
     * LIST CATEGORIES WITH PAGINATION AND FILTERS
     * ============================================================ */
    public function list(
        ?int $tenantId,
        array $filters = [],
        string $lang = 'ar'
    ): array {
        // ✅ FIX: استخدام parent_id من الـ filters مباشرة
        // الـ Controller يُرسل -1 عند show_all=1، null عند الـ roots، أو رقم محدد
        $parentId = isset($filters['parent_id'])
            ? (int) $filters['parent_id']
            : null;

        $featuredOnly = isset($filters['is_featured']) && in_array($filters['is_featured'], [1, '1', true, 'true'], true);
        $isActive     = isset($filters['is_active']) ? filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN) : null;
        $search       = $filters['search'] ?? null;
        $page         = max(1, (int)($filters['page']  ?? 1));
        // ✅ FIX: limit=0 means "no limit" — admin panel shows all records at once
        $rawLimit     = (int)($filters['limit'] ?? 25);
        $limit        = $rawLimit === 0 ? 0 : min(10000, max(1, $rawLimit));
        $offset       = $limit > 0 ? ($page - 1) * $limit : 0;
        $skipTcFilter = (bool)($filters['skip_tc_filter'] ?? false);

        // ✅ FIX: تمرير نفس parent_id (-1 / null / رقم) إلى كلا الاستعلامين
        // هذا يضمن أن all() و countAll() يعملان على نفس النطاق تماماً
        $items = $this->repo->all(
            $tenantId,
            $parentId,          // ← -1 = show_all, null = roots only, >0 = specific parent
            $featuredOnly,
            $lang,
            $search,
            $isActive,
            $limit,
            $offset,
            $skipTcFilter
        );

        // ✅ FIX: تمرير $filters مع parent_id محدد بوضوح
        // حتى لو كان -1 يجب أن يصل بشكل صريح إلى countAll()
        $filtersForCount = array_merge($filters, [
            'parent_id' => $parentId   // ← يضمن وصول -1 حتى لو كان null في الـ filters الأصلية
        ]);

        $total = $this->repo->countAll($tenantId, $filtersForCount, $skipTcFilter);

        return [
            'items' => $items,
            'meta'  => [
                'total'       => $total,
                'page'        => $limit > 0 ? $page : 1,
                'per_page'    => $limit > 0 ? $limit : $total,
                'total_pages' => $limit > 0 && $total > 0 ? (int) ceil($total / $limit) : ($total > 0 ? 1 : 0),
                'from'        => $total > 0 ? ($limit > 0 ? $offset + 1 : 1) : 0,
                'to'          => $total > 0 ? ($limit > 0 ? min($offset + $limit, $total) : $total) : 0,
                'last_page'   => $limit > 0 && $total > 0 ? (int) ceil($total / $limit) : 1,
            ]
        ];
    }

    /* ============================================================
     * GET BY ID WITH TRANSLATIONS
     * ============================================================ */
    public function getById(
        ?int $tenantId,
        int $id,
        string $lang = 'ar',
        bool $allTranslations = false
    ): array {
        $row = $this->repo->findById($tenantId, $id);

        if (!$row) {
            throw new RuntimeException('Category not found');
        }

        $translations = $this->repo->getTranslations($id);

        if ($allTranslations) {
            $row['translations'] = $translations;
        } else {
            if (isset($translations[$lang])) {
                $row = array_merge($row, $translations[$lang]);
            }
            $row['translations'] = $translations;
        }

        $image = $this->repo->getMainImage($tenantId, $id);
        if ($image) {
            $row['image_id']        = $image['id'];
            $row['image_url']       = $image['url'];
            $row['image_thumb_url'] = $image['thumb_url'];
        }

        if ($row['parent_id']) {
            $parent = $this->repo->findById($tenantId, (int)$row['parent_id']);
            $row['parent_name'] = $parent['name'] ?? null;
        }

        return $row;
    }

    /* ============================================================
     * CREATE / UPDATE CATEGORY
     * ============================================================ */
    public function save(
        ?int $tenantId,
        array $data,
        ?int $userId = null
    ): array {
        $data = $this->normalizePayload($data);

        $errors = $this->validator->validate($data);
        if (!empty($errors)) {
            throw new InvalidArgumentException(
                json_encode($errors, JSON_UNESCAPED_UNICODE)
            );
        }

        $isUpdate  = !empty($data['id']);
        $excludeId = $isUpdate ? (int)$data['id'] : null;

        if (isset($data['slug'])) {
            if ($this->repo->slugExists($tenantId, $data['slug'], $excludeId)) {
                throw new InvalidArgumentException(json_encode([
                    'slug' => 'Slug already exists'
                ]));
            }
        }

        if (isset($data['parent_id']) && $data['parent_id']) {
            $parent = $this->repo->findById($tenantId, (int)$data['parent_id']);
            if (!$parent) {
                throw new InvalidArgumentException(json_encode([
                    'parent_id' => 'Parent category not found'
                ]));
            }

            if ($isUpdate && $data['id'] == $data['parent_id']) {
                throw new InvalidArgumentException(json_encode([
                    'parent_id' => 'Category cannot be its own parent'
                ]));
            }
        }

        $id = $this->repo->save($tenantId, $data, $userId);

        $row = $this->repo->findByIdWithTranslations($tenantId, $id);
        if (!$row) {
            throw new RuntimeException('Failed to load saved category');
        }

        return $row;
    }

    private function normalizePayload(array $data): array
    {
        $translations = $this->normalizeTranslations($data['translations'] ?? []);
        $defaultTranslation = null;

        foreach ($translations as $translation) {
            if (($translation['language_code'] ?? '') === 'en') {
                $defaultTranslation = $translation;
                break;
            }
        }

        if ($defaultTranslation === null) {
            $fallbackName = $this->normalizeString($data['name'] ?? null);
            $fallbackSlug = $this->normalizeString($data['slug'] ?? null);
            $fallbackDescription = $this->normalizeNullableString($data['description'] ?? null);

            if ($fallbackName !== null || $fallbackSlug !== null || $fallbackDescription !== null) {
                $defaultTranslation = [
                    'language_code' => 'en',
                    'name' => $fallbackName ?? '',
                    'slug' => $fallbackSlug ?? '',
                    'description' => $fallbackDescription,
                    'meta_title' => null,
                    'meta_description' => null,
                    'meta_keywords' => null,
                ];
                $translations[] = $defaultTranslation;
            }
        }

        if ($defaultTranslation !== null) {
            $data['name'] = $this->firstNonEmpty(
                $this->normalizeString($defaultTranslation['name'] ?? null),
                $this->normalizeString($data['name'] ?? null)
            ) ?? '';

            $data['slug'] = $this->firstNonEmpty(
                $this->normalizeString($defaultTranslation['slug'] ?? null),
                $this->normalizeString($data['slug'] ?? null)
            ) ?? '';

            $data['description'] = $this->firstNonEmpty(
                $this->normalizeNullableString($defaultTranslation['description'] ?? null),
                $this->normalizeNullableString($data['description'] ?? null)
            );
        }

        $data['translations'] = $translations;

        return $data;
    }

    private function normalizeTranslations(array $translations): array
    {
        $normalized = [];

        foreach ($translations as $key => $translation) {
            if (!is_array($translation)) {
                continue;
            }

            $languageCode = $translation['language_code'] ?? (is_string($key) ? $key : null);
            $languageCode = $this->normalizeString($languageCode);
            if ($languageCode === null) {
                continue;
            }

            $normalized[$languageCode] = [
                'language_code' => $languageCode,
                'name' => $this->normalizeNullableString($translation['name'] ?? null) ?? '',
                'slug' => $this->normalizeNullableString($translation['slug'] ?? null),
                'description' => $this->normalizeNullableString($translation['description'] ?? null),
                'meta_title' => $this->normalizeNullableString($translation['meta_title'] ?? null),
                'meta_description' => $this->normalizeNullableString($translation['meta_description'] ?? null),
                'meta_keywords' => $this->normalizeNullableString($translation['meta_keywords'] ?? null),
            ];
        }

        return array_values($normalized);
    }

    private function normalizeString(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);
        return $value !== '' ? $value : null;
    }

    private function normalizeNullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (!is_string($value)) {
            $value = (string) $value;
        }

        $value = trim($value);
        return $value !== '' ? $value : null;
    }

    private function firstNonEmpty(?string $first, ?string $second): ?string
    {
        return $first !== null && $first !== '' ? $first : $second;
    }

    /* ============================================================
     * DELETE CATEGORY
     * ============================================================ */
    public function deleteById(
        ?int $tenantId,
        int $id,
        ?int $userId = null
    ): void {
        $category = $this->repo->findByIdWithTranslations($tenantId, $id);
        if (!$category) {
            throw new RuntimeException('Category not found');
        }

        if ($this->repo->hasChildren($id)) {
            throw new RuntimeException('Cannot delete category with subcategories');
        }

        if (!$this->repo->delete($tenantId, $id, $userId)) {
            throw new RuntimeException('Failed to delete category');
        }
    }

    /* ============================================================
     * DELETE CATEGORY BY SLUG
     * ============================================================ */
    public function deleteBySlug(
        ?int $tenantId,
        string $slug,
        ?int $userId = null
    ): void {
        $categoryId = $this->repo->findIdBySlug($tenantId, $slug);
        if (!$categoryId) {
            throw new RuntimeException('Category not found');
        }

        $this->deleteById($tenantId, $categoryId, $userId);
    }

    /* ============================================================
     * DELETE SINGLE TRANSLATION
     * ============================================================ */
    public function deleteTranslation(
        ?int $tenantId,
        int $categoryId,
        string $languageCode,
        ?int $userId = null
    ): void {
        $category = $this->repo->findByIdWithTranslations($tenantId, $categoryId);
        if (!$category) {
            throw new RuntimeException('Category not found');
        }

        if (empty($category['translations'][$languageCode])) {
            throw new RuntimeException('Translation not found');
        }

        $deleted = $this->repo->deleteTranslation($categoryId, $languageCode);
        if (!$deleted) {
            throw new RuntimeException('Failed to delete translation');
        }
    }

    /* ============================================================
     * GET ACTIVE CATEGORIES
     * ============================================================ */
    public function getActiveCategories(
        ?int $tenantId,
        string $lang = 'ar'
    ): array {
        return $this->repo->getActiveCategories($tenantId, $lang);
    }

    /* ============================================================
     * GET FEATURED CATEGORIES
     * ============================================================ */
    public function getFeaturedCategories(
        ?int $tenantId,
        string $lang = 'ar'
    ): array {
        return $this->repo->getFeaturedCategories($tenantId, $lang);
    }

    /* ============================================================
     * BULK OPERATIONS
     * ============================================================ */
    public function bulkUpdateStatus(
        ?int $tenantId,
        array $ids,
        bool $isActive,
        ?int $userId = null
    ): int {
        return $this->repo->bulkUpdateStatus($tenantId, $ids, $isActive, $userId);
    }

    public function bulkDelete(
        ?int $tenantId,
        array $ids,
        ?int $userId = null
    ): int {
        if (empty($ids)) {
            return 0;
        }

        $deletedCount = 0;
        foreach ($ids as $id) {
            try {
                $this->deleteById($tenantId, (int)$id, $userId);
                $deletedCount++;
            } catch (\RuntimeException $e) {
                error_log("Failed to delete category {$id}: " . $e->getMessage());
            }
        }

        return $deletedCount;
    }

    /* ============================================================
     * VALIDATION HELPERS
     * ============================================================ */
    public function findIdBySlug(?int $tenantId, string $slug): ?int
    {
        return $this->repo->findIdBySlug($tenantId, $slug);
    }

    public function validateSlug(?int $tenantId, string $slug, ?int $excludeId = null): bool
    {
        return !$this->repo->slugExists($tenantId, $slug, $excludeId);
    }

    public function getCategoryTree(?int $tenantId, string $lang = 'ar'): array
    {
        // -1 = bypass parent filter → جلب كل الفئات
        $categories = $this->repo->all($tenantId, -1, false, $lang, null, null, 0, 0);

        $tree    = [];
        $indexed = [];

        foreach ($categories as $category) {
            $indexed[$category['id']]             = $category;
            $indexed[$category['id']]['children'] = [];
        }

        foreach ($indexed as $id => &$category) {
            if ($category['parent_id'] && isset($indexed[$category['parent_id']])) {
                $indexed[$category['parent_id']]['children'][] = &$category;
            } else {
                $tree[] = &$category;
            }
        }

        return $tree;
    }
}
