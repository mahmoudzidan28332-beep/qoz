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
 * Booted ONCE per request in bootstrap.php and stored in $GLOBALS['app_container'].
 * HTTP entry files consume it via $GLOBALS['app_container']->userRepository(), etc.
 * Never instantiate Container inside individual request handlers.
 *
 * Usage (bootstrap):
 *   $GLOBALS['app_container'] = new Container($GLOBALS['ADMIN_DB']);
 *
 * Usage (HTTP entry):
 *   $repository = $GLOBALS['app_container']->userRepository();
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
