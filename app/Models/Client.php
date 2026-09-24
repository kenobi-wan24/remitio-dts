<?php

namespace App\Models;

use App\Enums\ClientType;
use App\Models\Concerns\GeneratesReferenceCode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Client extends Model
{
    /** @use HasFactory<\Database\Factories\ClientFactory> */
    use GeneratesReferenceCode, HasFactory, SoftDeletes;

    protected $fillable = [
        'client_type',
        'first_name',
        'last_name',
        'company_name',
        'contact_number',
        'email',
        'address',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'client_type' => ClientType::class,
        ];
    }

    protected function referenceCodeColumn(): string
    {
        return 'client_code';
    }

    protected function referenceCodePrefix(): string
    {
        return 'CL';
    }

    // ── Accessors ───────────────────────────────────────────

    /** $client->display_name → "Juan Dela Cruz" or "ABC Trading Corp." */
    protected function displayName(): Attribute
    {
        return Attribute::get(fn () => $this->client_type === ClientType::Company
            ? $this->company_name
            : trim("{$this->first_name} {$this->last_name}"));
    }

    // ── Scopes ──────────────────────────────────────────────

    /** Each word must match at least one column, so "juan cruz" finds "Juan Dela Cruz". */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (blank($term)) {
            return $query;
        }

        foreach (preg_split('/\s+/', trim($term)) as $word) {
            $query->where(function (Builder $q) use ($word) {
                $like = "%{$word}%";
                $q->where('client_code', 'like', $like)
                    ->orWhere('first_name', 'like', $like)
                    ->orWhere('last_name', 'like', $like)
                    ->orWhere('company_name', 'like', $like)
                    ->orWhere('contact_number', 'like', $like)
                    ->orWhere('email', 'like', $like);
            });
        }

        return $query;
    }

    /**
     * NEW (Phase 3): clients that look like the same person/company —
     * same full name, same company name, same contact number, or same email.
     */
    public function scopePossibleDuplicatesOf(Builder $query, array $data, ?int $ignoreId = null): Builder
    {
        $checks = [];

        if (filled($data['company_name'] ?? null)) {
            $checks[] = fn (Builder $q) => $q->orWhere('company_name', $data['company_name']);
        }

        if (filled($data['first_name'] ?? null) && filled($data['last_name'] ?? null)) {
            $checks[] = fn (Builder $q) => $q->orWhere(fn (Builder $name) => $name
                ->where('first_name', $data['first_name'])
                ->where('last_name', $data['last_name']));
        }

        if (filled($data['contact_number'] ?? null)) {
            $checks[] = fn (Builder $q) => $q->orWhere('contact_number', $data['contact_number']);
        }

        if (filled($data['email'] ?? null)) {
            $checks[] = fn (Builder $q) => $q->orWhere('email', $data['email']);
        }

        if ($checks === []) {
            return $query->whereRaw('1 = 0'); // nothing to compare → no duplicates
        }

        return $query
            ->when($ignoreId, fn (Builder $q) => $q->whereKeyNot($ignoreId))
            ->where(function (Builder $q) use ($checks) {
                foreach ($checks as $check) {
                    $check($q);
                }
            });
    }

    // ── Relationships ───────────────────────────────────────

    public function cases(): HasMany
    {
        return $this->hasMany(LegalCase::class);
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
