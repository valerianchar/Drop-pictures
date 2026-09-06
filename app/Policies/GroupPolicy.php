<?php

namespace App\Policies;

use App\Models\Group;
use App\Models\User;

class GroupPolicy
{
    public function view(User $user, Group $group): bool
    {
        return $group->hasMember($user);
    }

    /** Tout membre peut partager vers le groupe et inviter d'autres personnes. */
    public function share(User $user, Group $group): bool
    {
        return $group->hasMember($user);
    }

    public function invite(User $user, Group $group): bool
    {
        return $group->hasMember($user);
    }

    /** Renommer, régénérer le lien, supprimer : le propriétaire seul. */
    public function update(User $user, Group $group): bool
    {
        return $group->isOwnedBy($user);
    }

    public function delete(User $user, Group $group): bool
    {
        return $group->isOwnedBy($user);
    }

    /** Quitter : tout membre sauf le propriétaire, qui supprime plutôt. */
    public function leave(User $user, Group $group): bool
    {
        return $group->hasMember($user) && ! $group->isOwnedBy($user);
    }
}
