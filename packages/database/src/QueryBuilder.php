<?php

namespace Kyqo\Database;

/**
 * Fluent SQL query builder.
 *
 * All values go through PDO prepared statements.
 *
 * FIX B6: quoteIdentifier() now accepts table.column patterns (e.g. "users.id")
 * needed for JOINs and eager-loading in BelongsToMany.
 * Plain identifiers and qualified table.column are both validated strictly.
 *
 * FIX BTM: added join() and getModel() so BelongsToMany::buildJoinQuery() works.
 */
class QueryBuilder
{
    protected Connection $connection;
    protected string     $table;
    protected array      $wheres    = [];
    protected array      $bindings  = [];
    protected array      $columns   = ['*'];
    protected ?int       $limit     = null;
    protected ?int       $offset    = null;
    protected array      $orders    = [];
    protected array      $joins     = [];

    /** @var string|null  FQCN of the model that owns this query (set by ModelQueryBuilder) */
    protected ?string $modelClass = null;

    public function __construct(Connection $connection, string $table)
    {
        $this->connection = $connection;
        $this->table      = $table;
    }

    // ── Column selection ────────────────────────────────────────────────────

    public function select(array|string $columns): static
    {
        $this->columns = is_array($columns) ? $columns : func_get_args();
        return $this;
    }

    // ── Conditions ──────────────────────────────────────────────────────────

    public function where(string $column, mixed $operatorOrValue, mixed $value = null): static
    {
        if ($value === null) {
            $operator = '=';
            $val      = $operatorOrValue;
        } else {
            $operator = $this->sanitizeOperator($operatorOrValue);
            $val      = $value;
        }

        $this->wheres[]   = $this->quoteIdentifier($column) . " {$operator} ?";
        $this->bindings[] = $val;
        return $this;
    }

    public function whereNull(string $column): static
    {
        $this->wheres[] = $this->quoteIdentifier($column) . ' IS NULL';
        return $this;
    }

    public function whereNotNull(string $column): static
    {
        $this->wheres[] = $this->quoteIdentifier($column) . ' IS NOT NULL';
        return $this;
    }

    public function whereIn(string $column, array $values): static
    {
        if (empty($values)) {
            $this->wheres[] = '1 = 0';
            return $this;
        }
        $placeholders   = implode(', ', array_fill(0, count($values), '?'));
        $this->wheres[] = $this->quoteIdentifier($column) . " IN ({$placeholders})";
        array_push($this->bindings, ...$values);
        return $this;
    }

    public function whereNotIn(string $column, array $values): static
    {
        if (empty($values)) return $this;
        $placeholders   = implode(', ', array_fill(0, count($values), '?'));
        $this->wheres[] = $this->quoteIdentifier($column) . " NOT IN ({$placeholders})";
        array_push($this->bindings, ...$values);
        return $this;
    }

    // ── JOIN ─────────────────────────────────────────────────────────────────

    /**
     * FIX BTM: Add an INNER JOIN clause.
     *
     * @param string $table      Join table name
     * @param string $first      Left-hand column  (e.g. "users.id")
     * @param string $operator   Join operator     (e.g. "=")
     * @param string $second     Right-hand column (e.g. "role_user.user_id")
     */
    public function join(string $table, string $first, string $operator, string $second): static
    {
        $op            = $this->sanitizeOperator($operator);
        $this->joins[] = 'INNER JOIN '
            . $this->quoteIdentifier($table)
            . ' ON '
            . $this->quoteIdentifier($first)
            . " {$op} "
            . $this->quoteIdentifier($second);
        return $this;
    }

    public function leftJoin(string $table, string $first, string $operator, string $second): static
    {
        $op            = $this->sanitizeOperator($operator);
        $this->joins[] = 'LEFT JOIN '
            . $this->quoteIdentifier($table)
            . ' ON '
            . $this->quoteIdentifier($first)
            . " {$op} "
            . $this->quoteIdentifier($second);
        return $this;
    }

    // ── Ordering / limiting ─────────────────────────────────────────────────

    public function orderBy(string $column, string $direction = 'asc'): static
    {
        $direction      = strtolower($direction) === 'desc' ? 'DESC' : 'ASC';
        $this->orders[] = $this->quoteIdentifier($column) . ' ' . $direction;
        return $this;
    }

    public function latest(string $column = 'created_at'): static { return $this->orderBy($column, 'desc'); }
    public function oldest(string $column = 'created_at'): static { return $this->orderBy($column, 'asc'); }

    public function limit(int $value): static  { $this->limit = max(0, $value); return $this; }
    public function offset(int $value): static { $this->offset = max(0, $value); return $this; }
    public function skip(int $value): static   { return $this->offset($value); }
    public function take(int $value): static   { return $this->limit($value); }

    // ── Results ─────────────────────────────────────────────────────────────

    public function get(): array
    {
        return $this->connection->select($this->toSql(), $this->bindings);
    }

    public function first(): ?array
    {
        return $this->limit(1)->connection->selectOne($this->toSql(), $this->bindings);
    }

    public function find(mixed $id, string $primaryKey = 'id'): ?array
    {
        return $this->where($primaryKey, $id)->first();
    }

    public function count(): int
    {
        $sql  = 'SELECT COUNT(*) as aggregate FROM ' . $this->quoteIdentifier($this->table);
        $sql .= $this->buildJoinClause();
        $sql .= $this->buildWhereClause();
        $row  = $this->connection->selectOne($sql, $this->bindings);
        return (int) ($row['aggregate'] ?? 0);
    }

    public function exists(): bool { return $this->count() > 0; }

    public function pluck(string $column): array
    {
        $rows = $this->select($column)->get();
        return array_column($rows, $column);
    }

    public function value(string $column): mixed
    {
        $row = $this->select($column)->first();
        return $row[$column] ?? null;
    }

    // ── Writes ──────────────────────────────────────────────────────────────

    public function insert(array $data): bool
    {
        $columns      = array_keys($data);
        $quoted       = array_map([$this, 'quoteIdentifier'], $columns);
        $placeholders = array_fill(0, count($data), '?');

        $sql = 'INSERT INTO ' . $this->quoteIdentifier($this->table)
             . ' (' . implode(', ', $quoted) . ')'
             . ' VALUES (' . implode(', ', $placeholders) . ')';

        return $this->connection->insert($sql, array_values($data));
    }

    public function insertGetId(array $data): mixed
    {
        $this->insert($data);
        return $this->connection->lastInsertId();
    }

    public function update(array $data): int
    {
        $sets = [];
        foreach (array_keys($data) as $col) {
            $sets[] = $this->quoteIdentifier($col) . ' = ?';
        }

        $sql      = 'UPDATE ' . $this->quoteIdentifier($this->table)
                  . ' SET ' . implode(', ', $sets)
                  . $this->buildWhereClause();
        $bindings = array_merge(array_values($data), $this->bindings);

        return $this->connection->update($sql, $bindings);
    }

    public function delete(): int
    {
        $sql = 'DELETE FROM ' . $this->quoteIdentifier($this->table)
             . $this->buildWhereClause();
        return $this->connection->delete($sql, $this->bindings);
    }

    // ── Model binding ────────────────────────────────────────────────────────

    /**
     * FIX BTM: Store the model class so BelongsToMany can call getModel()->getTable().
     */
    public function setModel(string $modelClass): static
    {
        $this->modelClass = $modelClass;
        return $this;
    }

    /**
     * FIX BTM: Return a fresh model instance so relation classes can call getTable() etc.
     *
     * @throws \RuntimeException if no model class was bound to this builder.
     */
    public function getModel(): \Kyqo\Database\Orm\Model
    {
        if ($this->modelClass === null) {
            throw new \RuntimeException(
                'QueryBuilder::getModel() called but no model class is bound. '
                . 'Use setModel(MyModel::class) or go through ModelQueryBuilder.'
            );
        }
        return new $this->modelClass();
    }

    // ── SQL generation ───────────────────────────────────────────────────────

    public function toSql(): string
    {
        $cols = implode(', ', array_map(function (string $c) {
            return $c === '*' ? '*' : $this->quoteIdentifier($c);
        }, $this->columns));

        $sql  = "SELECT {$cols} FROM " . $this->quoteIdentifier($this->table);
        $sql .= $this->buildJoinClause();
        $sql .= $this->buildWhereClause();

        if (!empty($this->orders)) {
            $sql .= ' ORDER BY ' . implode(', ', $this->orders);
        }
        if ($this->limit !== null) {
            $sql .= ' LIMIT ' . $this->limit;
        }
        if ($this->offset !== null) {
            $sql .= ' OFFSET ' . $this->offset;
        }

        return $sql;
    }

    public function getConnection(): Connection { return $this->connection; }

    // ── Helpers ──────────────────────────────────────────────────────────────

    protected function buildJoinClause(): string
    {
        if (empty($this->joins)) return '';
        return ' ' . implode(' ', $this->joins);
    }

    protected function buildWhereClause(): string
    {
        if (empty($this->wheres)) return '';
        return ' WHERE ' . implode(' AND ', $this->wheres);
    }

    /**
     * FIX B6 – Accept both plain identifiers AND qualified table.column.
     *
     * Valid:  id, user_id, created_at, users.id, posts.user_id
     * Invalid: anything with spaces, semicolons, quotes, etc.
     */
    protected function quoteIdentifier(string $name): string
    {
        // Qualified: table.column
        if (str_contains($name, '.')) {
            $parts = explode('.', $name, 2);
            return $this->quotePart($parts[0]) . '.' . $this->quotePart($parts[1]);
        }

        return $this->quotePart($name);
    }

    protected function quotePart(string $part): string
    {
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $part)) {
            throw new \InvalidArgumentException("Invalid SQL identifier part: [{$part}]");
        }
        $driver = $this->connection->getDriver();
        if ($driver === 'pgsql') {
            return '"' . str_replace('"', '""', $part) . '"';
        }
        return '`' . str_replace('`', '``', $part) . '`';
    }

    protected function sanitizeOperator(mixed $op): string
    {
        $allowed = ['=', '!=', '<>', '<', '<=', '>', '>=', 'LIKE', 'NOT LIKE', 'ILIKE'];
        $op      = strtoupper((string) $op);
        if (!in_array($op, $allowed, true)) {
            throw new \InvalidArgumentException("Invalid SQL operator: [{$op}]");
        }
        return $op;
    }
}
