<?php

namespace App\Actions;

use App\Models\Group;
use App\Models\GroupInvitation;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Fait entrer un utilisateur dans un groupe, par le lien du groupe ou par une
 * invitation nominative — que l'on marque alors acceptée.
 */
final class JoinGroup
{
    /**
     * @return bool Vrai si l'utilisateur vient d'entrer, faux s'il était déjà membre.
     */
    public function handle(Group $group, User $user, ?GroupInvitation $invitation = null): bool
    {
        return DB::transaction(function () use ($group, $user, $invitation): bool {
            $alreadyMember = $group->hasMember($user);

            $group->addMember($user);

            if ($invitation !== null && ! $invitation->isAccepted()) {
                $invitation->forceFill(['accepted_at' => now()])->save();
            }

            return ! $alreadyMember;
        });
    }
}
