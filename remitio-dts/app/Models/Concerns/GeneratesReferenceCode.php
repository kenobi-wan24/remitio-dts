<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Auto-generates readable reference codes on create, e.g.
 *   CL-2026-0001   (clients)
 *   RR-2026-0001   (legal cases)
 *   DOC-2026-00001 (documents)
 *
 * The model using this trait must define referenceCodeColumn() and
 * referenceCodePrefix(), and may override referenceCodePadding().
 *
 * NOTE: needs model events — don't seed with the WithoutModelEvents trait.
 */
trait GeneratesReferenceCode
{
    abstract protected function referenceCodeColumn(): string;

    abstract protected function referenceCodePrefix(): string;

    protected function referenceCodePadding(): int
    {
        return 4;
    }

    public static function bootGeneratesReferenceCode(): void
    {
        static::creating(function (self $model) {
            $column = $model->referenceCodeColumn();

            if (blank($model->{$column})) {
                $model->{$column} = static::nextReferenceCode();
            }
        });
    }

    public static function nextReferenceCode(): string
    {
        $model = new static;
        $column = $model->referenceCodeColumn();
        $prefix = $model->referenceCodePrefix().'-'.now()->format('Y').'-';

        $query = static::query();

        // Include soft-deleted rows so a deleted record's code is never reused.
        if (in_array(SoftDeletes::class, class_uses_recursive(static::class), true)) {
            $query->withTrashed();
        }

        $lastCode = $query->where($column, 'like', $prefix.'%')
            ->orderByDesc($column)
            ->value($column);

        $nextNumber = $lastCode ? ((int) substr($lastCode, strlen($prefix))) + 1 : 1;

        return $prefix.str_pad((string) $nextNumber, $model->referenceCodePadding(), '0', STR_PAD_LEFT);
    }
}
