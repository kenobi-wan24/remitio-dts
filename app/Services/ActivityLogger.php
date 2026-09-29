<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Writes one row to activity_logs.
 * Does nothing when no one is logged in (e.g. while seeding from the console),
 * so demo data doesn't flood the log.
 */
class ActivityLogger
{
    public static function log(string $action, ?Model $subject, string $description, array $properties = [], ?User $user = null): void
    {
        $user ??= auth()->user();

        if (! $user) {
            return;
        }

        ActivityLog::create([
            'user_id' => $user->id,
            'action' => $action,
            'subject_type' => $subject ? $subject->getMorphClass() : null,
            'subject_id' => $subject?->getKey(),
            'description' => mb_strimwidth($description, 0, 255, '…'),
            'properties' => $properties ?: null,
            'ip_address' => app()->runningInConsole() ? null : request()->ip(),
        ]);
    }
}
