<?php

namespace App\Http\Controllers;

use App\Actions\CreateShareLink;
use App\Http\Requests\StoreShareLinkRequest;
use App\Http\Resources\ShareLinkResource;
use App\Models\Media;
use App\Models\ShareLink;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class ShareLinkController extends Controller
{
    public function store(StoreShareLinkRequest $request, Media $media, CreateShareLink $createShareLink): RedirectResponse
    {
        Gate::authorize('update', $media);

        $link = $createShareLink->handle($media, $request->user(), $request->isLimited());

        return back()
            ->with('share_link', ShareLinkResource::make($link)->resolve())
            ->with('success', 'Lien de partage prêt — fichier source, zéro compression.');
    }

    public function destroy(ShareLink $shareLink): RedirectResponse
    {
        Gate::authorize('update', $shareLink->media);

        $shareLink->delete();

        return back()->with('success', 'Lien désactivé : il ne mène plus nulle part.');
    }
}
