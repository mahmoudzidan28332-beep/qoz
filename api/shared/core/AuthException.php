<?php
declare(strict_types=1);

final class AuthException extends AppException
{
    protected int $statusCode = 401;

    public function __construct(
        string $message = 'Authentication required',
        array $context = [],
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
        $this->context = $context;
    }
}
