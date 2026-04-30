<?php
declare(strict_types=1);

final class DatabaseException extends AppException
{
    protected int $statusCode = 503;

    public function __construct(
        string $message = 'Database error',
        array $context = [],
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
        $this->context = $context;
    }
}
