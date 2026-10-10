<?php

namespace App\Models;

use App\Enums\NotarialActType;
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
        'notary_id',
        'doc_no',
        'page_no',
        'book_no',
        'series',
        'notarized_on',
        'act_type',
        'principals',   // [{name, address, evidence}] — copied on the day of notarization
        'witnesses',    // [{name, address}]
        'recorded_by',
    ];

    protected function casts(): array
    {
        return [
            'doc_no' => 'integer',
            'page_no' => 'integer',
            'series' => 'integer',
            'notarized_on' => 'date',
            'act_type' => NotarialActType::class,
            'principals' => 'array',
            'witnesses' => 'array',
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

    /**
     * R2: find entries by a person's name (partial match) — principals and witnesses as recorded
     * in the register, plus the linked document's client and parties, and the instrument title.
     *
     * Principals/witnesses are JSON. MariaDB/SQLite keep "ñ" escaped as "\u00f1" in that text,
     * so the escaped form of the term is searched too.
     */
    public function scopeSearchName(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        // "!" is the LIKE escape character, so backslashes (\u00f1), % and _ are matched literally
        // on MariaDB, MySQL and SQLite alike.
        $like = fn (string $value) => '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($value)).'%';
        $plain = $like($term);
        $escaped = $like(trim(json_encode($term), '"'));
        $jsonLike = fn (Builder $q, string $column) => $q
            ->orWhereRaw("LOWER(CAST({$column} AS CHAR)) LIKE ? ESCAPE '!'", [$plain])
            ->orWhereRaw("LOWER(CAST({$column} AS CHAR)) LIKE ? ESCAPE '!'", [$escaped]);

        return $query->where(function (Builder $q) use ($term, $jsonLike) {
            $jsonLike($q, 'principals');
            $jsonLike($q, 'witnesses');
            $q->orWhereHas('document', fn (Builder $d) => $d
                ->where('title', 'like', '%'.$term.'%')
                ->orWhereHas('client', fn (Builder $c) => $c->search($term))
                ->orWhereHas('parties', fn (Builder $p) => $p->search($term)));
        });
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class)->withTrashed();
    }

    /** The notary public whose register this entry belongs to. */
    public function notary(): BelongsTo
    {
        return $this->belongsTo(User::class, 'notary_id');
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
