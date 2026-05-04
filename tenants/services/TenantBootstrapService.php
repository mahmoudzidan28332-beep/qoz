<?php
declare(strict_types=1);

/**
 * TenantBootstrapService
 *
 * Atomically creates a new tenant together with its owner user and the full
 * default RBAC structure (roles, permission catalog, role→permission mapping,
 * and the owner's tenant_users record) inside a single PDO transaction.
 *
 * Security rules enforced here:
 *  • tenant_id is NEVER sourced from request input – it is returned by the DB
 *    after the tenant INSERT (lastInsertId).
 *  • owner_user_id is set from the newly-created (or looked-up) user – never
 *    from user-supplied data.
 *  • Roles and permissions are created by the system; callers cannot choose IDs.
 *
 * Permission catalog strategy:
 *  The `permissions` table is treated as a global catalog: all seeded permissions
 *  use tenant_id = 1 (the reserved system tenant).  When a new tenant is
 *  bootstrapped, the service fetches those global permission IDs and maps them
 *  to the new tenant's roles inside `role_permissions` (which IS tenant-scoped).
 *  This avoids duplicating permission definitions per tenant while still keeping
 *  the role→permission assignment fully tenant-isolated.
 */
final class TenantBootstrapService
{
    /** The reserved system tenant that owns the global permission catalog. */
    private const SYSTEM_TENANT_ID = 1;

    /**
     * Full permission catalog seeded for every new tenant.
     *
     * Format: [ key_name => [display_name, module, description] ]
     */
    private const PERMISSION_CATALOG = [
        // ── Products ──────────────────────────────────────────────────────────
        'products.view'         => ['View Products',          'products',    'List and view products'],
        'products.create'       => ['Create Products',        'products',    'Create new products'],
        'products.edit'         => ['Edit Products',          'products',    'Update existing products'],
        'products.delete'       => ['Delete Products',        'products',    'Delete products'],
        // ── Orders ────────────────────────────────────────────────────────────
        'orders.view'           => ['View Orders',            'orders',      'List and view orders'],
        'orders.create'         => ['Create Orders',          'orders',      'Place new orders'],
        'orders.edit'           => ['Edit Orders',            'orders',      'Update order status/details'],
        'orders.delete'         => ['Delete Orders',          'orders',      'Delete orders'],
        // ── Users / Members ───────────────────────────────────────────────────
        'users.view'            => ['View Users',             'users',       'List and view tenant users'],
        'users.create'          => ['Create Users',           'users',       'Add users to the tenant'],
        'users.edit'            => ['Edit Users',             'users',       'Update user details'],
        'users.delete'          => ['Delete Users',           'users',       'Remove users from the tenant'],
        // ── Roles & Permissions ───────────────────────────────────────────────
        'roles.view'            => ['View Roles',             'roles',       'List and view roles'],
        'roles.create'          => ['Create Roles',           'roles',       'Create new roles'],
        'roles.edit'            => ['Edit Roles',             'roles',       'Update role definitions'],
        'roles.delete'          => ['Delete Roles',           'roles',       'Delete roles'],
        'permissions.view'      => ['View Permissions',       'permissions', 'List and view permissions'],
        'permissions.assign'    => ['Assign Permissions',     'permissions', 'Assign permissions to roles'],
        // ── Settings ──────────────────────────────────────────────────────────
        'settings.view'         => ['View Settings',          'settings',    'View tenant settings'],
        'settings.edit'         => ['Edit Settings',          'settings',    'Modify tenant settings'],
        // ── Reports ───────────────────────────────────────────────────────────
        'reports.view'          => ['View Reports',           'reports',     'Access analytics and reports'],
        // ── Entities / Stores ─────────────────────────────────────────────────
        'entities.view'         => ['View Entities',          'entities',    'List and view entities/stores'],
        'entities.create'       => ['Create Entities',        'entities',    'Create new entities/stores'],
        'entities.edit'         => ['Edit Entities',          'entities',    'Update entities/stores'],
        'entities.delete'       => ['Delete Entities',        'entities',    'Delete entities/stores'],
        // ── Categories ────────────────────────────────────────────────────────
        'categories.view'       => ['View Categories',        'categories',  'List and view categories'],
        'categories.create'     => ['Create Categories',      'categories',  'Create new categories'],
        'categories.edit'       => ['Edit Categories',        'categories',  'Update categories'],
        'categories.delete'     => ['Delete Categories',      'categories',  'Delete categories'],
        // ── Brands ────────────────────────────────────────────────────────────
        'brands.view'           => ['View Brands',            'brands',      'List and view brands'],
        'brands.create'         => ['Create Brands',          'brands',      'Create new brands'],
        'brands.edit'           => ['Edit Brands',            'brands',      'Update brands'],
        'brands.delete'         => ['Delete Brands',          'brands',      'Delete brands'],
        // ── Subscriptions ─────────────────────────────────────────────────────
        'subscriptions.view'    => ['View Subscriptions',     'subscriptions', 'View subscription info'],
        'subscriptions.manage'  => ['Manage Subscriptions',   'subscriptions', 'Upgrade/downgrade plans'],
        // ── Audit / System ────────────────────────────────────────────────────
        'audit.view'            => ['View Audit Logs',        'audit',       'Access audit trail'],
        'system.impersonate'    => ['Impersonate Users',      'system',      'Act as another user (sensitive)'],
    ];

    /**
     * Permissions granted to each default role.
     *
     * 'admin'   → all permissions in the catalog
     * 'manager' → CRUD on business objects, no sensitive system actions
     * 'user'    → read-only on own context
     */
    private const ROLE_PERMISSION_MAP = [
        'admin'   => null,  // null = ALL permissions
        'manager' => [
            'products.view', 'products.create', 'products.edit', 'products.delete',
            'orders.view',   'orders.create',   'orders.edit',
            'users.view',
            'roles.view',
            'permissions.view',
            'settings.view',
            'reports.view',
            'entities.view', 'entities.create', 'entities.edit',
            'categories.view', 'categories.create', 'categories.edit',
            'brands.view',   'brands.create',   'brands.edit',
        ],
        'user'    => [
            'products.view',
            'orders.view', 'orders.create',
            'categories.view',
            'brands.view',
            'entities.view',
        ],
    ];

    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PUBLIC API
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Create a tenant, its owner user (or reuse an existing one by e-mail),
     * and the full default RBAC structure – all inside a single transaction.
     *
     * @param array $tenantData    Must contain: name (string), domain (?string)
     * @param array $ownerUserData Must contain: username, email, password (plain-text).
     *                             Optional: phone (string), preferred_language (string).
     *
     * @return int  The newly created tenant_id.
     *
     * @throws InvalidArgumentException  When required fields are missing or invalid.
     * @throws RuntimeException          When any DB operation fails (transaction rolled back).
     */
    public function createTenantWithDefaults(array $tenantData, array $ownerUserData): int
    {
        $this->validateInputs($tenantData, $ownerUserData);

        $this->pdo->beginTransaction();
        try {
            // 1. Resolve or create the owner user
            $userId = $this->resolveOrCreateUser($ownerUserData);

            // 2. Create the tenant (owner_user_id is set from DB, not from request)
            $tenantId = $this->createTenant($tenantData, $userId);

            // 3. Ensure the global permission catalog exists (idempotent upsert)
            $permissionIds = $this->upsertGlobalPermissions();

            // 4. Create the three default tenant-scoped roles
            $roleIds = $this->createDefaultRoles($tenantId);

            // 5. Assign permissions to roles in role_permissions (tenant-scoped)
            $this->assignRolePermissions($tenantId, $roleIds, $permissionIds);

            // 6. Link the owner to the tenant with the admin role
            $this->linkOwnerToTenant($tenantId, $userId, $roleIds['admin']);

            $this->pdo->commit();
            return $tenantId;

        } catch (\RuntimeException $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw new ApplicationException(
                'TenantBootstrapService: failed to create tenant – ' . $e->getMessage(),
                (int)$e->getCode(),
                $e
            );
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PRIVATE STEPS
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Find user by e-mail or create a new active user record.
     * Returns the user ID.
     */
    private function resolveOrCreateUser(array $data): int
    {
        $stmt = $this->pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$data['email']]);
        $existing = $stmt->fetchColumn();

        if ($existing !== false) {
            return (int)$existing;
        }

        $passwordHash = password_hash($data['password'], PASSWORD_DEFAULT);

        $stmt = $this->pdo->prepare(
            'INSERT INTO users (username, email, password_hash, phone, preferred_language, is_active, created_at)
             VALUES (?, ?, ?, ?, ?, 1, NOW())'
        );
        $stmt->execute([
            $data['username'],
            $data['email'],
            $passwordHash,
            $data['phone'] ?? null,
            $data['preferred_language'] ?? 'ar',
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Insert a new tenant record.  owner_user_id comes from the resolved user,
     * never from external input.
     */
    private function createTenant(array $data, int $ownerUserId): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO tenants (name, domain, owner_user_id, status, created_at)
             VALUES (?, ?, ?, ?, NOW())'
        );
        $stmt->execute([
            $data['name'],
            $data['domain'] ?? null,
            $ownerUserId,
            $data['status'] ?? 'active',
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Upsert the global permission catalog into tenant_id = SYSTEM_TENANT_ID.
     * Rows that already exist (by key_name + tenant_id) are skipped.
     *
     * Returns an associative map of  key_name => permission_id.
     *
     * @return array<string, int>
     */
    private function upsertGlobalPermissions(): array
    {
        $systemTenantId = self::SYSTEM_TENANT_ID;
        $now = date('Y-m-d H:i:s');

        // Bulk upsert using INSERT IGNORE to avoid duplicates without extra queries
        $valueParts  = [];
        $params      = [];
        $i           = 0;
        foreach (self::PERMISSION_CATALOG as $key => [$displayName, $module, $description]) {
            $valueParts[] = "(:tid{$i}, :key{$i}, :dname{$i}, :module{$i}, :desc{$i}, :cat{$i}, 1)";
            $params[":tid{$i}"]   = $systemTenantId;
            $params[":key{$i}"]   = $key;
            $params[":dname{$i}"] = $displayName;
            $params[":module{$i}"]= $module;
            $params[":desc{$i}"]  = $description;
            $params[":cat{$i}"]   = $now;
            $i++;
        }

        // ON DUPLICATE KEY UPDATE makes the upsert explicit; avoids silently
        // swallowing real constraint violations unrelated to the key duplicate.
        $sql = 'INSERT INTO permissions (tenant_id, key_name, display_name, module, description, created_at, is_active)
                VALUES ' . implode(', ', $valueParts) . '
                ON DUPLICATE KEY UPDATE
                    display_name = VALUES(display_name),
                    module       = VALUES(module),
                    description  = VALUES(description),
                    updated_at   = NOW()';
        $this->pdo->prepare($sql)->execute($params);

        // Fetch the IDs (whether just inserted or pre-existing)
        $keys = array_keys(self::PERMISSION_CATALOG);
        $placeholders = [];
        $params = [':tenant_id' => $systemTenantId];

        foreach ($keys as $i => $keyName) {
            $pName = ':p' . $i;
            $placeholders[] = $pName;
            $params[$pName] = $keyName;
        }

        $sql = "SELECT id, key_name FROM permissions WHERE tenant_id = ? AND FIND_IN_SET(key_name, ?)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$systemTenantId, implode(',', $keys)]);

        $map = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $map[$row['key_name']] = (int)$row['id'];
        }

        return $map;
    }

    /**
     * Insert the three default tenant-scoped roles using a batch query.
     *
     * @return array<string, int>  ['admin' => id, 'manager' => id, 'user' => id]
     */
    private function createDefaultRoles(int $tenantId): array
    {
        $definitions = [
            'admin'   => 'Administrator',
            'manager' => 'Manager',
            'user'    => 'User',
        ];

        $now = date('Y-m-d H:i:s');
        $rows = [];
        $params = [];
        $i = 0;

        foreach ($definitions as $key => $displayName) {
            $rows[] = "(:tid{$i}, :key{$i}, :dname{$i}, :cat{$i})";
            $params[":tid{$i}"] = $tenantId;
            $params[":key{$i}"] = $key;
            $params[":dname{$i}"] = $displayName;
            $params[":cat{$i}"] = $now;
            $i++;
        }

        $sql = 'INSERT INTO roles (tenant_id, key_name, display_name, created_at)
                VALUES ' . implode(', ', $rows) . '
                ON DUPLICATE KEY UPDATE 
                    display_name = VALUES(display_name),
                    updated_at = NOW()';
        
        $this->pdo->prepare($sql)->execute($params);

        // Fetch back the IDs to return the expected map
        $keys = array_keys($definitions);
        $placeholders = [];
        $params = [':tid' => $tenantId];
        foreach ($keys as $i => $key) {
            $pName = ":rk{$i}";
            $placeholders[] = $pName;
            $params[$pName] = $key;
        }
        $sql = "SELECT id, key_name FROM roles WHERE tenant_id = ? AND key_name IN ('admin', 'manager', 'user')";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$tenantId]);

        $ids = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $ids[$row['key_name']] = (int)$row['id'];
        }

        return $ids;
    }

    /**
     * Batch-insert all role→permission mappings into role_permissions.
     * The table is tenant-scoped; each row carries the tenant_id.
     *
     * @param array<string, int> $roleIds       ['admin' => id, …]
     * @param array<string, int> $permissionIds ['products.view' => id, …]
     */
    private function assignRolePermissions(
        int   $tenantId,
        array $roleIds,
        array $permissionIds
    ): void {
        $allPermIds = array_values($permissionIds);
        $now        = date('Y-m-d H:i:s');

        $rows   = [];
        $params = [];
        $i      = 0;

        foreach (self::ROLE_PERMISSION_MAP as $roleKey => $allowedKeys) {
            $roleId = $roleIds[$roleKey] ?? null;
            if ($roleId === null) {
                continue;
            }

            // null means "all permissions"
            $idsToAssign = ($allowedKeys === null)
                ? $allPermIds
                : array_values(array_filter(
                    array_map(
                        fn(string $k): ?int => $permissionIds[$k] ?? null,
                        $allowedKeys
                    ),
                    fn(?int $v): bool => $v !== null
                ));

            foreach ($idsToAssign as $permId) {
                $rows[]                = "(:tid{$i}, :rid{$i}, :pid{$i}, :cat{$i})";
                $params[":tid{$i}"]    = $tenantId;
                $params[":rid{$i}"]    = $roleId;
                $params[":pid{$i}"]    = $permId;
                $params[":cat{$i}"]    = $now;
                $i++;
            }
        }

        if (empty($rows)) {
            return;
        }

        $sql = 'INSERT INTO role_permissions (tenant_id, role_id, permission_id, created_at)
                VALUES ' . implode(', ', $rows);
        $this->pdo->prepare($sql)->execute($params);
    }

    /**
     * Insert the owner into tenant_users with the admin role and is_active = 1.
     * Skips if the record already exists to remain idempotent.
     */
    private function linkOwnerToTenant(int $tenantId, int $userId, int $adminRoleId): void
    {
        $stmt = $this->pdo->prepare(
            'SELECT id FROM tenant_users WHERE tenant_id = ? AND user_id = ? LIMIT 1'
        );
        $stmt->execute([$tenantId, $userId]);

        if ($stmt->fetchColumn() !== false) {
            return; // already linked
        }

        $this->pdo->prepare(
            'INSERT INTO tenant_users (tenant_id, user_id, role_id, is_active, joined_at)
             VALUES (?, ?, ?, 1, NOW())'
        )->execute([$tenantId, $userId, $adminRoleId]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // VALIDATION
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Light validation of required fields before touching the DB.
     *
     * @throws InvalidArgumentException
     */
    private function validateInputs(array $tenantData, array $ownerUserData): void
    {
        $tenantRequired = ['name'];
        foreach ($tenantRequired as $field) {
            if (empty($tenantData[$field])) {
                throw new \InvalidArgumentException("Tenant field '{$field}' is required.");
            }
        }

        $userRequired = ['username', 'email', 'password'];
        foreach ($userRequired as $field) {
            if (empty($ownerUserData[$field])) {
                throw new \InvalidArgumentException("Owner user field '{$field}' is required.");
            }
        }

        if (!filter_var($ownerUserData['email'], FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException("Owner user field 'email' must be a valid e-mail address.");
        }

        if (strlen($ownerUserData['password']) < 8) {
            throw new \InvalidArgumentException("Owner user field 'password' must be at least 8 characters.");
        }
    }
}