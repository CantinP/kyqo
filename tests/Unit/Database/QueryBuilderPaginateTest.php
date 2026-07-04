<?php

namespace Tests\Unit\Database;

use PHPUnit\Framework\TestCase;
use Kyqo\Database\QueryBuilder;
use Kyqo\Database\Connection;

class QueryBuilderPaginateTest extends TestCase
{
    private function makeMockConnection(int $total, array $rows): Connection
    {
        $conn = $this->createMock(Connection::class);

        // count() call — returns aggregate row
        $conn->method('selectOne')
             ->willReturn(['aggregate' => $total]);

        // get() call — returns data rows
        $conn->method('select')
             ->willReturn($rows);

        $conn->method('getDriver')
             ->willReturn('mysql');

        return $conn;
    }

    public function test_paginate_returns_correct_structure(): void
    {
        $rows = [['id' => 1, 'name' => 'Alice'], ['id' => 2, 'name' => 'Bob']];
        $conn = $this->makeMockConnection(50, $rows);
        $qb   = new QueryBuilder($conn, 'users');

        $result = $qb->paginate(15, 1);

        $this->assertArrayHasKey('data', $result);
        $this->assertArrayHasKey('total', $result);
        $this->assertArrayHasKey('per_page', $result);
        $this->assertArrayHasKey('current_page', $result);
        $this->assertArrayHasKey('last_page', $result);
    }

    public function test_paginate_calculates_last_page(): void
    {
        $conn   = $this->makeMockConnection(100, []);
        $qb     = new QueryBuilder($conn, 'users');
        $result = $qb->paginate(15, 1);

        $this->assertSame(7, $result['last_page']); // ceil(100/15) = 7
    }

    public function test_paginate_sets_current_page(): void
    {
        $conn   = $this->makeMockConnection(100, []);
        $qb     = new QueryBuilder($conn, 'users');
        $result = $qb->paginate(10, 3);

        $this->assertSame(3, $result['current_page']);
    }

    public function test_paginate_with_zero_results(): void
    {
        $conn   = $this->makeMockConnection(0, []);
        $qb     = new QueryBuilder($conn, 'users');
        $result = $qb->paginate(15, 1);

        $this->assertSame(0, $result['total']);
        $this->assertSame(1, $result['last_page']); // always at least 1
        $this->assertEmpty($result['data']);
    }

    public function test_paginate_enforces_minimum_page_1(): void
    {
        $conn   = $this->makeMockConnection(30, []);
        $qb     = new QueryBuilder($conn, 'users');
        $result = $qb->paginate(10, 0); // page 0 → forced to 1

        $this->assertSame(1, $result['current_page']);
    }

    public function test_paginate_per_page_is_preserved(): void
    {
        $conn   = $this->makeMockConnection(40, []);
        $qb     = new QueryBuilder($conn, 'users');
        $result = $qb->paginate(20, 2);

        $this->assertSame(20, $result['per_page']);
    }
}
