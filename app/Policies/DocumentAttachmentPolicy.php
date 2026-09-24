<?php

namespace App\Policies;

use App\Models\DocumentAttachment;
use App\Models\User;

class DocumentAttachmentPolicy
{
    /** Admins, or whoever uploaded the file. */
    public function delete(User $user, DocumentAttachment $attachment): bool
    {
        return $user->isAdmin() || (int) $attachment->uploaded_by === $user->id;
    }
}
