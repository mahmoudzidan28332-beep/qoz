<?php
declare(strict_types=1);

final class BrandsService
{
    private PdoBrandsRepository $repo;
    private BrandsValidator $validator;

    public function __construct(
        PdoBrandsRepository $repo,
        BrandsValidator $validator
    ) {
        $this->repo      = $repo;
        $this->validator = $validator;
    }

    public function list(?int $tenantId, array $filters, string $orderBy, string $orderDir, ?int $limit, ?int $offset, string $lang): array
    {
        $items = $this->repo->all($tenantId, $limit, $offset, $filters, $orderBy, $orderDir, $lang);
        $total = $this->repo->count($tenantId, $filters);
        return ['items' => $items, 'total' => $total];
    }

    public function get(string $slug, string $lang = 'en', bool $allTranslations = false): array
    {
        $row = $this->repo->find($slug, $lang, $allTranslations);
        if (!$row) {
            throw new RuntimeException('Brand not found');
        }
        return $row;
    }

    public function getById(int $id, string $lang = 'en', bool $allTranslations = false): array
    {
        $row = $this->repo->findById($id, $lang, $allTranslations);
        if (!$row) {
            throw new RuntimeException('Brand not found');
        }
        return $row;
    }

    public function save(array $data, ?int $userId = null): array
    {
        $isUpdate = !empty($data['id']);
        $errors = $this->validator->validate($data, $isUpdate);
        if (!empty($errors)) {
            throw new InvalidArgumentException(json_encode($errors, JSON_UNESCAPED_UNICODE));
        }

        if (!empty($data['translations']) && is_array($data['translations'])) {
            $data['translations'] = BrandsValidator::normalizeTranslations($data['translations']);
        }

        $id = $this->repo->save($data, $userId);

        $row = $this->repo->findById($id, 'en', true);
        if (!$row) {
            throw new RuntimeException('Failed to load saved brand');
        }

        return $row;
    }

    public function delete(string $slug, ?int $userId = null): void
    {
        if (!$this->repo->delete($slug, $userId)) {
            throw new RuntimeException('Failed to delete brand');
        }
    }

    public function deleteById(int $id, ?int $userId = null): void
    {
        if (!$this->repo->deleteById($id, $userId)) {
            throw new RuntimeException('Failed to delete brand');
        }
    }
}