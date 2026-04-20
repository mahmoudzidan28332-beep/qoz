<?php
declare(strict_types=1);

final class UserDevicesService
{
    private PdoUserDevicesRepository $repo;
    private UserDevicesValidator $validator;

    public function __construct(PdoUserDevicesRepository $repo)
    {
        $this->repo = $repo;
        $this->validator = new UserDevicesValidator();
    }

    /**
     * List devices with pagination, filters, sorting
     */
    public function list(
        ?int $limit,
        ?int $offset,
        array $filters,
        string $orderBy,
        string $orderDir
    ): array {
        return $this->repo->all($limit, $offset, $filters, $orderBy, $orderDir);
    }

    public function count(array $filters = []): int
    {
        return $this->repo->count($filters);
    }

    public function get(int $id): ?array
    {
        return $this->repo->find($id);
    }

    public function getByToken(string $token): ?array
    {
        return $this->repo->findByToken($token);
    }

    public function getByUser(int $userId): array
    {
        return $this->repo->findByUserId($userId);
    }

    public function getByUserAndAgent(int $userId, string $userAgent): ?array
    {
        return $this->repo->findByUserAndAgent($userId, $userAgent);
    }

    public function create(array $data): int
    {
        $this->validator->validate($data, false);

        // Deduplicate by FCM token if provided
        if (!empty($data['fcm_token'])) {
            $existing = $this->repo->findByToken($data['fcm_token']);
            if ($existing) {
                // If token exists, just update its metadata and return existing id
                $data['id'] = $existing['id'];
                return $this->update($data);
            }
        }

        // Deduplicate by user_id + user_agent (fallback for session-based tracking,
        // and also when a new FCM token needs to be attached to an existing device row)
        if (!empty($data['user_id']) && !empty($data['user_agent'])) {
            $existing = $this->repo->findByUserAndAgent((int)$data['user_id'], $data['user_agent']);
            if ($existing) {
                $data['id'] = $existing['id'];
                return $this->update($data);
            }
        }

        return $this->repo->save($data);
    }

    public function update(array $data): int
    {
        if (empty($data['id'])) {
            throw new InvalidArgumentException("ID is required for update.");
        }
        $this->validator->validate($data, true);
        return $this->repo->save($data);
    }

    public function delete(int $id): bool
    {
        return $this->repo->delete($id);
    }

    public function deleteByUser(int $userId): bool
    {
        return $this->repo->deleteByUserId($userId);
    }

    public function touch(int $id): bool
    {
        return $this->repo->touch($id);
    }
}