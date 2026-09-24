<?php

namespace App\Policies;

use App\Models\LegalCase;
use App\Models\User;

/**
 * Auto-discovered (App\Models\LegalCase → App\Policies\LegalCasePolicy).
 */
class LegalCasePolicy
{
    public function delete(User $user, LegalCase $legalCase): bool
    {
        return $user->isAdmin();
    }

    public function restore(User $user, LegalCase $legalCase): bool
    {
        return $user->isAdmin();
    }
}
