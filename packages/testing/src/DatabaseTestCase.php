<?php

namespace Kyqo\Testing;

use Kyqo\Database\Connection;
use Kyqo\Database\DatabaseManager;
use Kyqo\Database\Schema\Blueprint;
use Kyqo\Database\Schema\SchemaBuilder;

/**
 * DatabaseTestCase — extends TestCase with an in-memory SQLite database.
 *
 * Each test class gets a fresh SQLite :memory: database.
 * Migrations run automatically before the first test.
 * Optionally seeds the database with test fixtures.
 *
 * Usage:
 * ────────────────────────────────────────────────────────────────
 *   class UserTest extends DatabaseTestCase
 *   {
 *       // migrations run automatically from database/migrations/
 *
 *       public function test_can_create_user(): void
 *       {
 *           $user = User::create(['name' => 'Alice', 'email' => 'alice@test.com']);
 *           $this->assertDatabaseHas('users', ['email' => 'alice@test.com']);
 *       }
 *
 *       public function test_can_query_users(): void
 *       {
 *           User::create(['name' => 'Bob', 'email' => 'bob@test.com']);
 *           $this->assertDatabaseCount('users', 1);
 *       }
 *   }
 *
 * REFRESH BEHAVIOUR
 * ────────────────────────────────────────────────────────────────
 *   By default the database is refreshed (wiped + re-migrated) before
 *   each test method. To share the DB across the whole test class:
 *
 *   protected bool $refreshPerTest = false;
 *
 * CUSTOM MIGRATIONS
 * ────────────────────────────────────────────────────────────────
 *   Override migrateDatabase() to define tables inline:
 *
 *   protected function migrateDatabase(): void
 *   {
 *       $this->schema()->create('users', function (Blueprint $t) {
 *           $t->id();
 *           $t->string('name');
 *           $t->string('email')->unique();
 *           $t->timestamps();
 *       });
 *   }
 *
 * SEEDERS
 * ────────────────────────────────────────────────────────────────
 *   Override seedDatabase() to insert fixture data:
 *
 *   protected function seedDatabase(): void
 *   {
 *       User::create(['name' => 'Admin', 'email' => 'admin@test.com']);
 *   }
 */
abstract class DatabaseTestCase extends TestCase
{
    /**
     * Refresh (wipe + re-migrate) before each individual test.
     * Set to false to share the DB across the whole class (faster).
     */
    protected bool $refreshPerTest = true;

    /** Shared PDO instance for the test class. */
    private static ?\PDO $sharedPdo = null;

    // ── Lifecycle ────────────────────────────────────────────────────────

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::$sharedPdo = null; // reset per class
    }

    protected function setUp(): void
    {
        parent::setUp();

        if ($this->refreshPerTest) {
            $this->bootDatabase();
        } elseif (self::$sharedPdo === null) {
            $this->bootDatabase();
        } else {
            $this->bindExistingDatabase(self::$sharedPdo);
        }
    }

    protected function tearDown(): void
    {
        if ($this->refreshPerTest) {
            self::$sharedPdo = null;
        }
        parent::tearDown();
    }

    // ── Database boot ──────────────────────────────────────────────────

    private function bootDatabase(): void
    {
        $pdo = new \PDO('sqlite::memory:', null, null, [
            \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        ]);
        $pdo->exec('PRAGMA foreign_keys=ON;');
        $pdo->exec('PRAGMA journal_mode=WAL;');

        self::$sharedPdo = $pdo;

        $this->bindExistingDatabase($pdo);
        $this->migrateDatabase();
        $this->seedDatabase();
    }

    private function bindExistingDatabase(\PDO $pdo): void
    {
        $connection = new Connection($pdo, 'sqlite');
        $manager    = new DatabaseManager(['default' => 'sqlite'], $connection);

        // Rebind in container
        $this->app->instance('db', $manager);
        $this->app->instance(DatabaseManager::class, $manager);
        $this->app->instance(Connection::class, $connection);

        // Point ORM models to this connection
        \Kyqo\Database\Orm\Model::setConnectionResolver($manager);
    }

    // ── Override hooks ────────────────────────────────────────────────

    /**
     * Override to define your schema inline or run migration files.
     * Default: runs all migrations from database/migrations/.
     */
    protected function migrateDatabase(): void
    {
        $this->runMigrationFiles();
    }

    /**
     * Override to seed the database with fixture data.
     * Called after migrateDatabase().
     */
    protected function seedDatabase(): void
    {
        // No-op by default
    }

    // ── Schema helper ─────────────────────────────────────────────────

    /**
     * Access the schema builder for the in-memory DB.
     */
    protected function schema(): SchemaBuilder
    {
        return new SchemaBuilder($this->app->make(Connection::class));
    }

    /**
     * Run all migration files from database/migrations/ against the in-memory DB.
     *
     * Migration files must follow the naming convention:
     *   YYYY_MM_DD_HHMMSS_description.php
     * and return a class with an up() method.
     */
    protected function runMigrationFiles(): void
    {
        $dir = $this->app->databasePath('migrations');
        if (!is_dir($dir)) return;

        $files = glob($dir . '/*.php') ?: [];
        sort($files);

        $connection = $this->app->make(Connection::class);

        foreach ($files as $file) {
            $migration = require $file;
            if (is_object($migration) && method_exists($migration, 'up')) {
                try {
                    $migration->up($connection);
                } catch (\Throwable $e) {
                    // Skip if table already exists (re-run on same :memory: instance)
                    if (!str_contains($e->getMessage(), 'already exists')) {
                        throw $e;
                    }
                }
            }
        }
    }

    // ── Database assertions ────────────────────────────────────────────

    /**
     * Assert that a table contains a row matching the given column values.
     *
     * @param string $table
     * @param array  $data  Columns + values to match (WHERE clause)
     */
    public function assertDatabaseHas(string $table, array $data): static
    {
        $connection = $this->app->make(Connection::class);
        $qb         = $connection->table($table);

        foreach ($data as $col => $value) {
            $qb->where($col, '=', $value);
        }

        $count = $qb->count();

        \PHPUnit\Framework\Assert::assertGreaterThan(
            0,
            $count,
            "Failed asserting that table [{$table}] contains " . json_encode($data) . "."
        );

        return $this;
    }

    /**
     * Assert that a table does NOT contain a row matching the given values.
     */
    public function assertDatabaseMissing(string $table, array $data): static
    {
        $connection = $this->app->make(Connection::class);
        $qb         = $connection->table($table);

        foreach ($data as $col => $value) {
            $qb->where($col, '=', $value);
        }

        $count = $qb->count();

        \PHPUnit\Framework\Assert::assertEquals(
            0,
            $count,
            "Failed asserting that table [{$table}] does not contain " . json_encode($data) . "."
        );

        return $this;
    }

    /**
     * Assert the total number of rows in a table.
     */
    public function assertDatabaseCount(string $table, int $expected): static
    {
        $connection = $this->app->make(Connection::class);
        $count      = $connection->table($table)->count();

        \PHPUnit\Framework\Assert::assertEquals(
            $expected,
            $count,
            "Failed asserting that table [{$table}] has {$expected} row(s). Got {$count}."
        );

        return $this;
    }

    /**
     * Assert that a table is empty.
     */
    public function assertDatabaseEmpty(string $table): static
    {
        return $this->assertDatabaseCount($table, 0);
    }

    /**
     * Assert that a model was soft-deleted (has a non-null deleted_at).
     */
    public function assertSoftDeleted(string $table, array $data): static
    {
        $connection = $this->app->make(Connection::class);
        $qb         = $connection->table($table)->whereNotNull('deleted_at');

        foreach ($data as $col => $value) {
            $qb->where($col, '=', $value);
        }

        $count = $qb->count();

        \PHPUnit\Framework\Assert::assertGreaterThan(
            0,
            $count,
            "Failed asserting that table [{$table}] has a soft-deleted row matching " . json_encode($data) . "."
        );

        return $this;
    }

    /**
     * Directly access the underlying PDO for raw SQL in tests.
     */
    protected function pdo(): \PDO
    {
        return self::$sharedPdo;
    }

    /**
     * Truncate a table (delete all rows, reset auto-increment).
     */
    protected function truncate(string $table): void
    {
        $this->app->make(Connection::class)
            ->statement("DELETE FROM `{$table}`");
    }
}
