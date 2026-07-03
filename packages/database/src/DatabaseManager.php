<?php

namespace Kyqo\Database;

use PDO;

/**
 * Manages named database connections.
 *
 * Config shape expected:
 *   [
 *     'default'     => 'mysql',
 *     'connections' => [
 *       'mysql' => ['driver'=>'mysql','host'=>..,'port'=>..,'database'=>..,'username'=>..,'password'=>..,'charset'=>'utf8mb4'],
 *       'sqlite' => ['driver'=>'sqlite','database'=>'/path/to/db.sqlite'],
 *     ]
 *   ]
 */
class DatabaseManager
{
    protected array $config;
    protected array $connections = [];

    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    /**
     * Get a named connection (or the default one).
     */
    public function connection(?string $name = null): Connection
    {
        $name ??= $this->config['default'] ?? 'mysql';

        if (!isset($this->connections[$name])) {
            $this->connections[$name] = $this->makeConnection($name);
        }

        return $this->connections[$name];
    }

    /**
     * Proxy table() to the default connection for convenience.
     */
    public function table(string $table): QueryBuilder
    {
        return $this->connection()->table($table);
    }

    /**
     * Proxy schema() to the default connection.
     */
    public function schema(): Schema\SchemaBuilder
    {
        return $this->connection()->schema();
    }

    /**
     * Proxy select() to the default connection.
     */
    public function select(string $sql, array $bindings = []): array
    {
        return $this->connection()->select($sql, $bindings);
    }

    /**
     * Proxy statement() to the default connection.
     */
    public function statement(string $sql, array $bindings = []): bool
    {
        return $this->connection()->statement($sql, $bindings);
    }

    // ---- Internals ----------------------------------------------------------

    protected function makeConnection(string $name): Connection
    {
        $config = $this->config['connections'][$name]
            ?? throw new \InvalidArgumentException("Database connection [{$name}] not configured.");

        $driver = $config['driver'] ?? 'mysql';

        $pdo = match ($driver) {
            'mysql'  => $this->makeMysql($config),
            'pgsql'  => $this->makePgsql($config),
            'sqlite' => $this->makeSqlite($config),
            default  => throw new \InvalidArgumentException("Database driver [{$driver}] not supported."),
        };

        return new Connection($pdo, $driver, $config['database'] ?? '');
    }

    protected function makeMysql(array $cfg): PDO
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            $cfg['host']     ?? '127.0.0.1',
            $cfg['port']     ?? '3306',
            $cfg['database'] ?? '',
            $cfg['charset']  ?? 'utf8mb4'
        );
        return new PDO($dsn, $cfg['username'] ?? '', $cfg['password'] ?? '', [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }

    protected function makePgsql(array $cfg): PDO
    {
        $dsn = sprintf(
            'pgsql:host=%s;port=%s;dbname=%s',
            $cfg['host']     ?? '127.0.0.1',
            $cfg['port']     ?? '5432',
            $cfg['database'] ?? ''
        );
        return new PDO($dsn, $cfg['username'] ?? '', $cfg['password'] ?? '', [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }

    protected function makeSqlite(array $cfg): PDO
    {
        $path = $cfg['database'] ?? ':memory:';
        $pdo  = new PDO('sqlite:' . $path, options: [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo->exec('PRAGMA journal_mode=WAL; PRAGMA foreign_keys=ON;');
        return $pdo;
    }
}
