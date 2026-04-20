<?php
declare(strict_types=1);

final class PermissionsService
{
    private PdoPermissionsRepository $repository;
    private PermissionsValidator $validator;

    public const WHITELISTED_COLUMNS = [
        'key_name', 'display_name', 'description', 'id'
    ];

    public function __construct(PdoPermissionsRepository $repository, PermissionsValidator $validator)
    {
        $this->repository = $repository;
        $this->validator = $validator;
    }

    public function list(int $tenantId, ?int $limit = null, ?int $offset = null, array $filters = []): array
    {
        return $this->repository->all($tenantId, $limit, $offset, $filters);
    }

    public function count(int $tenantId, array $filters = []): int
    {
        return $this->repository->count($tenantId, $filters);
    }

    public function get(int $tenantId, int $id): array
    {
        $data = $this->repository->find($tenantId, $id);
        if (!$data) {
            throw new RuntimeException('Permission not found');
        }
        return $data;
    }

    public function save(int $tenantId, array $data, ?int $userId = null): array
    {
        $isUpdate = !empty($data['id']);

        if (!$this->validator->validate($data, $isUpdate ? 'update' : 'create')) {
            throw new InvalidArgumentException(implode(', ', $this->validator->getErrors()));
        }

        // 🔒 SECURITY: Mass Assignment Protection - Define WHITELIST
        $whitelisted = array_intersect_key($data, array_flip(self::WHITELISTED_COLUMNS));

        $id = $this->repository->save($tenantId, $whitelisted, $userId);
        return $this->get($tenantId, $id);
    }

    public function delete(int $tenantId, int $id, ?int $userId = null): void
    {
        if (!$this->repository->delete($tenantId, $id, $userId)) {
            throw new RuntimeException('Failed to delete permission');
        }
    }
}