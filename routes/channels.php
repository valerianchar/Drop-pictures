<?php

use App\Models\Group;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

/*
 * Un canal privé par utilisateur — ses propres fichiers (aperçu prêt,
 * restauration) — et un par groupe, réservé à ses membres : ce que les autres
 * y déposent apparaît chez chacun sans recharger.
 */
Broadcast::channel('users.{id}', fn (User $user, int $id): bool => $user->id === $id);

Broadcast::channel('groups.{id}', fn (User $user, int $id): bool => Group::query()
    ->whereKey($id)
    ->first()
    ?->hasMember($user) ?? false);
