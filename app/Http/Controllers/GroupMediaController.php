<?php

namespace App\Http\Controllers;

use App\Actions\ShareMediaToGroup;
use App\Events\MediaAdded;
use App\Events\MediaRemoved;
use App\Http\Requests\StoreGroupMediaRequest;
use App\Models\Group;
use App\Models\Media;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class GroupMediaController extends Controller
{
    /**
     * Partage un ou plusieurs fichiers de sa galerie vers le groupe.
     */
    public function store(StoreGroupMediaRequest $request, Group $group, ShareMediaToGroup $shareToGroup): RedirectResponse
    {
        Gate::authorize('share', $group);

        $shared = 0;
        $already = 0;

        foreach ($request->media() as $media) {
            if ($shareToGroup->handle($media, $group, $request->user())) {
                MediaAdded::dispatch($media, $group, $request->user());
                $shared++;
            } else {
                $already++;
            }
        }

        return back()->with('success', match (true) {
            $shared === 0 => $already > 1
                ? "Ces fichiers étaient déjà dans « {$group->name} »."
                : "Ce fichier était déjà dans « {$group->name} ».",
            $shared === 1 => "Envoyé au groupe « {$group->name} » en qualité d’origine.",
            default => "{$shared} fichiers envoyés au groupe « {$group->name} » en qualité d’origine.",
        });
    }

    /**
     * Retire un fichier du groupe. Seul son déposant peut le faire, et seulement
     * s'il y est vraiment : sinon rien n'est diffusé aux membres — un inconnu ne
     * doit pas pouvoir faire apparaître un message sur le canal d'un groupe.
     */
    public function destroy(Group $group, Media $media): RedirectResponse
    {
        Gate::authorize('update', $media);

        abort_if($group->media()->detach($media->id) === 0, 404);

        MediaRemoved::dispatch($media->id, $media->original_name, [$group->id], $media->user_id);

        return back()->with('success', "« {$media->original_name} » retiré de « {$group->name} ».");
    }
}
