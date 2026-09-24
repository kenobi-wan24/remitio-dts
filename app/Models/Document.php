<?php

namespace App\Models;

use App\Enums\DocumentStatus;
use App\Models\Concerns\GeneratesReferenceCode;
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
    use GeneratesReferenceCode, HasFactory, SoftDeletes;

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
        'due_date',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => DocumentStatus::class,
            'date_received' => 'date',
            'due_date' => 'date',
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

    /** $document->is_overdue */
    protected function isOverdue(): Attribute
    {
        return Attribute::get(fn () => $this->due_date !== null
            && ! $this->status->isFinal()
            && $this->due_date->lt(today()));
    }

    /** $document->is_due_soon → due within the next 3 days */
    protected function isDueSoon(): Attribute
    {
        return Attribute::get(fn () => $this->due_date !== null
            && ! $this->status->isFinal()
            && $this->due_date->betweenIncluded(today(), today()->addDays(3)));
    }

    // ── Scopes ──────────────────────────────────────────────

    /** Still being worked on in the office (not released/archived). */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNotIn('status', DocumentStatus::finalValues());
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query->open()
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', today());
    }

    public function scopeDueWithin(Builder $query, int $days): Builder
    {
        return $query->open()
            ->whereBetween('due_date', [today()->toDateString(), today()->addDays($days)->toDateString()]);
    }

    public function scopeHeldBy(Builder $query, User|int $user): Builder
    {
        return $query->where('current_holder_id', $user instanceof User ? $user->id : $user);
    }

    /** Tracking code, title, location — and (Phase 5) client name/code and case code/docket. */
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
}
