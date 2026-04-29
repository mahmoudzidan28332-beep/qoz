<?php
declare(strict_types=1);

/**
 * AddressesRepositoryInterface
 *
 * Defines the contract for the addresses persistence layer.
 * Concrete implementations must enforce multi-tenant isolation
 * and mass-assignment protection on every operation.
 */
interface AddressesRepositoryInterface
{
    /**
     * Return a paginated, filtered list of addresses for the active tenant.
     *
     * @param  int    $limit    Maximum rows to return.
     * @param  int    $offset   Row offset for pagination.
     * @param  array  $filters  Optional filters: owner_type, owner_id, language, etc.
     * @param  string $orderBy  Column expression (must be whitelisted by implementation).
     * @param  string $orderDir 'ASC' or 'DESC'.
     * @return array<int, array<string, mixed>>
     */
    public function list(
        int    $limit,
        int    $offset,
        array  $filters  = [],
        string $orderBy  = 'a.id',
        string $orderDir = 'DESC'
    ): array;

    /**
     * Return the total number of addresses matching $filters for the active tenant.
     *
     * @param  array $filters  Same filter keys accepted by list().
     * @return int
     */
    public function count(array $filters = []): int;

    /**
     * Fetch a single address by primary key, scoped to the active tenant.
     *
     * @param  int    $id       Address primary key.
     * @param  string $language ISO language code for translated name columns.
     * @return array<string, mixed>|null  Null when not found or access denied.
     */
    public function find(int $id, string $language = 'ar'): ?array;

    /**
     * Persist a new address row (or delegate to update() when $data['id'] exists).
     *
     * @param  array<string, mixed> $data  Address fields. Only ALLOWED_COLUMNS are written.
     * @return int  Primary key of the newly created row.
     */
    public function save(array $data): int;

    /**
     * Update an existing address row, scoped to the active tenant.
     *
     * @param  int                  $id    Address primary key.
     * @param  array<string, mixed> $data  Fields to update. Only ALLOWED_COLUMNS are written.
     * @return bool  True on success, false when nothing was changed.
     */
    public function update(int $id, array $data): bool;

    /**
     * Delete an address row, scoped to the active tenant.
     *
     * @param  int  $id  Address primary key.
     * @return bool True when a row was deleted.
     */
    public function delete(int $id): bool;

    /**
     * Return all addresses belonging to a specific owner within the active tenant.
     *
     * @param  int    $ownerId   Owner primary key.
     * @param  string $ownerType 'user' or 'entity'.
     * @return array<int, array<string, mixed>>
     */
    public function getByOwner(int $ownerId, string $ownerType = 'user'): array;
}
