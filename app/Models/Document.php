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
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Document extends Model
{
    /** @use HasFactory<\Database\Factories\DocumentFactory> */
    use GeneratesReferenceCode, HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'title',
        'description',
        'document_type_id',
        'client_id',
        'legal_case_id',
        'status',
        'current_holder_id',
        'physical_location',
        'date_received',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => DocumentStatus::class,
            'date_received' => 'date',
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
            'Received' => $this->movements->last()?->acted_at,
            'Drafted (submitted for review)' => $at(MovementAction::SubmittedForReview),
            'Approved' => $at(MovementAction::Approved),
            'Signed' => $at(MovementAction::Signed),
            'Notarial entry recorded' => $this->notarialEntry?->created_at,
            'Released' => $at(MovementAction::ReleasedToClient),
        ];
    }

    // ── Scopes ──────────────────────────────────────────────

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
