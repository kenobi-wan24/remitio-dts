<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use App\Enums\CivilStatus;
use App\Enums\ClientType;
use App\Enums\Gender;
use App\Models\Concerns\GeneratesReferenceCode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Client extends Model
{
    /** @use HasFactory<\Database\Factories\ClientFactory> */
    use GeneratesReferenceCode, HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'client_type',
        'is_retainer',
        'notarization_only',
        'first_name',
        'middle_name',
        'last_name',
        'suffix',
        'civil_status',
        'gender',
        'spouse_last_name',
        'spouse_first_name',
        'spouse_middle_name',
        'spouse_contact_number',
        'company_name',
        'representative',
        'business_permit_number',
        'tin',
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
            'is_retainer' => 'boolean',
            'notarization_only' => 'boolean',
            'civil_status' => CivilStatus::class,
            'gender' => Gender::class,
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

    /** $client->display_name → "Juan Santos Dela Cruz Jr." or "ABC Trading Corp." */
    protected function displayName(): Attribute
    {
        return Attribute::get(fn () => $this->client_type === ClientType::Company
            ? $this->company_name
            : $this->fullName());
    }

    /** First Middle Last Suffix (blank parts are skipped). */
    public function fullName(): string
    {
        return trim(implode(' ', array_filter([
            $this->first_name, $this->middle_name, $this->last_name, $this->suffix,
        ], fn ($part) => filled($part))));
    }

    /** "Dela Cruz, Juan Santos Jr." (registry order, handy for lists and reports) */
    protected function formalName(): Attribute
    {
        return Attribute::get(function () {
            if ($this->client_type === ClientType::Company) {
                return $this->company_name;
            }

            $given = trim(implode(' ', array_filter([$this->first_name, $this->middle_name], fn ($p) => filled($p))));
            $name = filled($this->last_name) ? $this->last_name.($given !== '' ? ", {$given}" : '') : $given;

            return trim($name.(filled($this->suffix) ? " {$this->suffix}" : ''));
        });
    }

    /** Married clients have spouse details. */
    public function isMarried(): bool
    {
        return $this->civil_status === CivilStatus::Married;
    }

    /** "Maria Reyes Santos" or null when no spouse is recorded. */
    protected function spouseName(): Attribute
    {
        return Attribute::get(function () {
            $name = trim(implode(' ', array_filter([
                $this->spouse_first_name, $this->spouse_middle_name, $this->spouse_last_name,
            ], fn ($part) => filled($part))));

            return $name === '' ? null : $name;
        });
    }

    /** Short label for the client's engagement. */
    public function engagementLabel(): string
    {
        return match (true) {
            $this->notarization_only => 'Walk-in (notarization only)',
            (bool) $this->is_retainer => 'Retainer client',
            default => 'Regular client',
        };
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
                    ->orWhere('middle_name', 'like', $like)
                    ->orWhere('last_name', 'like', $like)
                    ->orWhere('company_name', 'like', $like)
                    ->orWhere('representative', 'like', $like)
                    ->orWhere('tin', 'like', $like)
                    ->orWhere('contact_number', 'like', $like)
                    ->orWhere('email', 'like', $like);
            });
        }

        return $query;
    }

    /** Phase 8: retainer accounts (corporate / cooperative with continuing engagement). */
    public function scopeRetainer(Builder $query, bool $isRetainer = true): Builder
    {
        return $query->where('is_retainer', $isRetainer);
    }

    /** Walk-in clients (notarization only). */
    public function scopeWalkIn(Builder $query, bool $walkIn = true): Builder
    {
        return $query->where('notarization_only', $walkIn);
    }

    /** Clients who can have cases opened for them (everyone except walk-in / notarization-only). */
    public function scopeCanHaveCases(Builder $query): Builder
    {
        return $query->where('notarization_only', false);
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

        if (filled($data['tin'] ?? null)) {
            $checks[] = fn (Builder $q) => $q->orWhere('tin', $data['tin']);
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

    /** 11C: valid government IDs, newest first. */
    public function validIds(): HasMany
    {
        return $this->hasMany(ClientId::class)->latest('id');
    }

    /** 11C: documents where this client is one of the parties (e.g. the Vendee of a deed). */
    public function partyDocuments(): BelongsToMany
    {
        return $this->belongsToMany(Document::class, 'document_parties')->withPivot('role', 'position')->withTimestamps();
    }

    // ── Activity log (Phase 9) ──────────────────────────────

    public function activityLabel(): string
    {
        return "client {$this->client_code} ({$this->display_name})";
    }
}
