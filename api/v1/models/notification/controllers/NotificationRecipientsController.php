<?php
declare(strict_types=1);

final class NotificationRecipientsController
{
    private NotificationRecipientsService $service;

    public function __construct(NotificationRecipientsService $service)
    {
        $this->service = $service;
    }

    public function list(
        array  $filters  = [],
        string $orderBy  = 'id',
        string $orderDir = 'DESC',
        ?int   $limit    = null,
        ?int   $offset   = null
    ): array {
        return $this->service->list($filters, $orderBy, $orderDir, $limit, $offset);
    }

    public function get(int $id): array
    {
        return $this->service->get($id);
    }

    public function markRead(int $id, int $tenantId): bool
    {
        return $this->service->markRead($id, $tenantId);
    }

    public function markUnread(int $id, int $tenantId): bool
    {
        return $this->service->markUnread($id, $tenantId);
    }

    public function delete(int $id, int $tenantId): void
    {
        $this->service->delete($id, $tenantId);
    }
}
