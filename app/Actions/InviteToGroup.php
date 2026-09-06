<?php

namespace App\Actions;

use App\Models\Group;
use App\Models\GroupInvitation;
use App\Models\User;
use App\Notifications\GroupInvitationReceived;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

/**
 * Invite quelqu'un par e-mail. Une invitation en attente pour la même adresse
 * est renvoyée plutôt que doublée : c'est un rappel, pas un second jeton.
 */
final class InviteToGroup
{
    public function handle(Group $group, User $inviter, string $email): GroupInvitation
    {
        $email = Str::lower(trim($email));

        $invitation = $group->invitations()
            ->where('email', $email)
            ->whereNull('accepted_at')
            ->first();

        $invitation ??= $group->invitations()->create([
            'invited_by' => $inviter->id,
            'email' => $email,
            'token' => Str::random(40),
        ]);

        Notification::route('mail', $email)->notify(new GroupInvitationReceived($invitation));

        return $invitation;
    }
}
