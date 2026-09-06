<?php

namespace App\Http\Controllers;

use App\Actions\CreateGroup;
use App\Http\Requests\StoreGroupRequest;
use App\Http\Resources\GroupResource;
use App\Http\Resources\MediaResource;
use App\Models\Group;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class GroupController extends Controller
{
    public function store(StoreGroupRequest $request, CreateGroup $createGroup): RedirectResponse
    {
        $group = $createGroup->handle($request->user(), $request->string('name')->value(), $request->lifetime());

        return back()->with('success', $group->expires_at === null
            ? "Groupe « {$group->name} » créé — invite tes proches par lien."
            : "Groupe « {$group->name} » créé jusqu’au {$group->expires_at->translatedFormat('j F')} — invite tes proches par lien.");
    }

    public function show(Request $request, Group $group): Response
    {
        Gate::authorize('view', $group);

        $group->loadCount(['memberships', 'media']);

        $media = $group->media()
            ->with(['tags' => fn ($query) => $query->orderBy('name')])
            ->latest('group_media.created_at')
            ->get();

        $members = $group->memberships()->with('user')->orderBy('id')->get();

        return Inertia::render('Groups/Show', [
            'group' => GroupResource::make($group)->resolve(),
            'media' => MediaResource::collection($media)->resolve(),
            'members' => $members->map(fn ($membership): array => [
                'id' => $membership->user->id,
                'name' => $membership->user->name,
                'initials' => $membership->user->initials,
                'role' => $membership->role->label(),
                'is_owner' => $membership->user_id === $group->owner_id,
            ])->values()->all(),
            'can_leave' => $request->user()->can('leave', $group),
            'can_delete' => $request->user()->can('delete', $group),
            'leave_url' => route('groups.leave', $group),
            'delete_url' => route('groups.destroy', $group),
            'regenerate_url' => route('groups.invite-link.regenerate', $group),
        ]);
    }

    /**
     * Un nouveau lien d'invitation : les anciens, s'ils circulent encore, ne
     * mènent plus nulle part.
     */
    public function regenerateInviteLink(Group $group): RedirectResponse
    {
        Gate::authorize('update', $group);

        $group->forceFill(['invite_token' => Str::random(32)])->save();

        return back()->with('success', 'Nouveau lien d’invitation — l’ancien est désactivé.');
    }

    public function leave(Request $request, Group $group): RedirectResponse
    {
        Gate::authorize('leave', $group);

        $group->memberships()->where('user_id', $request->user()->id)->delete();

        return redirect()->route('dashboard')->with('success', "Tu as quitté « {$group->name} ».");
    }

    public function destroy(Group $group): RedirectResponse
    {
        Gate::authorize('delete', $group);

        $name = $group->name;
        $group->delete();

        return redirect()->route('dashboard')->with('success', "Groupe « {$name} » supprimé. Les fichiers restent chez leurs déposants.");
    }
}
