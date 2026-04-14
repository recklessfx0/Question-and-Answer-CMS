<?php

declare(strict_types=1);

namespace Core;

use PDO;
use PDOException;
use Throwable;

final class Database
{
    private ?PDO $pdo = null;

    /** @param array<string, string|int|bool> $config */
    public function __construct(private readonly array $config) {}

    public function pdo(): PDO
    {
        if ($this->pdo instanceof PDO) {
            return $this->pdo;
        }

        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', $this->config['host'], $this->config['port'], $this->config['database'], $this->config['charset']);

        return $this->pdo = new PDO($dsn, (string) $this->config['username'], (string) $this->config['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }

    /** @param array<string|int, mixed> $params */
    public function query(string $sql, array $params = []): \PDOStatement
    {
        $statement = $this->pdo()->prepare($sql);
        foreach ($params as $key => $value) {
            $type = match (true) {
                $value === null => PDO::PARAM_NULL,
                is_int($value) => PDO::PARAM_INT,
                is_bool($value) => PDO::PARAM_BOOL,
                default => PDO::PARAM_STR,
            };
            $statement->bindValue(is_int($key) ? $key + 1 : (string) $key, $value, $type);
        }

        $statement->execute();
        return $statement;
    }

    /** @param array<string|int, mixed> $params @return array<string, mixed>|null */
    public function fetch(string $sql, array $params = []): ?array
    {
        $row = $this->query($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    /** @param array<string|int, mixed> $params @return array<int, array<string, mixed>> */
    public function fetchAll(string $sql, array $params = []): array
    {
        return $this->query($sql, $params)->fetchAll();
    }

    /** @param array<string|int, mixed> $params */
    public function execute(string $sql, array $params = []): bool
    {
        return $this->query($sql, $params)->rowCount() >= 0;
    }

    public function lastInsertId(): int
    {
        return (int) $this->pdo()->lastInsertId();
    }

    public function transaction(callable $callback): mixed
    {
        $attempts = 0;
        beginning:
        try {
            $this->pdo()->beginTransaction();
            $result = $callback($this);
            $this->pdo()->commit();
            return $result;
        } catch (Throwable $e) {
            if ($this->pdo()->inTransaction()) {
                $this->pdo()->rollBack();
            }

            $retryable = $e instanceof PDOException
                && (str_contains($e->getMessage(), 'Deadlock') || str_contains($e->getMessage(), 'Lock wait timeout'));
            if ($retryable && ++$attempts < 3) {
                usleep(100000 * $attempts);
                goto beginning;
            }

            throw $e;
        }
    }
}
