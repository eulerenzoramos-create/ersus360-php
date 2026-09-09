<?php

declare(strict_types=1);

namespace Ersus360\Core;

use PDO;
use PDOException;
use PDOStatement;

/**
 * Wrapper PDO MySQL com prepared statements obrigatórios.
 * NUNCA execute SQL com concatenação de strings de usuário — use sempre bind.
 */
final class Database
{
    private PDO $pdo;

    public function __construct(
        string $host,
        int    $port,
        string $dbname,
        string $user,
        string $pass,
        string $charset = 'utf8mb4',
    ) {
        $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset={$charset}";

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,      // prepared statements reais
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$charset} COLLATE {$charset}_unicode_ci,
                                             time_zone = '-04:00'",  // America/Manaus UTC-4
        ];

        try {
            $this->pdo = new PDO($dsn, $user, $pass, $options);
        } catch (PDOException $e) {
            // Nunca expor credenciais na mensagem de erro
            throw new \RuntimeException('Falha na conexão com o banco de dados.', 500, $e);
        }
    }

    // ── Query helpers ────────────────────────────────────────

    /**
     * Executa query e retorna todos os resultados.
     * @param array<string, mixed> $params
     * @return array<int, array<string, mixed>>
     */
    public function fetchAll(string $sql, array $params = []): array
    {
        $stmt = $this->prepare($sql, $params);
        return $stmt->fetchAll();
    }

    /**
     * Executa query e retorna um único resultado ou null.
     * @param array<string, mixed> $params
     * @return array<string, mixed>|null
     */
    public function fetchOne(string $sql, array $params = []): ?array
    {
        $stmt = $this->prepare($sql, $params);
        $row  = $stmt->fetch();
        return $row === false ? null : $row;
    }

    /**
     * Executa INSERT/UPDATE/DELETE. Retorna linhas afetadas.
     * @param array<string, mixed> $params
     */
    public function execute(string $sql, array $params = []): int
    {
        $stmt = $this->prepare($sql, $params);
        return $stmt->rowCount();
    }

    /**
     * Insere e retorna o ID gerado.
     * @param array<string, mixed> $params
     */
    public function insert(string $sql, array $params = []): int
    {
        $this->prepare($sql, $params);
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Retorna valor escalar de uma coluna, ou null.
     * @param array<string, mixed> $params
     */
    public function scalar(string $sql, array $params = []): mixed
    {
        $stmt = $this->prepare($sql, $params);
        $row  = $stmt->fetch(PDO::FETCH_NUM);
        return $row === false ? null : $row[0];
    }

    // ── Transações ───────────────────────────────────────────

    public function beginTransaction(): void
    {
        $this->pdo->beginTransaction();
    }

    public function commit(): void
    {
        $this->pdo->commit();
    }

    public function rollback(): void
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
    }

    /**
     * Executa um closure dentro de uma transação.
     * Em caso de exceção, faz rollback e re-lança.
     */
    public function transaction(callable $fn): mixed
    {
        $this->beginTransaction();
        try {
            $result = $fn($this);
            $this->commit();
            return $result;
        } catch (\Throwable $e) {
            $this->rollback();
            throw $e;
        }
    }

    // ── Interno ──────────────────────────────────────────────

    /** @param array<string, mixed> $params */
    private function prepare(string $sql, array $params): PDOStatement
    {
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $type = match (true) {
                is_int($value)  => PDO::PARAM_INT,
                is_bool($value) => PDO::PARAM_BOOL,
                is_null($value) => PDO::PARAM_NULL,
                default         => PDO::PARAM_STR,
            };
            $param = is_int($key) ? $key + 1 : ':' . ltrim((string) $key, ':');
            $stmt->bindValue($param, $value, $type);
        }
        $stmt->execute();
        return $stmt;
    }

    public function getPdo(): PDO
    {
        return $this->pdo;
    }

    /** Factory estática para uso nos Jobs CLI (fora do container IoC). */
    public static function fromEnv(): self
    {
        return new self(
            host:   $_ENV['DB_HOST']   ?? '127.0.0.1',
            port:   (int) ($_ENV['DB_PORT'] ?? 3306),
            dbname: $_ENV['DB_NAME']   ?? '',
            user:   $_ENV['DB_USER']   ?? '',
            pass:   $_ENV['DB_PASS']   ?? '',
        );
    }
}
