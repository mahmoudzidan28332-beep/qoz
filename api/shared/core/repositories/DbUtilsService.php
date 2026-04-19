<?php
declare(strict_types=1);

/**
 * Core DB utilities — executes prepared statements on behalf of the
 * helper-layer db_utils functions so that helpers stay SQL-free.
 */
class DbUtilsService
{
    /**
     * Prepare, bind and execute a query.
     *
     * @return PDOStatement|false
     * @throws RuntimeException
     */
    public static function executeQuery(PDO $pdo, string $query, array $params = [], int $fetchMode = PDO::FETCH_ASSOC): \PDOStatement
    {
        try {
            $stmt = $pdo->prepare($query);
            if (!$stmt) {
                throw new RuntimeException('Prepare failed: ' . implode(' ', $pdo->errorInfo()));
            }

            self::bindParams($stmt, $params);

            if (!$stmt->execute()) {
                throw new RuntimeException('Execute failed: ' . implode(' ', $stmt->errorInfo()));
            }

            $stmt->setFetchMode($fetchMode);
            return $stmt;
        } catch (PDOException $e) {
            throw new RuntimeException('PDO Query Error: ' . $e->getMessage());
        }
    }

    /**
     * Bind parameters with automatic type detection.
     */
    private static function bindParams(PDOStatement $stmt, array $params): void
    {
        if (empty($params)) return;

        foreach ($params as $key => $value) {
            if (is_int($value)) {
                $type = PDO::PARAM_INT;
            } elseif (is_bool($value)) {
                $type = PDO::PARAM_BOOL;
                $value = $value ? 1 : 0;
            } elseif (is_null($value)) {
                $type = PDO::PARAM_NULL;
            } elseif (is_float($value)) {
                $type = PDO::PARAM_STR;
            } else {
                $type = PDO::PARAM_STR;
            }

            $paramName = is_int($key) ? $key + 1 : $key;
            if (!$stmt->bindValue($paramName, $value, $type)) {
                throw new RuntimeException('bindValue failed for parameter: ' . $paramName);
            }
        }
    }
}
