<?php

namespace App\Http\Controllers;

use App\Actions\ShareMediaToGroup;
use App\Http\Requests\StoreGroupMediaRequest;
use App\Models\Group;
use App\Models\Media;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class GroupMediaController extends Controller
{
    public function store(StoreGroupMediaRequest $request, Group $group, ShareMediaToGroup $shareToGroup): RedirectResponse
    {
        Gate::authorize('share', $group);

        $media = $request->media();
        $shared = $shareToGroup->handle($media, $group, $request->user());

        return back()->with('success', $shared
            ? "Envoyé au groupe « {$group->name} » en qualité d’origine."
            : "« {$media->original_name} » était déjà dans « {$group->name} ».");
    }

    public function destroy(Group $group, Media $media): RedirectResponse
    {
        Gate::authorize('update', $media);

        $group->media()->detach($media->id);

        return back()->with('success', "« {$media->original_name} » retiré de « {$group->name} ».");
    }
}
