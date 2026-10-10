<?php

namespace App\Policies;

use App\Models\LegalCase;
use App\Models\User;

/**
 * Auto-discovered (App\Models\LegalCase → App\Policies\LegalCasePolicy).
 */
class LegalCasePolicy
{
    public function view(User $user, LegalCase $legalCase): bool
    {
        return $legalCase->isVisibleTo($user);
    }

    public function update(User $user, LegalCase $legalCase): bool
    {
        return $legalCase->isVisibleTo($user);
    }

    /** R3: only lawyers decide what is confidential. */
    public function markConfidential(User $user, ?LegalCase $legalCase = null): bool
    {
        return $user->isLawyer();
    }

    public function delete(User $user, LegalCase $legalCase): bool
    {
        return $user->isAdmin();
    }

    public function restore(User $user, LegalCase $legalCase): bool
    {
        return $user->isAdmin();
    }
}
