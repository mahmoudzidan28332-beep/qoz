<?php
declare(strict_types=1);

/**
 * Centralised factory for domain exceptions.
 *
 * Each static method wraps a low-level exception in the corresponding
 * domain exception, ensuring a consistent message + context structure
 * without repeating the constructor call at every catch site.
 */
final class ExceptionFactory
{
    private function __construct() {}

    /**
     * Wrap a PDOException in a DatabaseException.
     *
     * @param \PDOException $e       Original PDO error.
     * @param array         $context Diagnostic context (table, sqlstate, …).
     * @param string        $message Human-readable summary.
     */
    public static function database(
        \PDOException $e,
        array $context = [],
        string $message = 'Database error'
    ): DatabaseException {
        return new DatabaseException($message, $context, $e);
    }
}
