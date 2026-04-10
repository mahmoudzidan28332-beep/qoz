<?php
declare(strict_types=1);

class RbacService
{
    private RbacRepository $repo;

    public function __construct()
    {
        $this->repo = new RbacRepository();
    }

    public function getUserPermissions(int $userId): array
    {
        $roleId = $this->repo->getUserRoleId($userId);
        if (!$roleId) return [];
        return $this->repo->getPermissionsByRole($roleId);
    }
}
