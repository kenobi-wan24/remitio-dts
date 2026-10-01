<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One entry in the Notarial Register, linked to the document it notarizes.
 * A separate record — NOT a step in the document's status history.
 */
class NotarialEntry extends Model
{
    use LogsActivity;

    protected $fillable = [
        'document_id',
        'doc_no',
        'page_no',
        'book_no',
        'series',
        'recorded_by',
    ];

    protected function casts(): array
    {
        return [
            'doc_no' => 'integer',
            'page_no' => 'integer',
            'series' => 'integer',
        ];
    }

    /** $entry->reference → "Doc. No. 45; Page No. 9; Book No. III; Series of 2026" */
    protected function reference(): Attribute
    {
        return Attribute::get(fn () => "Doc. No. {$this->doc_no}; Page No. {$this->page_no}; Book No. {$this->book_no}; Series of {$this->series}");
    }

    public function activityLabel(): string
    {
        return "notarial entry Doc. No. {$this->doc_no}, Book {$this->book_no}, Series {$this->series}";
    }

    /** Find by any combination of register numbers. */
    public function scopeLookup(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['series'] ?? null, fn ($q, $v) => $q->where('series', (int) $v))
            ->when($filters['book'] ?? null, fn ($q, $v) => $q->where('book_no', strtoupper(trim($v))))
            ->when($filters['page'] ?? null, fn ($q, $v) => $q->where('page_no', (int) $v))
            ->when($filters['doc'] ?? null, fn ($q, $v) => $q->where('doc_no', (int) $v));
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class)->withTrashed();
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
