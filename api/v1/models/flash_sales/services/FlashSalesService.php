<?php
declare(strict_types=1);

class FlashSalesService 
{
    private PdoFlashSalesRepository $repo;

    public const WHITELISTED_SALE_COLS = [
        'entity_id', 'title', 'start_date', 'end_date', 'status', 'is_active'
    ];

    public const WHITELISTED_PRODUCT_COLS = [
        'sale_id', 'product_id', 'variant_id', 'discount_type', 'discount_value',
        'quantity_limit', 'sort_order'
    ];

    public function __construct(PdoFlashSalesRepository $repo) 
    {
        $this->repo = $repo;
    }

    public function list(?int $tenantId, array $filters = []): array 
    { 
        return $this->repo->list($tenantId, $filters); 
    }

    public function find(int $id, ?int $entityId = null, ?int $tenantId = null): ?array 
    { 
        return $this->repo->find($id, $entityId, $tenantId); 
    }

    public function create(array $data, ?int $tenantId = null): int
    {
        // 🔒 SECURITY: Mass Assignment Protection - Define WHITELIST
        $whitelisted = array_intersect_key($data, array_flip(self::WHITELISTED_SALE_COLS));

        if ($this->validator) {
            $this->validator->validate($whitelisted, false);
        }

        return $this->repo->save($whitelisted, $tenantId);
    }

    public function update(int $id, array $data): bool 
    { 
        // 🔒 SECURITY: Mass Assignment Protection
        $data = array_intersect_key($data, array_flip(self::WHITELISTED_SALE_COLS));
        return $this->repo->update($id, $data); 
    }

    public function delete(int $id, ?int $entityId = null): bool 
    { 
        return $this->repo->delete($id, $entityId); 
    }

    public function stats(): array 
    { 
        return $this->repo->stats(); 
    }

    public function getTranslations(int $id, ?string $lang = null): array 
    { 
        return $this->repo->getTranslations($id, $lang); 
    }

    public function saveTranslation(array $data): bool 
    { 
        return $this->repo->saveTranslation($data); 
    }

    public function deleteTranslation(int $id): bool 
    { 
        return $this->repo->deleteTranslation($id); 
    }

    public function deleteTranslationsByLang(int $fid, string $lang): bool 
    { 
        return $this->repo->deleteTranslationsByLang($fid, $lang); 
    }

    public function getProducts(int $id): array 
    { 
        return $this->repo->getProducts($id); 
    }

    public function addProduct(array $data, ?int $tenantId = null): int
    {
        // 🔒 SECURITY: Mass Assignment Protection - Define WHITELIST
        $whitelisted = array_intersect_key($data, array_flip(self::WHITELISTED_PRODUCT_COLS));

        if ($this->validator) {
            $this->validator->validateProduct($whitelisted);
        }

        return $this->repo->addProduct($whitelisted, $tenantId);
    }

    public function updateProduct(int $id, array $data): bool 
    { 
        // 🔒 SECURITY: Mass Assignment Protection
        $data = array_intersect_key($data, array_flip(self::WHITELISTED_PRODUCT_COLS));
        return $this->repo->updateProduct($id, $data); 
    }

    public function deleteProduct(int $id): bool 
    { 
        return $this->repo->deleteProduct($id); 
    }
}
