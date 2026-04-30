<?php
declare(strict_types=1);

namespace Shared\Application;

use PDO;
use Shared\Application\Auth\IdentityHydrator;
use Shared\Domain\Exceptions\ExceptionFactory;
use Shared\Infrastructure\Persistence\MySQL\UserRepository;

/**
 * Lightweight composition-root container for the Shared layer.
 *
 * Instantiate ONCE per request entry point and pass it down; never
 * let inner layers create their own ExceptionFactory instances.
 *
 * Usage (HTTP entry point):
 *   $container  = new Container($GLOBALS['ADMIN_DB']);
 *   $repository = $container->userRepository();
 *
 * Usage (static resolver):
 *   $container = new Container($pdo);
 *   $hydrator  = $container->identityHydrator();
 */
final class Container
{
    public function __construct(private readonly ?PDO $pdo = null) {}

    // ──────────────────────────────────────────────────────────────────────────
    // Shared dependencies
    // ──────────────────────────────────────────────────────────────────────────

    public function exceptionFactory(): ExceptionFactory
    {
        return new ExceptionFactory();
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Infrastructure
    // ──────────────────────────────────────────────────────────────────────────

    public function userRepository(): UserRepository
    {
        if (!$this->pdo instanceof PDO) {
            throw new \LogicException('Container requires a PDO instance to build UserRepository.');
        }

        return new UserRepository($this->pdo, $this->exceptionFactory());
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Application
    // ──────────────────────────────────────────────────────────────────────────

    public function identityHydrator(): IdentityHydrator
    {
        return new IdentityHydrator($this->pdo, $this->exceptionFactory());
    }
}
