<?php
declare(strict_types=1);

namespace Shared\Infrastructure\Persistence\MySQL;

use PDO;
use Shared\Application\Repositories\UserRepositoryInterface;
use Shared\Application\DTO\CreateUserDTO;
use Shared\Domain\Exceptions\ExceptionFactory;

final class UserRepository implements UserRepositoryInterface
{
    private ExceptionFactory $exceptions;

    public function __construct(private PDO $pdo, ExceptionFactory $exceptions)
    {
        $this->exceptions = $exceptions;
    }

    public function create(CreateUserDTO $dto): int
    {
        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO users (email, password, name) VALUES (:email, :password, :name)'
            );
            $stmt->execute([
                'email'    => $dto->email,
                'password' => password_hash($dto->password, PASSWORD_BCRYPT),
                'name'     => $dto->name,
            ]);
        } catch (\PDOException $e) {
            throw $this->exceptions->database($e, ['table' => 'users'], 'User insert failed');
        }

        return (int)$this->pdo->lastInsertId();
    }
}
