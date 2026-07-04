<?php

namespace Kyqo\Tests\Unit\View;

use Kyqo\View\Pagination\Paginator;
use PHPUnit\Framework\TestCase;

class PaginatorTest extends TestCase
{
    private function make(int $total, int $perPage, int $page): Paginator
    {
        $lastPage = (int) ceil($total / $perPage);
        return new Paginator([
            'data'         => array_fill(0, min($perPage, max(0, $total - ($page - 1) * $perPage)), 'item'),
            'total'        => $total,
            'per_page'     => $perPage,
            'current_page' => $page,
            'last_page'    => $lastPage,
        ], '/users', $page);
    }

    public function testNoLinksWhenSinglePage(): void
    {
        $p = $this->make(5, 15, 1);
        $this->assertFalse($p->hasPages());
        $this->assertSame('', $p->links());
    }

    public function testFirstPageHasNoBack(): void
    {
        $p = $this->make(100, 10, 1);
        $this->assertNull($p->previousPageUrl());
        $this->assertNotNull($p->nextPageUrl());
    }

    public function testLastPageHasNoNext(): void
    {
        $p = $this->make(100, 10, 10);
        $this->assertNull($p->nextPageUrl());
        $this->assertNotNull($p->previousPageUrl());
    }

    public function testPageUrls(): void
    {
        $p = $this->make(100, 10, 5);
        $this->assertSame('/users?page=4', $p->previousPageUrl());
        $this->assertSame('/users?page=6', $p->nextPageUrl());
        $this->assertSame('/users?page=3', $p->pageUrl(3));
    }

    public function testLinksContainNavElement(): void
    {
        $p    = $this->make(100, 10, 5);
        $html = $p->links();
        $this->assertStringContainsString('<nav', $html);
        $this->assertStringContainsString('pagination', $html);
    }

    public function testLinksHighlightCurrentPage(): void
    {
        $p    = $this->make(100, 10, 5);
        $html = $p->links();
        $this->assertStringContainsString('active', $html);
        $this->assertStringContainsString('>5<', $html);
    }

    public function testLinksContainEllipsis(): void
    {
        // page 1, 10 pages total → gap between 4 and 10
        $p    = $this->make(100, 10, 1);
        $html = $p->links();
        $this->assertStringContainsString('&hellip;', $html);
    }

    public function testItems(): void
    {
        $p = $this->make(25, 10, 2);
        $this->assertCount(10, $p->items());
        $this->assertSame(25, $p->total());
        $this->assertSame(3, $p->lastPage());
    }
}
