<?php
declare(strict_types=1);

final class PdoEntityTranslationsRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function getByEntity(int $entityId, int $tenantId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT et.* FROM entity_translations et
            INNER JOIN entities e ON e.id = et.entity_id AND e.tenant_id = :tenant_id
            WHERE et.entity_id = :entity_id
            LIMIT 100
        ");
        $stmt->bindValue(':entity_id', $entityId, PDO::PARAM_INT);
        $stmt->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function save(array $data, int $tenantId): int
    {
        // Check if exists
        if (isset($data['id'])) {
            return $this->update($data, $tenantId);
        }

        // Check unique constraint (entity_id + language_code)
        $stmt = $this->pdo->prepare("
            SELECT id FROM entity_translations
            WHERE entity_id = :entity_id AND language_code = :language_code
            LIMIT 1
        ");
        $stmt->execute([
            ':entity_id' => $data['entity_id'],
            ':language_code' => $data['language_code']
        ]);
        $existingId = $stmt->fetchColumn();

        if ($existingId) {
            $data['id'] = (int)$existingId;
            return $this->update($data, $tenantId);
        }

        return $this->create($data, $tenantId);
    }

    private function create(array $data, int $tenantId): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO entity_translations (
                entity_id, language_code, store_name, description, 
                meta_title, meta_description
            ) 
            SELECT :entity_id, :language_code, :store_name, :description, :meta_title, :meta_description
            FROM (SELECT 1) AS dummy
            WHERE EXISTS (SELECT 1 FROM entities WHERE id = :entity_id AND tenant_id = :tenant_id)
        ");

        $stmt->execute([
            ':entity_id' => $data['entity_id'],
            ':language_code' => $data['language_code'],
            ':store_name' => $data['store_name'] ?? '',
            ':description' => $data['description'] ?? null,
            ':meta_title' => $data['meta_title'] ?? null,
            ':meta_description' => $data['meta_description'] ?? null,
            ':tenant_id' => $tenantId
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    private function update(array $data, int $tenantId): int
    {
        $stmt = $this->pdo->prepare("
            UPDATE entity_translations et
            INNER JOIN entities e ON e.id = et.entity_id
            SET et.store_name = :store_name,
                et.description = :description,
                et.meta_title = :meta_title,
                et.meta_description = :meta_description
            WHERE et.id = :id AND e.tenant_id = :tenant_id
        ");

        $stmt->execute([
            ':id' => $data['id'],
            ':store_name' => $data['store_name'] ?? '',
            ':description' => $data['description'] ?? null,
            ':meta_title' => $data['meta_title'] ?? null,
            ':meta_description' => $data['meta_description'] ?? null,
            ':tenant_id' => $tenantId
        ]);

        return (int)$data['id'];
    }

    public function delete(int $id, int $tenantId): bool
    {
        $stmt = $this->pdo->prepare("
            DELETE et FROM entity_translations et
            INNER JOIN entities e ON e.id = et.entity_id
            WHERE et.id = :id AND e.tenant_id = :tenant_id
        ");
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
        return $stmt->execute();
    }
}