<?php

namespace App\Policies;

use App\Models\DocumentAttachment;
use App\Models\User;

class DocumentAttachmentPolicy
{
    /** R3: download only if you may see the document. */
    public function view(User $user, DocumentAttachment $attachment): bool
    {
        return (bool) $attachment->document?->isVisibleTo($user);
    }

    /**
     * Admins can delete any file.
     * Staff can delete only files THEY uploaded, and never a FINAL version.
     */
    public function delete(User $user, DocumentAttachment $attachment): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return ! $attachment->is_final && (int) $attachment->uploaded_by === $user->id;
    }

    /** Only a lawyer approves which version is final. */
    public function markFinal(User $user, DocumentAttachment $attachment): bool
    {
        return $user->isLawyer();
    }
}
