<?php

namespace App\Actions;

use App\Enums\GroupRole;
use App\Models\Group;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Crée un groupe et y installe son propriétaire comme premier membre, avec un
 * lien d'invitation prêt à être copié.
 */
final class CreateGroup
{
    public function handle(User $owner, string $name): Group
    {
        return DB::transaction(function () use ($owner, $name): Group {
            $group = $owner->ownedGroups()->create([
                'name' => $name,
                'invite_token' => Str::random(32),
            ]);

            $group->addMember($owner, GroupRole::Proprietaire);

            return $group;
        });
    }
}
