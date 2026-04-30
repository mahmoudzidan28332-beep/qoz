<?php
declare(strict_types=1);

class ApplicationException extends AppException
{
    protected int $statusCode = 400;

    public function __construct(
        string $message = 'Application error',
        array $context = [],
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
        $this->context = $context;
    }
}
