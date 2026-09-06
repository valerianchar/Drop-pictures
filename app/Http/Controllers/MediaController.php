<?php

namespace App\Http\Controllers;

use App\Actions\DeleteMedia;
use App\Actions\SyncMediaTags;
use App\Http\Requests\UpdateMediaTagsRequest;
use App\Models\Media;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class MediaController extends Controller
{
    /**
     * Le fichier d'origine, octet pour octet, sous son nom d'origine. Réponse
     * en flux avec reprise (Range) : un fichier de plusieurs Go part sans
     * jamais passer par la mémoire de PHP, et rien ne le transforme en route.
     */
    public function download(Media $media): BinaryFileResponse
    {
        Gate::authorize('view', $media);

        return response()
            ->download($media->absolutePath(), $media->original_name, [
                'Content-Type' => $media->mime_type,
                'X-Checksum-SHA256' => $media->checksum_sha256,
                'Cache-Control' => 'private, no-transform',
            ]);
    }

    /**
     * L'aperçu réduit — le seul dérivé. Il est distinct du fichier d'origine
     * et ne le remplace jamais au téléchargement.
     */
    public function thumbnail(Media $media): BinaryFileResponse
    {
        Gate::authorize('view', $media);

        abort_if($media->thumbnail_path === null, 404);

        return response()
            ->file(Storage::disk('local')->path($media->thumbnail_path), [
                'Content-Type' => 'image/jpeg',
                'Cache-Control' => 'private, max-age=604800, immutable',
            ]);
    }

    public function updateTags(UpdateMediaTagsRequest $request, Media $media, SyncMediaTags $syncTags): RedirectResponse
    {
        Gate::authorize('update', $media);

        $syncTags->handle($media, $request->tagNames());

        return back()->with('success', 'Tags mis à jour.');
    }

    public function destroy(Media $media, DeleteMedia $deleteMedia): RedirectResponse
    {
        Gate::authorize('delete', $media);

        $name = $media->original_name;
        $deleteMedia->handle($media);

        return redirect()->route('dashboard')->with('success', "« {$name} » supprimé.");
    }
}
