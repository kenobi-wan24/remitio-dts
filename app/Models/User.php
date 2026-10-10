<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use App\Enums\UserRole;
// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, LogsActivity, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'is_active',
        'notarial_commission_no',
        'commission_expires_on',
        'notary_office_address',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'is_active' => 'boolean',
            'commission_expires_on' => 'date',
        ];
    }

    // ── Helpers ─────────────────────────────────────────────

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    /** R3: lawyer duties (approve, revise, sign, notarize, mark confidential). Administrator = the Lawyer/Owner. */
    public function isLawyer(): bool
    {
        return in_array($this->role, [UserRole::Admin, UserRole::Lawyer], true);
    }

    public function isStaff(): bool
    {
        return $this->role === UserRole::Staff;
    }

    // ── Scopes ──────────────────────────────────────────────

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** Admins act as the firm's attorneys (for the "handling attorney" dropdown). */
    public function scopeAttorneys(Builder $query): Builder
    {
        return $query->whereIn('role', UserRole::lawyerValues());
    }

    // ── Relationships ───────────────────────────────────────

    public function handledCases(): HasMany
    {
        return $this->hasMany(LegalCase::class, 'handling_attorney_id');
    }

    public function heldDocuments(): HasMany
    {
        return $this->hasMany(Document::class, 'current_holder_id');
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
    }

    // ── Activity log (Phase 9) ──────────────────────────────

    public function activityLabel(): string
    {
        return "user account {$this->name} ({$this->email})";
    }

    /** Activation is logged explicitly as activated/deactivated. */
    protected function activityIgnoredAttributes(): array
    {
        return ['is_active', 'email_verified_at'];
    }
}
