<?php

namespace Kyqo\Database\Orm\Concerns;

/**
 * SoftDeletes trait.
 *
 * Add to a model to enable soft-deletion via a `deleted_at` timestamp.
 *
 * Usage:
 *   class Post extends Model
 *   {
 *       use SoftDeletes;
 *   }
 *
 * The `delete()` method will set `deleted_at` instead of removing the row.
 * Call `forceDelete()` to permanently remove.
 * Call `restore()` to undelete.
 * Scoped queries automatically exclude soft-deleted records.
 */
trait SoftDeletes
{
    protected string $deletedAtColumn = 'deleted_at';

    public function delete(): bool
    {
        $col  = $this->getDeletedAtColumn();
        $now  = date('Y-m-d H:i:s');
        $this->setAttribute($col, $now);
        return $this->save();
    }

    public function forceDelete(): bool
    {
        return parent::delete();
    }

    public function restore(): bool
    {
        $this->setAttribute($this->getDeletedAtColumn(), null);
        return $this->save();
    }

    public function trashed(): bool
    {
        return $this->{$this->getDeletedAtColumn()} !== null;
    }

    public function getDeletedAtColumn(): string
    {
        return $this->deletedAtColumn;
    }

    /** Apply soft-delete scope: WHERE deleted_at IS NULL */
    public function newQuery(): \Kyqo\Database\Orm\ModelQueryBuilder
    {
        return parent::newQuery()->whereNull($this->getDeletedAtColumn());
    }

    public static function withTrashed(): \Kyqo\Database\Orm\ModelQueryBuilder
    {
        return (new static())->newQueryWithoutScopes();
    }

    public static function onlyTrashed(): \Kyqo\Database\Orm\ModelQueryBuilder
    {
        return (new static())->newQueryWithoutScopes()
            ->whereNotNull((new static())->getDeletedAtColumn());
    }
}
