<?php

namespace App\Actions;

use App\Enums\GroupLifetime;
use App\Enums\GroupRole;
use App\Models\Group;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Crée un groupe et y installe son propriétaire comme premier membre, avec un
 * lien d'invitation prêt à être copié et, s'il y en a une, sa date de fin.
 */
final class CreateGroup
{
    public function handle(User $owner, string $name, GroupLifetime $lifetime = GroupLifetime::SansLimite): Group
    {
        return DB::transaction(function () use ($owner, $name, $lifetime): Group {
            $group = $owner->ownedGroups()->create([
                'name' => $name,
                'invite_token' => Str::random(32),
                'expires_at' => $lifetime->expiresAt(now()),
            ]);

            $group->addMember($owner, GroupRole::Proprietaire);

            return $group;
        });
    }
}
