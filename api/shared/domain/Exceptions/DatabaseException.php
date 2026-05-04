<?php
declare(strict_types=1);

namespace Shared\Domain\Exceptions;

final class DatabaseException extends DomainException
{
    protected string $errorCode = 'database_error';
    protected int $statusCode = 500;
}
