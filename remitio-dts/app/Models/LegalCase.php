<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
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
    use GeneratesReferenceCode, HasFactory, LogsActivity, SoftDeletes;

    protected $attributes = ['urgency' => 'normal'];

    protected $fillable = [
        'docket_number',
        'title',
        'client_id',
        'case_type',
        'court_or_venue',
        'handling_attorney_id',
        'status',
        'is_confidential',
        'urgency',
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
            'is_confidential' => 'boolean',
            'urgency' => \App\Enums\Urgency::class,
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
    /**
     * R3 — the cases this user may see.
     *   Administrator: all.
     *   Others: cases that are not confidential (in "open" mode), plus cases assigned to them:
     *   handling lawyer, whoever recorded the case, or anyone assigned to one of its documents.
     */
    public function scopeVisibleTo(Builder $query, ?\App\Models\User $user): Builder
    {
        if ($user?->isAdmin()) {
            return $query;
        }

        $id = $user?->id ?? 0;

        return $query->where(function (Builder $q) use ($id) {
            if (config('dts.access_mode') !== 'assigned') {
                $q->where('legal_cases.is_confidential', false);
            }

            $q->orWhere('legal_cases.handling_attorney_id', $id)
                ->orWhere('legal_cases.created_by', $id)
                ->orWhereIn('legal_cases.id', Document::withTrashed()->assignedTo($id)->whereNotNull('legal_case_id')->select('legal_case_id'));
        });
    }

    public function isVisibleTo(?\App\Models\User $user): bool
    {
        return $user?->isAdmin() || static::withTrashed()->whereKey($this->getKey())->visibleTo($user)->exists();
    }

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

    // ── Activity log (Phase 9) ──────────────────────────────

    public function activityLabel(): string
    {
        return "case {$this->case_code}";
    }
}
