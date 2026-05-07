<?php
declare(strict_types=1);

final class NotificationRecipientsService
{
    private PdoNotificationRecipientsRepository $repo;
    private NotificationRecipientsValidator     $validator;

    public function __construct(
        PdoNotificationRecipientsRepository $repo,
        NotificationRecipientsValidator     $validator
    ) {
        $this->repo      = $repo;
        $this->validator = $validator;
    }

    public function list(
        array  $filters  = [],
        string $orderBy  = 'id',
        string $orderDir = 'DESC',
        ?int   $limit    = null,
        ?int   $offset   = null
    ): array {
        $items = $this->repo->all($limit, $offset, $filters, $orderBy, $orderDir);
        $total = $this->repo->count($filters);
        return ['items' => $items, 'total' => $total];
    }

    public function get(int $id): array
    {
        $row = $this->repo->find($id);
        if (!$row) {
            throw new ApplicationException('Notification recipient not found.');
        }
        return $row;
    }

    public function markRead(int $id, int $tenantId): bool
    {
        $row = $this->repo->find($id);
        if (!$row) {
            throw new ApplicationException('Notification recipient not found.');
        }
        return $this->repo->markRead($id, $tenantId);
    }

    public function markUnread(int $id, int $tenantId): bool
    {
        $row = $this->repo->find($id);
        if (!$row) {
            throw new ApplicationException('Notification recipient not found.');
        }
        return $this->repo->markUnread($id, $tenantId);
    }

    public function delete(int $id, int $tenantId): void
    {
        $row = $this->repo->find($id);
        if (!$row) {
            throw new ApplicationException('Notification recipient not found.');
        }
        $this->repo->delete($id, $tenantId);
    }
}
