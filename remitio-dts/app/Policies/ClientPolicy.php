<?php

namespace App\Policies;

use App\Models\Client;
use App\Models\User;

/**
 * Auto-discovered by Laravel (App\Models\Client → App\Policies\ClientPolicy).
 * Viewing/creating/editing is open to all logged-in users; only admins delete & restore.
 */
class ClientPolicy
{
    public function delete(User $user, Client $client): bool
    {
        return $user->isAdmin();
    }

    public function restore(User $user, Client $client): bool
    {
        return $user->isAdmin();
    }
}
