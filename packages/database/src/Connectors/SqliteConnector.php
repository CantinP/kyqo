<?php

namespace Kyqo\Database\Connectors;

/**
 * SQLite PDO connector.
 *
 * Configuration:
 *   'default' => 'sqlite',
 *   'connections' => [
 *       'sqlite' => [
 *           'driver'   => 'sqlite',
 *           'database' => database_path('database.sqlite'),
 *           'prefix'   => '',
 *       ],
 *   ]
 */
class SqliteConnector
{
    public function connect(array $config): \PDO
    {
        $database = $config['database'] ?? ':memory:';

        $dsn = 'sqlite:' . $database;

        $pdo = new \PDO($dsn, null, null, [
            \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        ]);

        // Enable WAL mode for better concurrency
        $pdo->exec('PRAGMA journal_mode=WAL;');
        // Enable foreign-key enforcement
        $pdo->exec('PRAGMA foreign_keys=ON;');

        return $pdo;
    }
}
