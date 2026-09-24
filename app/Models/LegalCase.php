<?php

namespace App\Models;

use App\Enums\CaseStatus;
use App\Enums\CaseType;
use App\Models\Concerns\GeneratesReferenceCode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Named LegalCase because `case` is a reserved word in PHP.
 * Table: legal_cases · URLs: /cases
 */
class LegalCase extends Model
{
    /** @use HasFactory<\Database\Factories\LegalCaseFactory> */
    use GeneratesReferenceCode, HasFactory, SoftDeletes;

    protected $fillable = [
        'docket_number',
        'title',
        'client_id',
        'case_type',
        'court_or_venue',
        'handling_attorney_id',
        'status',
        'date_opened',
        'date_closed',
        'description',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'case_type' => CaseType::class,
            'status' => CaseStatus::class,
            'date_opened' => 'date',
            'date_closed' => 'date',
        ];
    }

    protected function referenceCodeColumn(): string
    {
        return 'case_code';
    }

    protected function referenceCodePrefix(): string
    {
        return 'RR';
    }

    // ── Scopes ──────────────────────────────────────────────

    /** Matches case code, docket no., title — and (Phase 4) the client's name/code. */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (blank($term)) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($term) {
            $like = '%'.trim($term).'%';
            $q->where('case_code', 'like', $like)
                ->orWhere('docket_number', 'like', $like)
                ->orWhere('title', 'like', $like)
                ->orWhereHas('client', fn (Builder $client) => $client->search($term));
        });
    }

    public function scopeOngoing(Builder $query): Builder
    {
        return $query->where('status', '!=', CaseStatus::Closed->value);
    }

    // ── Relationships ───────────────────────────────────────

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function attorney(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handling_attorney_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
