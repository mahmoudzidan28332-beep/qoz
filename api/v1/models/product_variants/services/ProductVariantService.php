<?php
declare(strict_types=1);

final class ProductVariantService
{
    private PdoProductVariantsRepository $repo;
    private ProductVariantValidator $validator;

    public const WHITELISTED_COLUMNS = [
        'product_id', 'sku', 'barcode', 'stock_quantity',
        'low_stock_threshold', 'is_active', 'is_default', 'id'
    ];

    public function __construct(PdoProductVariantsRepository $repo, ProductVariantValidator $validator)
    {
        $this->repo = $repo;
        $this->validator = $validator;
    }

    public function listWithTranslations(int $tenantId, ?string $languageCode=null, ?int $limit=null, ?int $offset=null, array $filters=[], string $orderBy='id', string $orderDir='DESC'): array
    {
        return $this->repo->allWithTranslations($tenantId, $languageCode, $limit, $offset, $filters, $orderBy, $orderDir);
    }

    public function createOrUpdate(array $data, int $tenantId): int
    {
        // 🔒 SECURITY: Mass Assignment Protection - Define WHITELIST
        $whitelisted = array_intersect_key($data, array_flip(self::WHITELISTED_COLUMNS));

        $this->validator->validate($whitelisted, !empty($whitelisted['id']));
        return $this->repo->save($tenantId, $whitelisted);
    }

    public function delete(int $tenantId, int $id): bool
    {
        return $this->repo->delete($tenantId, $id);
    }

    public function saveTranslation(int $variantId, string $languageCode, string $name): void
    {
        $this->repo->saveTranslation($variantId, $languageCode, $name);
    }

    public function getTranslations(int $variantId): array
    {
        return $this->repo->getTranslations($variantId);
    }
}
