<?php
declare(strict_types=1);

final class AuthorizationException extends AppException
{
    protected int $statusCode = 403;

    public function __construct(
        string $message = 'Forbidden',
        array $context = [],
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
        $this->context = $context;
    }
}