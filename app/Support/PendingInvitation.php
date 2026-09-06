<?php

namespace App\Support;

use App\Models\Group;
use App\Models\GroupInvitation;
use Illuminate\Support\Facades\Session;

/**
 * Une invitation qui attend que son destinataire se connecte ou s'inscrive.
 *
 * Quand un visiteur clique un lien de groupe sans être connecté, on retient le
 * jeton en session, on l'envoie créer son compte, puis on l'installe dans le
 * groupe dès la session ouverte — il « arrive directement dans le groupe ».
 */
final class PendingInvitation
{
    private const KEY = 'invitation';

    public static function rememberGroup(Group $group): void
    {
        Session::put(self::KEY, ['type' => 'group', 'token' => $group->invite_token, 'group' => $group->name]);
    }

    public static function rememberEmail(GroupInvitation $invitation): void
    {
        Session::put(self::KEY, ['type' => 'email', 'token' => $invitation->token, 'group' => $invitation->group->name]);
    }

    /** Le nom du groupe qui attend, pour l'afficher sur l'écran d'inscription. */
    public static function groupName(): ?string
    {
        return Session::get(self::KEY.'.group');
    }

    /**
     * Retire l'invitation de la session et la résout en groupe (+ invitation
     * nominative le cas échéant). Nul si rien n'attend ou si le lien n'est plus valable.
     *
     * @return array{0: Group, 1: ?GroupInvitation}|null
     */
    public static function pull(): ?array
    {
        $pending = Session::pull(self::KEY);

        if (! is_array($pending)) {
            return null;
        }

        if ($pending['type'] === 'email') {
            $invitation = GroupInvitation::query()->where('token', $pending['token'])->whereNull('accepted_at')->first();

            return $invitation === null || $invitation->group->isExpired() ? null : [$invitation->group, $invitation];
        }

        $group = Group::query()->where('invite_token', $pending['token'])->first();

        // Le groupe a pu arriver à échéance pendant l'inscription : on n'y entre plus.
        return $group === null || $group->isExpired() ? null : [$group, null];
    }
}
