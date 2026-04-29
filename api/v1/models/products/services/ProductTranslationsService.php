<?php
declare(strict_types=1);

final class ProductTranslationsService
{
    private PdoProductTranslationsRepository $repo;

    public function __construct(PdoProductTranslationsRepository $repo)
    {
        $this->repo = $repo;
    }

    public function list(?string $languageCode, ?int $limit, ?int $offset, array $filters, string $orderBy, string $orderDir): array
    {
        return [
            'items' => $this->repo->list($languageCode, $limit, $offset, $filters, $orderBy, $orderDir),
            'total' => $this->repo->count($filters)
        ];
    }

    public function get(int $id, ?string $languageCode): ?array
    {
        return $this->repo->find($id, $languageCode);
    }

    public function create(array $data): int
    {
        return $this->repo->save($data);
    }

    public function update(array $data): int
    {
        return $this->repo->save($data);
    }

    public function delete(int $id): bool
    {
        return $this->repo->delete($id);
    }
}
