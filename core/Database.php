<?php

declare(strict_types=1);

final class QueryResult
{
    public function __construct(
        private readonly int $rowCount,
        private readonly string $lastInsertId,
    ) {
    }

    public function rowCount(): int
    {
        return $this->rowCount;
    }

    public function lastInsertId(): string
    {
        return $this->lastInsertId;
    }
}

final class Database
{
    private static ?self $platform = null;

    /** @var array<string, self> */
    private static array $projects = [];

    private function __construct(
        private readonly PDO $pdo,
        private readonly string $path,
    ) {
    }

    public static function platform(string $rootPath): self
    {
        if (self::$platform !== null) {
            return self::$platform;
        }

        $dbPath = rtrim($rootPath, '/') . '/data/platform.sqlite';
        self::$platform = self::connect($dbPath);

        return self::$platform;
    }

    public static function project(string $projectDbPath): self
    {
        $real = $projectDbPath;
        if (isset(self::$projects[$real])) {
            return self::$projects[$real];
        }

        $connection = self::connect($real);
        self::$projects[$real] = $connection;

        return $connection;
    }

    public static function resetConnections(): void
    {
        self::$platform = null;
        self::$projects = [];
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function fetchAll(string $sql, array $params = []): array
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);

        return $statement->fetchAll();
    }

    public function fetchOne(string $sql, array $params = []): ?array
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);
        $row = $statement->fetch();

        return $row === false ? null : $row;
    }

    public function run(string $sql, array $params = []): QueryResult
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);

        return new QueryResult($statement->rowCount(), (string) $this->pdo->lastInsertId());
    }

    private static function connect(string $dbPath): self
    {
        $directory = dirname($dbPath);
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException('Unable to create database directory.');
        }

        $isNew = !is_file($dbPath);

        try {
            $pdo = new PDO('sqlite:' . $dbPath);
        } catch (PDOException $exception) {
            throw new RuntimeException('Unable to open SQLite database.', 0, $exception);
        }

        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        $pdo->exec('PRAGMA foreign_keys = ON');
        $pdo->exec('PRAGMA busy_timeout = 5000');

        try {
            $pdo->exec('PRAGMA journal_mode = WAL');
        } catch (Throwable $exception) {
            lab_log('warning', 'Unable to enable SQLite WAL mode; continuing with default journal mode.', [
                'path' => basename($dbPath),
                'error' => $exception->getMessage(),
            ]);
        }

        if ($isNew) {
            @chmod($dbPath, 0664);
        }

        return new self($pdo, $dbPath);
    }
}
