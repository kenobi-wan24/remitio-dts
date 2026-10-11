<?php

namespace App\Models;

use App\Enums\DocumentStatus;
use App\Enums\MovementAction;
use App\Models\Concerns\GeneratesReferenceCode;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Document extends Model
{
    /** @use HasFactory<\Database\Factories\DocumentFactory> */
    use GeneratesReferenceCode, HasFactory, LogsActivity, SoftDeletes;

    /** New records start Normal (so the attribute is set before the first save/refresh). */
    protected $attributes = ['urgency' => 'normal'];

    protected $fillable = [
        'title',
        'description',
        'document_type_id',
        'client_id',
        'legal_case_id',
        'status',
        'is_confidential',
        'urgency',
        'deadline',
        'on_hold_since',
        'on_hold_reason',
        'current_holder_id',
        'physical_location',
        'out_since',
        'out_destination',
        'out_purpose',
        'out_carried_by',
        'out_expected_back',
        'date_received',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => DocumentStatus::class,
            'date_received' => 'date',
            'out_since' => 'datetime',
            'is_confidential' => 'boolean',
            'out_expected_back' => 'date',
            'urgency' => \App\Enums\Urgency::class,
            'deadline' => 'date',
            'on_hold_since' => 'datetime',
        ];
    }

    protected function referenceCodeColumn(): string
    {
        return 'tracking_code';
    }

    protected function referenceCodePrefix(): string
    {
        return 'DOC';
    }

    protected function referenceCodePadding(): int
    {
        return 5;
    }

    // ── Accessors ───────────────────────────────────────────

    /**
     * $document->notarial_reference
     * → "Doc. No. 45; Page No. 9; Book No. III; Series of 2026" (null if not notarized).
     * Workflow v2: comes from the linked Notarial Register entry.
     */
    protected function notarialReference(): Attribute
    {
        return Attribute::get(fn () => $this->notarialEntry?->reference);
    }

    /**
     * Workflow v2: key dates taken from the tracking history (latest occurrence of each step).
     * Needs `movements` loaded; returns [label => Carbon|null].
     */
    public function keyDates(): array
    {
        $at = fn (MovementAction ...$actions) => $this->movements
            ->first(fn ($m) => in_array($m->action, $actions, true))?->acted_at;

        return [
            'Request In' => $this->movements->last()?->acted_at,
            'Drafted (submitted for review)' => $at(MovementAction::SubmittedForReview),
            'Approved' => $at(MovementAction::Approved),
            'Finalized' => $at(MovementAction::Finalized),
            'Signed & Sealed' => $at(MovementAction::Signed),
            'Notarial entry recorded' => $this->notarialEntry?->created_at,
            'Released' => $at(MovementAction::ReleasedToClient),
        ];
    }

    /** R2: is the physical copy outside the office right now? */
    public function isOutOfOffice(): bool
    {
        return $this->out_since !== null;
    }

    /** R2: out of office past the expected return date. */
    public function isOutOverdue(): bool
    {
        return $this->isOutOfOffice() && (bool) $this->out_expected_back?->lt(today());
    }

    // ── Scopes ──────────────────────────────────────────────

    /**
     * Adds `last_moved_at` = time of the latest tracking step (how long it has been waiting).
     * Uses the history, not updated_at, so editing a title doesn't reset the waiting time.
     */
    public function scopeWithLastMovedAt(Builder $query): Builder
    {
        return $query->withMax('movements as last_moved_at', 'acted_at');
    }

    /** Waiting since: the latest tracking step (falls back to when it was recorded). */
    public function waitingSince(): \Illuminate\Support\Carbon
    {
        return $this->last_moved_at ? \Illuminate\Support\Carbon::parse($this->last_moved_at) : $this->created_at;
    }

    /**
     * R3 — documents ASSIGNED to a user: they hold it now, recorded it, handled it in the
     * tracking history, or are the handling lawyer of its case.
     */
    public function scopeAssignedTo(Builder $query, User|int $user): Builder
    {
        $id = $user instanceof User ? $user->id : $user;

        return $query->where(fn (Builder $q) => $q
            ->where('documents.current_holder_id', $id)
            ->orWhere('documents.created_by', $id)
            ->orWhereIn('documents.id', DocumentMovement::query()
                ->select('document_id')
                ->where(fn ($m) => $m->where('acted_by', $id)->orWhere('to_user_id', $id)->orWhere('from_user_id', $id)))
            ->orWhereIn('documents.legal_case_id', LegalCase::withTrashed()->select('id')->where('handling_attorney_id', $id)));
    }

    /**
     * R3 — the documents this user may see.
     *   Administrator: all.
     *   Others: documents not marked confidential (and not in a confidential case) in "open" mode,
     *   plus documents assigned to them.
     */
    public function scopeVisibleTo(Builder $query, ?User $user): Builder
    {
        if ($user?->isAdmin()) {
            return $query;
        }

        $id = $user?->id ?? 0;

        return $query->where(function (Builder $q) use ($id) {
            if (config('dts.access_mode') !== 'assigned') {
                $q->where(fn (Builder $open) => $open
                    ->where('documents.is_confidential', false)
                    ->where(fn (Builder $c) => $c
                        ->whereNull('documents.legal_case_id')
                        ->orWhereIn('documents.legal_case_id', LegalCase::withTrashed()->select('id')->where('is_confidential', false))));
            }

            $q->orWhere(fn (Builder $mine) => $mine->assignedTo($id));
        });
    }

    public function isVisibleTo(?User $user): bool
    {
        return $user?->isAdmin() || static::withTrashed()->whereKey($this->getKey())->visibleTo($user)->exists();
    }

    // ── R4: urgency & priority ─────────────────────────────

    public function isOnHold(): bool
    {
        return $this->on_hold_since !== null;
    }

    /** The deadline, only when deadlines are turned on (DTS_DEADLINES=true). */
    public function activeDeadline(): ?\Illuminate\Support\Carbon
    {
        return config('dts.deadlines') ? $this->deadline : null;
    }

    public function isOverdue(): bool
    {
        return (bool) $this->activeDeadline()?->lt(today()) && ! $this->status->isFinal();
    }

    /** Which priority group this document falls in (rules on PriorityGroup). */
    public function priorityGroup(): \App\Enums\PriorityGroup
    {
        $deadline = $this->activeDeadline();

        return match (true) {
            $this->isOnHold() => \App\Enums\PriorityGroup::OnHold,
            $this->urgency === \App\Enums\Urgency::Critical,
            $deadline !== null && $deadline->lte(today()) => \App\Enums\PriorityGroup::Immediate,
            $deadline !== null && $deadline->lte(today()->addDays(\App\Enums\PriorityGroup::DUE_SOON_DAYS)) => \App\Enums\PriorityGroup::DueSoon,
            $this->urgency === \App\Enums\Urgency::High => \App\Enums\PriorityGroup::High,
            default => \App\Enums\PriorityGroup::Routine,
        };
    }

    /**
     * Sorts by priority: not on hold first → higher urgency → earlier deadline (if on) → waiting longest.
     * Adds `last_moved_at`.
     */
    public function scopeByPriority(Builder $query): Builder
    {
        $query->withLastMovedAt()
            ->orderByRaw('CASE WHEN documents.on_hold_since IS NULL THEN 0 ELSE 1 END')
            ->orderByRaw(\App\Enums\Urgency::rankSql('documents.urgency'));

        if (config('dts.deadlines')) {
            $query->orderByRaw('CASE WHEN documents.deadline IS NULL THEN 1 ELSE 0 END')->orderBy('documents.deadline');
        }

        return $query->orderBy('last_moved_at')->orderBy('documents.id');
    }

    /** Confidential itself, or because its case is. */
    public function isConfidential(): bool
    {
        return $this->is_confidential || (bool) $this->legalCase?->is_confidential;
    }

    /** R2: physical copy currently outside the office. */
    public function scopeOutOfOffice(Builder $query): Builder
    {
        return $query->whereNotNull('out_since');
    }

    /** Still being worked on in the office (not released/archived). */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNotIn('status', DocumentStatus::finalValues());
    }

    public function scopeHeldBy(Builder $query, User|int $user): Builder
    {
        return $query->where('current_holder_id', $user instanceof User ? $user->id : $user);
    }

    /** Tracking code, title, location, client name/code, case code/docket. */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (blank($term)) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($term) {
            $like = '%'.trim($term).'%';
            $q->where('tracking_code', 'like', $like)
                ->orWhere('title', 'like', $like)
                ->orWhere('physical_location', 'like', $like)
                ->orWhereHas('client', fn (Builder $client) => $client->search($term))
                ->orWhereHas('parties', fn (Builder $party) => $party->search($term))
                ->orWhereHas('legalCase', fn (Builder $case) => $case
                    ->where('case_code', 'like', $like)
                    ->orWhere('docket_number', 'like', $like));
        });
    }

    /** Workflow v2: notarial lookup goes through the linked register entry. */
    public function scopeNotarialLookup(Builder $query, array $filters): Builder
    {
        $filters = array_filter($filters, fn ($v) => filled($v));

        return $filters
            ? $query->whereHas('notarialEntry', fn (Builder $e) => $e->lookup($filters))
            : $query;
    }

    // ── Relationships ───────────────────────────────────────

    public function documentType(): BelongsTo
    {
        return $this->belongsTo(DocumentType::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function legalCase(): BelongsTo
    {
        return $this->belongsTo(LegalCase::class);
    }

    public function currentHolder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'current_holder_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Newest first — ready for the timeline. */
    public function movements(): HasMany
    {
        return $this->hasMany(DocumentMovement::class)
            ->orderByDesc('acted_at')
            ->orderByDesc('id');
    }

    public function latestMovement(): HasOne
    {
        return $this->hasOne(DocumentMovement::class)->latestOfMany('acted_at');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(DocumentAttachment::class)->latest();
    }

    /** 11C: the parties to the document (each a client), in order. */
    public function parties(): BelongsToMany
    {
        return $this->belongsToMany(Client::class, 'document_parties')
            ->withPivot('role', 'position')
            ->withTimestamps()
            ->orderByPivot('position');
    }

    /** Workflow v2: the Notarial Register entry for this document (if notarized). */
    public function notarialEntry(): HasOne
    {
        return $this->hasOne(NotarialEntry::class);
    }

    // ── Activity log ────────────────────────────────────────

    public function activityLabel(): string
    {
        return "document {$this->tracking_code}";
    }

    /** Status/holder/location changes are logged as movements instead. */
    protected function activityIgnoredAttributes(): array
    {
        return ['status', 'current_holder_id', 'physical_location'];
    }
}
