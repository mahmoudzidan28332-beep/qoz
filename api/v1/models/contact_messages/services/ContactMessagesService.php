<?php
declare(strict_types=1);

final class ContactMessagesService
{
    private PdoContactMessagesRepository $repo;

    public function __construct(PdoContactMessagesRepository $repo)
    {
        $this->repo = $repo;
    }

    public function createMessage(int $tenantId, int $userId, string $name, string $email, string $subject, string $message): int
    {
        return $this->repo->createMessage($tenantId, $userId, $name, $email, $subject, $message);
    }
}
