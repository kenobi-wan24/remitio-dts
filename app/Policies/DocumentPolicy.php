<?php

namespace App\Policies;

use App\Models\Document;
use App\Models\User;

class DocumentPolicy
{
    /** R3: open, edit, move, download files of, or add a notarial entry to a document. */
    public function view(User $user, Document $document): bool
    {
        return $document->isVisibleTo($user);
    }

    public function update(User $user, Document $document): bool
    {
        return $document->isVisibleTo($user);
    }

    /** R3: only lawyers decide what is confidential. */
    public function markConfidential(User $user, ?Document $document = null): bool
    {
        return $user->isLawyer();
    }

    public function delete(User $user, Document $document): bool
    {
        return $user->isAdmin();
    }

    public function restore(User $user, Document $document): bool
    {
        return $user->isAdmin();
    }
}
