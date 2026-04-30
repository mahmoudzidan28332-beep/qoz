<?php
declare(strict_types=1);

final class SystemException extends AppException
{
    protected int $statusCode = 500;

    public function __construct(
        string $message = 'System error',
        array $context = [],
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
        $this->context = $context;
    }
}
