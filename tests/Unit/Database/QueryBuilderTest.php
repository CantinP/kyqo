<?php

namespace Kyqo\Tests\Unit\Database;

use Kyqo\Database\Connection;
use Kyqo\Database\QueryBuilder;
use PHPUnit\Framework\TestCase;

class QueryBuilderTest extends TestCase
{
    private Connection $conn;

    protected function setUp(): void
    {
        $pdo = new \PDO('sqlite::memory:');
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT, email TEXT)');
        $pdo->exec("INSERT INTO users (name, email) VALUES ('Alice','alice@ex.com'),('Bob','bob@ex.com')");

        $this->conn = new Connection($pdo, 'sqlite');
    }

    public function testSelectAll(): void
    {
        $rows = (new QueryBuilder($this->conn, 'users'))->get();
        $this->assertCount(2, $rows);
    }

    public function testWhere(): void
    {
        $row = (new QueryBuilder($this->conn, 'users'))->where('name', 'Alice')->first();
        $this->assertSame('alice@ex.com', $row['email']);
    }

    public function testWhereIn(): void
    {
        $rows = (new QueryBuilder($this->conn, 'users'))->whereIn('name', ['Alice', 'Bob'])->get();
        $this->assertCount(2, $rows);
    }

    public function testInsertUpdateDelete(): void
    {
        $qb = new QueryBuilder($this->conn, 'users');
        $qb->insert(['name' => 'Charlie', 'email' => 'c@ex.com']);
        $this->assertSame(3, (new QueryBuilder($this->conn, 'users'))->count());

        (new QueryBuilder($this->conn, 'users'))->where('name', 'Charlie')->update(['email' => 'new@ex.com']);
        $row = (new QueryBuilder($this->conn, 'users'))->where('name', 'Charlie')->first();
        $this->assertSame('new@ex.com', $row['email']);

        (new QueryBuilder($this->conn, 'users'))->where('name', 'Charlie')->delete();
        $this->assertSame(2, (new QueryBuilder($this->conn, 'users'))->count());
    }

    public function testOrderByLimitOffset(): void
    {
        $rows = (new QueryBuilder($this->conn, 'users'))
            ->orderBy('name', 'asc')
            ->limit(1)->offset(1)
            ->get();
        $this->assertCount(1, $rows);
        $this->assertSame('Bob', $rows[0]['name']);
    }

    public function testToSqlContainsJoin(): void
    {
        $qb  = (new QueryBuilder($this->conn, 'users'))
            ->join('posts', 'users.id', '=', 'posts.user_id');
        $sql = $qb->toSql();
        $this->assertStringContainsString('INNER JOIN', $sql);
        $this->assertStringContainsString('posts', $sql);
    }

    public function testInvalidIdentifierThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new QueryBuilder($this->conn, 'users'))->where('na me', 'x')->get();
    }
}
