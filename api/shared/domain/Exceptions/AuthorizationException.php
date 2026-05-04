<?php
declare(strict_types=1);

namespace Shared\Domain\Exceptions;

final class AuthorizationException extends DomainException
{
    protected string $errorCode = 'forbidden';
    protected int $statusCode = 403;
}
