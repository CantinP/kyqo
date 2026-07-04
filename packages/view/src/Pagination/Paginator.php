<?php

namespace Kyqo\View\Pagination;

/**
 * Paginator
 *
 * Wraps a paginated result set (from ModelQueryBuilder::paginate() or
 * QueryBuilder::paginate()) and renders HTML pagination links.
 *
 * Usage in a controller:
 *   $result = User::query()->paginate(15);
 *   $paginator = new Paginator($result, $request->url(), $request->query('page', 1));
 *   return view('users.index', ['users' => $paginator->items(), 'pages' => $paginator]);
 *
 * Usage in a view:
 *   <?= $pages->links() ?>
 *
 * Or via the global helper:
 *   <?= paginator($result)->links() ?>
 */
class Paginator
{
    protected array $data;
    protected int   $total;
    protected int   $perPage;
    protected int   $currentPage;
    protected int   $lastPage;
    protected string $baseUrl;
    protected string $pageParam;

    public function __construct(
        array  $paginationResult,
        string $baseUrl    = '/',
        int    $currentPage = 1,
        string $pageParam  = 'page'
    ) {
        $this->data        = $paginationResult['data']         ?? [];
        $this->total       = (int) ($paginationResult['total']     ?? 0);
        $this->perPage     = (int) ($paginationResult['per_page']  ?? 15);
        $this->currentPage = (int) ($paginationResult['current_page'] ?? $currentPage);
        $this->lastPage    = (int) ($paginationResult['last_page'] ?? 1);
        $this->baseUrl     = rtrim(strtok($baseUrl, '?'), '/');
        $this->pageParam   = $pageParam;
    }

    public function items(): array   { return $this->data; }
    public function total(): int     { return $this->total; }
    public function perPage(): int   { return $this->perPage; }
    public function currentPage(): int { return $this->currentPage; }
    public function lastPage(): int  { return $this->lastPage; }
    public function hasPages(): bool { return $this->lastPage > 1; }

    public function previousPageUrl(): ?string
    {
        if ($this->currentPage <= 1) return null;
        return $this->pageUrl($this->currentPage - 1);
    }

    public function nextPageUrl(): ?string
    {
        if ($this->currentPage >= $this->lastPage) return null;
        return $this->pageUrl($this->currentPage + 1);
    }

    public function pageUrl(int $page): string
    {
        return $this->baseUrl . '?' . $this->pageParam . '=' . $page;
    }

    /**
     * Render Bootstrap-5-compatible pagination HTML.
     *
     * Renders at most $window pages on each side of the current page,
     * with "..." gaps and first/last page links.
     */
    public function links(int $window = 3): string
    {
        if (!$this->hasPages()) return '';

        $pages = $this->pageRange($window);
        $html  = '<nav aria-label="Page navigation"><ul class="pagination">';

        // Previous
        if ($this->currentPage <= 1) {
            $html .= '<li class="page-item disabled"><span class="page-link">&laquo;</span></li>';
        } else {
            $html .= '<li class="page-item"><a class="page-link" href="' . $this->e($this->previousPageUrl()) . '">&laquo;</a></li>';
        }

        $prev = null;
        foreach ($pages as $p) {
            if ($prev !== null && $p - $prev > 1) {
                $html .= '<li class="page-item disabled"><span class="page-link">&hellip;</span></li>';
            }
            if ($p === $this->currentPage) {
                $html .= '<li class="page-item active" aria-current="page"><span class="page-link">' . $p . '</span></li>';
            } else {
                $html .= '<li class="page-item"><a class="page-link" href="' . $this->e($this->pageUrl($p)) . '">' . $p . '</a></li>';
            }
            $prev = $p;
        }

        // Next
        if ($this->currentPage >= $this->lastPage) {
            $html .= '<li class="page-item disabled"><span class="page-link">&raquo;</span></li>';
        } else {
            $html .= '<li class="page-item"><a class="page-link" href="' . $this->e($this->nextPageUrl()) . '">&raquo;</a></li>';
        }

        $html .= '</ul></nav>';
        return $html;
    }

    /**
     * Build the page number list: always include 1, lastPage,
     * and $window pages around currentPage.
     */
    protected function pageRange(int $window): array
    {
        $start = max(1, $this->currentPage - $window);
        $end   = min($this->lastPage, $this->currentPage + $window);

        $pages = range($start, $end);

        if ($start > 1)              array_unshift($pages, 1);
        if ($end < $this->lastPage)  $pages[] = $this->lastPage;

        $pages = array_unique($pages);
        sort($pages);
        return $pages;
    }

    protected function e(mixed $v): string
    {
        return htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
