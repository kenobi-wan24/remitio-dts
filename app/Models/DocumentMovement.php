<?php

namespace App\Models;

use App\Enums\DocumentStatus;
use App\Enums\MovementAction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only: the app creates movements but never updates or deletes them.
 */
class DocumentMovement extends Model
{
    protected $fillable = [
        'document_id',
        'action',
        'from_user_id',
        'to_user_id',
        'from_status',
        'to_status',
        'location',
        'remarks',
        'acted_by',
        'acted_at',
    ];

    protected function casts(): array
    {
        return [
            'action' => MovementAction::class,
            'from_status' => DocumentStatus::class,
            'to_status' => DocumentStatus::class,
            'acted_at' => 'datetime',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class)->withTrashed();
    }

    public function fromUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'from_user_id');
    }

    public function toUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'to_user_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acted_by');
    }
}
