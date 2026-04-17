<?php
declare(strict_types=1);

final class CategoriesController
{
    private CategoriesService $service;

    public function __construct(CategoriesService $service)
    {
        $this->service = $service;
    }

    /* ============================================================
     * LIST CATEGORIES WITH FILTERS
     * ============================================================ */
    public function list(?int $tenantId): array
    {
        /*
         * show_all=1  → عرض كل الفئات بدون فلتر Root
         * show_all=0  → السلوك الافتراضي (roots فقط إذا لم يُحدَّد parent_id)
         *
         * القيمة -1 هي signal داخلي للـ Repository لتخطي فلتر parent_id تماماً.
         */
        $showAll  = isset($_GET['show_all']) && $_GET['show_all'] === '1';
        $rawPid   = $_GET['parent_id'] ?? '';

        if ($showAll) {
            $parentId = -1;                          // bypass — لا فلترة على الـ parent
        } elseif ($rawPid !== '' && is_numeric($rawPid)) {
            $parentId = (int) $rawPid;               // فلترة بـ parent محدد
        } else {
            $parentId = null;                        // السلوك الافتراضي (roots فقط)
        }

        $page  = max(1, (int) ($_GET['page']  ?? 1));
        $limit = min(200, max(1, (int) ($_GET['limit'] ?? 50)));

        $filters = [
            'parent_id'      => $parentId,
            'is_featured'    => $this->sanitizeFlag($_GET['is_featured'] ?? null),
            'is_active'      => $this->sanitizeFlag($_GET['is_active']   ?? null),
            'search'         => $this->sanitizeString($_GET['search']    ?? null),
            'page'           => $page,
            'limit'          => $limit,
            'skip_tc_filter' => !empty($_GET['skip_tc_filter']),
        ];

        $lang = $this->sanitizeLang($_GET['lang'] ?? 'ar');

        return $this->service->list($tenantId, $filters, $lang);
    }

    /* ============================================================
     * GET CATEGORY TREE
     * ============================================================ */
    public function tree(?int $tenantId): array
    {
        $lang  = $this->sanitizeLang($_GET['lang'] ?? 'ar');
        $items = $this->service->getCategoryTree($tenantId, $lang);

        return [
            'items' => $items,
            'meta'  => ['total' => count($items)],
        ];
    }

    /* ============================================================
     * GET ACTIVE CATEGORIES
     * ============================================================ */
    public function getActive(?int $tenantId): array
    {
        $lang  = $this->sanitizeLang($_GET['lang'] ?? 'ar');
        $items = $this->service->getActiveCategories($tenantId, $lang);

        return [
            'items' => $items,
            'meta'  => ['total' => count($items)],
        ];
    }

    /* ============================================================
     * GET FEATURED CATEGORIES
     * ============================================================ */
    public function getFeatured(?int $tenantId): array
    {
        $lang  = $this->sanitizeLang($_GET['lang'] ?? 'ar');
        $items = $this->service->getFeaturedCategories($tenantId, $lang);

        return [
            'items' => $items,
            'meta'  => ['total' => count($items)],
        ];
    }

    /* ============================================================
     * GET BY ID (EDIT FORM)
     * ============================================================ */
    public function getById(?int $tenantId, int $id): array
    {
        $lang           = $this->sanitizeLang($_GET['lang'] ?? 'ar');
        $allTranslations = isset($_GET['all_translations']) &&
                           in_array($_GET['all_translations'], ['1', 1, true], true);

        return $this->service->getById($tenantId, $id, $lang, $allTranslations);
    }

    /* ============================================================
     * CREATE CATEGORY
     * ============================================================ */
    public function create(?int $tenantId, array $data): array
    {
        $userId = $this->resolveUserId();
        unset($data['id']); // حماية ضد update غير مقصود

        return $this->service->save($tenantId, $data, $userId);
    }

    /* ============================================================
     * UPDATE CATEGORY
     * ============================================================ */
    public function update(?int $tenantId, array $data): array
    {
        if (empty($data['id'])) {
            throw new InvalidArgumentException('ID is required for update');
        }

        $userId = $this->resolveUserId();
        return $this->service->save($tenantId, $data, $userId);
    }

    /* ============================================================
     * DELETE CATEGORY
     * ============================================================ */
    public function delete(?int $tenantId, array $data): void
    {
        $userId = $this->resolveUserId($data);

        if (!empty($data['id'])) {
            $this->service->deleteById($tenantId, (int) $data['id'], $userId);
            return;
        }

        if (!empty($data['slug'])) {
            $this->service->deleteBySlug($tenantId, (string) $data['slug'], $userId);
            return;
        }

        throw new InvalidArgumentException('ID or slug is required to delete category');
    }

    /* ============================================================
     * DELETE SINGLE TRANSLATION
     * ============================================================ */
    public function deleteTranslation(
        ?int $tenantId,
        int $categoryId,
        string $languageCode
    ): array {
        $userId = $this->resolveUserId();

        if ($languageCode === '') {
            throw new InvalidArgumentException('Language code is required');
        }

        $this->service->deleteTranslation($tenantId, $categoryId, $languageCode, $userId);

        return [
            'status'        => 'success',
            'message'       => 'Translation deleted successfully',
            'category_id'   => $categoryId,
            'language_code' => $languageCode,
        ];
    }

    /* ============================================================
     * BULK OPERATIONS
     * ============================================================ */
    public function bulkUpdate(?int $tenantId, array $data): array
    {
        $userId = $this->resolveUserId();
        $action = $data['action'] ?? '';
        $ids    = $data['ids']    ?? [];

        if (empty($ids) || !is_array($ids)) {
            throw new InvalidArgumentException('IDs array is required');
        }

        // تنظيف المعرفات
        $ids = array_map('intval', array_filter($ids, 'is_numeric'));
        if (empty($ids)) {
            throw new InvalidArgumentException('No valid IDs provided');
        }

        switch ($action) {
            case 'activate':
                $count   = $this->service->bulkUpdateStatus($tenantId, $ids, true, $userId);
                $message = "Activated {$count} categories";
                break;

            case 'deactivate':
                $count   = $this->service->bulkUpdateStatus($tenantId, $ids, false, $userId);
                $message = "Deactivated {$count} categories";
                break;

            case 'delete':
                $count   = $this->service->bulkDelete($tenantId, $ids, $userId);
                $message = "Deleted {$count} categories";
                break;

            default:
                throw new InvalidArgumentException("Invalid bulk action: {$action}");
        }

        return [
            'status'   => 'success',
            'message'  => $message,
            'affected' => $count,
        ];
    }

    /* ============================================================
     * VALIDATE SLUG
     * ============================================================ */
    public function validateSlug(?int $tenantId, array $data): array
    {
        $slug      = trim($data['slug'] ?? '');
        $excludeId = isset($data['exclude_id']) ? (int) $data['exclude_id'] : null;

        if ($slug === '') {
            throw new InvalidArgumentException('Slug is required');
        }

        $isValid = $this->service->validateSlug($tenantId, $slug, $excludeId);

        return [
            'valid'   => $isValid,
            'slug'    => $slug,
            'message' => $isValid ? 'Slug is available' : 'Slug already exists',
        ];
    }

    /* ============================================================
     * PRIVATE HELPERS
     * ============================================================ */

    /**
     * يحل userId من SESSION أولاً ثم من البيانات المُمرَّرة كـ fallback.
     */
    private ?int $actingUserId = null;

    public function setActingUserId(?int $userId): void
    {
        $this->actingUserId = $userId;
    }

    private function resolveUserId(array $data = []): ?int
    {
        if ($this->actingUserId !== null) {
            return $this->actingUserId;
        }
        if (!empty($data['user_id']) && is_numeric($data['user_id'])) {
            return (int) $data['user_id'];
        }
        return null;
    }

    /**
     * يضمن أن قيمة الـ flag هي '0', '1', أو null فقط.
     */
    private function sanitizeFlag(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        return in_array((string) $value, ['0', '1'], true) ? (string) $value : null;
    }

    /**
     * يُنظِّف النصوص ويُرجع null إذا كانت فارغة.
     */
    private function sanitizeString(?string $value): ?string
    {
        if ($value === null) return null;
        $trimmed = trim($value);
        return $trimmed !== '' ? $trimmed : null;
    }

    /**
     * يتحقق من صحة كود اللغة ويُرجع 'ar' كـ fallback آمن.
     */
    private function sanitizeLang(string $lang): string
    {
        $lang = preg_replace('/[^a-z]/', '', strtolower(trim($lang)));
        return (strlen($lang) >= 2 && strlen($lang) <= 5) ? $lang : 'ar';
    }
}