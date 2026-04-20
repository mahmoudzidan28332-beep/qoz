<?php
declare(strict_types=1);

final class ProductVariantAttributesService
{
    private PdoProductVariantAttributesRepository $repo;
    private ProductVariantAttributesValidator $validator;

    public const WHITELISTED_COLUMNS = [
        'variant_id', 'attribute_id', 'attribute_value_id', 'custom_value', 'id'
    ];

    public function __construct(PdoProductVariantAttributesRepository $repo, ProductVariantAttributesValidator $validator)
    {
        $this->repo = $repo;
        $this->validator = $validator;
    }

    public function list(int $tenantId, ?int $limit=null, ?int $offset=null, array $filters=[], string $orderBy='id', string $orderDir='DESC'): array
    {
        return $this->repo->all($tenantId,$limit,$offset,$filters,$orderBy,$orderDir);
    }

    public function count(int $tenantId, array $filters=[]): int
    {
        return $this->repo->count($tenantId,$filters);
    }

    public function get(int $tenantId,int $id): ?array
    {
        return $this->repo->find($tenantId,$id);
    }

    public function create(int $tenantId, array $data): int
    {
        // 🔒 SECURITY: Mass Assignment Protection - Define WHITELIST
        $whitelisted = array_intersect_key($data, array_flip(self::WHITELISTED_COLUMNS));

        $this->validator->validate($whitelisted);
        return $this->repo->save($tenantId, $whitelisted);
    }

    public function update(int $tenantId,array $data): int
    {
        if(empty($data['id'])) throw new InvalidArgumentException("ID is required.");

        // 🔒 SECURITY: Mass Assignment Protection - Define WHITELIST
        $whitelisted = array_intersect_key($data, array_flip(self::WHITELISTED_COLUMNS));

        $this->validator->validate($whitelisted,true);
        return $this->repo->save($tenantId, $whitelisted);
    }

    public function delete(int $tenantId,int $id): bool
    {
        return $this->repo->delete($tenantId,$id);
    }
}
