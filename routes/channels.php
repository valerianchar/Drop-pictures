<?php

use App\Models\Group;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

/*
 * Un canal privé par utilisateur — ses propres fichiers (aperçu prêt,
 * restauration) — et un par groupe, réservé à ses membres tant que le groupe
 * n'est pas arrivé à échéance : ce que les autres y déposent apparaît chez
 * chacun sans recharger.
 */
Broadcast::channel('users.{id}', fn (User $user, int $id): bool => $user->id === $id);

Broadcast::channel('groups.{id}', function (User $user, int $id): bool {
    $group = Group::query()->whereKey($id)->first();

    return $group !== null && ! $group->isExpired() && $group->hasMember($user);
});
