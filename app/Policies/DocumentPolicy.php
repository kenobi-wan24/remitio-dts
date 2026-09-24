<?php

namespace App\Policies;

use App\Models\Document;
use App\Models\User;

class DocumentPolicy
{
    public function delete(User $user, Document $document): bool
    {
        return $user->isAdmin();
    }

    public function restore(User $user, Document $document): bool
    {
        return $user->isAdmin();
    }
}
