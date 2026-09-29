<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ActivityLog extends Model
{
    public const ACTIONS = [
        'created' => ['Created', 'green'],
        'updated' => ['Updated', 'blue'],
        'deleted' => ['Deleted', 'red'],
        'restored' => ['Restored', 'indigo'],
        'moved' => ['Moved', 'purple'],
        'uploaded' => ['Uploaded file', 'indigo'],
        'finalized' => ['Marked final', 'green'],
        'file_deleted' => ['Deleted file', 'red'],
        'login' => ['Logged in', 'gray'],
        'logout' => ['Logged out', 'gray'],
        'password_reset' => ['Password reset', 'amber'],
        'activated' => ['Activated', 'green'],
        'deactivated' => ['Deactivated', 'amber'],
    ];

    protected $fillable = [
        'user_id',
        'action',
        'subject_type',
        'subject_id',
        'description',
        'properties',
        'ip_address',
    ];

    protected function casts(): array
    {
        return [
            'properties' => 'array',
        ];
    }

    public function actionLabel(): string
    {
        return self::ACTIONS[$this->action][0] ?? ucfirst(str_replace('_', ' ', $this->action));
    }

    public function actionColor(): string
    {
        return self::ACTIONS[$this->action][1] ?? 'gray';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** The record the action was done to (Client, LegalCase, Document...). */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
