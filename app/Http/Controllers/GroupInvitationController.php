<?php

namespace App\Http\Controllers;

use App\Actions\InviteToGroup;
use App\Actions\JoinGroup;
use App\Http\Requests\StoreGroupInvitationRequest;
use App\Models\Group;
use App\Models\GroupInvitation;
use App\Support\PendingInvitation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class GroupInvitationController extends Controller
{
    public function store(StoreGroupInvitationRequest $request, Group $group, InviteToGroup $inviteToGroup): RedirectResponse
    {
        Gate::authorize('invite', $group);

        $invitation = $inviteToGroup->handle($group, $request->user(), $request->string('email')->value());

        return back()->with('success', "Invitation envoyée à {$invitation->email}.");
    }

    /**
     * Le lien du groupe : connecté, on entre tout de suite ; sinon on retient
     * l'invitation et on passe par l'inscription (ou la connexion).
     */
    public function join(Request $request, string $token, JoinGroup $joinGroup): RedirectResponse
    {
        $group = Group::query()->where('invite_token', $token)->firstOrFail();

        // Un groupe arrivé à échéance n'accueille plus personne, quel que soit le lien.
        abort_if($group->isExpired(), 404);

        if ($request->user() === null) {
            PendingInvitation::rememberGroup($group);

            return redirect()->route('register');
        }

        $joined = $joinGroup->handle($group, $request->user());

        return redirect()->route('groups.show', $group)->with(
            'success',
            $joined ? "Tu as rejoint « {$group->name} »." : "Tu es déjà dans « {$group->name} ».",
        );
    }

    /**
     * Le lien d'une invitation nominative. Même parcours, mais l'invitation se
     * marque acceptée et ne sert plus ensuite.
     */
    public function show(Request $request, string $token, JoinGroup $joinGroup): RedirectResponse
    {
        $invitation = GroupInvitation::query()->with('group')->where('token', $token)->firstOrFail();

        abort_if($invitation->group->isExpired(), 404);

        if ($invitation->isAccepted()) {
            return $request->user() !== null
                ? redirect()->route('groups.show', $invitation->group)
                : redirect()->route('login')->with('error', 'Cette invitation a déjà été utilisée — connecte-toi.');
        }

        if ($request->user() === null) {
            PendingInvitation::rememberEmail($invitation);

            return redirect()->route('register');
        }

        $joinGroup->handle($invitation->group, $request->user(), $invitation);

        return redirect()->route('groups.show', $invitation->group)
            ->with('success', "Tu as rejoint « {$invitation->group->name} ».");
    }
}
