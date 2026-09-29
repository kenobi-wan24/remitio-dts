<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Number;

class DocumentAttachment extends Model
{
    protected $fillable = [
        'document_id',
        'version_group_id',
        'version',
        'is_final',
        'version_notes',
        'original_name',
        'path',
        'mime_type',
        'size',
        'uploaded_by',
    ];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'version' => 'integer',
            'is_final' => 'boolean',
        ];
    }

    /** $attachment->human_size → "1.2 MB" */
    protected function humanSize(): Attribute
    {
        return Attribute::get(fn () => Number::fileSize($this->size, precision: 1));
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /** Phase 8: every version of this file (including itself), newest first. */
    public function versions(): HasMany
    {
        return $this->hasMany(self::class, 'version_group_id', 'version_group_id')->orderByDesc('version');
    }
}
