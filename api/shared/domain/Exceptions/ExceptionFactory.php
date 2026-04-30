<?php
declare(strict_types=1);

namespace Shared\Domain\Exceptions;

/**
 * Injectable factory for domain exceptions (namespaced layer).
 *
 * Instantiate once and inject via constructor wherever a repository or
 * service in the Shared\* namespaced layer needs to raise a domain exception.
 *
 * Usage:
 *   class MyRepository
 *   {
 *       public function __construct(
 *           private \PDO $pdo,
 *           private ExceptionFactory $exceptions = new ExceptionFactory()
 *       ) {}
 *
 *       private function doQuery(): void
 *       {
 *           try { ... } catch (\PDOException $e) {
 *               throw $this->exceptions->database($e, ['table' => 'my_table']);
 *           }
 *       }
 *   }
 */
final class ExceptionFactory
{
    /**
     * Wrap a PDOException in a DatabaseException.
     *
     * @param \PDOException $e       The original PDO error.
     * @param array         $context Diagnostic context (table, sqlstate, …).
     * @param string        $message Human-readable summary.
     */
    public function database(
        \PDOException $e,
        array $context = [],
        string $message = 'Database error'
    ): DatabaseException {
        return new DatabaseException($message, $context, $e);
    }

    /**
     * Create a NotFoundException.
     */
    public function notFound(
        string $message = 'Resource not found',
        array $context = [],
        ?\Throwable $previous = null
    ): NotFoundException {
        return new NotFoundException($message, $context, $previous);
    }
}
