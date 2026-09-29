<?php

namespace App\Models\Concerns;

use App\Services\ActivityLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Arr;

/**
 * Automatically logs created / updated (with old → new values) / deleted / restored.
 * The model must define activityLabel(), e.g. "client CL-2026-0001 (Juan Dela Cruz)".
 */
trait LogsActivity
{
    abstract public function activityLabel(): string;

    /** Columns whose changes should NOT be logged as "updated". */
    protected function activityIgnoredAttributes(): array
    {
        return [];
    }

    public static function bootLogsActivity(): void
    {
        static::created(function (Model $model) {
            ActivityLogger::log('created', $model, 'Created '.$model->activityLabel());
        });

        static::updated(function (Model $model) {
            $ignored = ['updated_at', 'deleted_at', 'remember_token', 'password', ...$model->activityIgnoredAttributes()];
            $changed = Arr::except($model->getChanges(), $ignored);

            if ($changed === []) {
                return;
            }

            ActivityLogger::log(
                'updated',
                $model,
                'Updated '.$model->activityLabel().' ('.implode(', ', array_keys($changed)).')',
                ['old' => Arr::only($model->getRawOriginal(), array_keys($changed)), 'new' => $changed],
            );
        });

        static::deleted(function (Model $model) {
            ActivityLogger::log('deleted', $model, 'Deleted '.$model->activityLabel());
        });

        if (in_array(SoftDeletes::class, class_uses_recursive(static::class), true)) {
            static::restored(function (Model $model) {
                ActivityLogger::log('restored', $model, 'Restored '.$model->activityLabel());
            });
        }
    }
}
