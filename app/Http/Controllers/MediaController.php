<?php

namespace App\Http\Controllers;

use App\Actions\DeleteMedia;
use App\Actions\SyncMediaTags;
use App\Events\MediaRemoved;
use App\Http\Requests\UpdateMediaTagsRequest;
use App\Http\Resources\MediaResource;
use App\Jobs\RestoreMedia;
use App\Models\Media;
use App\Queries\UserMedia;
use App\Support\ColdStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

class MediaController extends Controller
{
    /**
     * Sa propre galerie, en JSON, pour choisir des fichiers depuis un groupe :
     * ceux qui y sont déjà sont écartés.
     */
    public function index(Request $request, UserMedia $userMedia): JsonResponse
    {
        $excludeGroup = $request->integer('exclude_group') ?: null;
        $search = $request->string('q')->value() ?: null;

        $media = $userMedia->forGallery($request->user(), null, null, $search)
            ->when($excludeGroup !== null, fn ($items) => $items->reject(fn (Media $item) => $item->groups()->whereKey($excludeGroup)->exists()));

        return response()->json(['media' => MediaResource::collection($media->values())->resolve()]);
    }

    /**
     * Le fichier d'origine, octet pour octet, sous son nom d'origine. Réponse
     * en flux avec reprise (Range) : un fichier de plusieurs Go part sans
     * jamais passer par la mémoire de PHP, et rien ne le transforme en route.
     */
    public function download(Media $media): BinaryFileResponse
    {
        Gate::authorize('view', $media);
        $media = ColdStorage::ensureHot($media);

        return response()
            ->download($media->absolutePath(), $media->original_name, [
                'Content-Type' => $media->mime_type,
                'X-Checksum-SHA256' => $media->checksum_sha256,
                'Cache-Control' => 'private, no-transform',
            ]);
    }

    /**
     * Le même fichier d'origine, affiché dans le navigateur au lieu d'être
     * téléchargé. Sur iPhone, un appui long sur l'image propose alors
     * « Enregistrer dans Photos » — là où un téléchargement finit dans Fichiers.
     */
    public function view(Media $media): BinaryFileResponse
    {
        Gate::authorize('view', $media);

        return self::inline(ColdStorage::ensureHot($media));
    }

    /**
     * Reconstruire l'original d'un fichier archivé, en tâche de fond : la carte
     * l'annonce quand il est de retour. Le téléchargement direct n'attend pas
     * ce bouton — il restaure lui-même —, mais lui permet de préparer plusieurs
     * fichiers à l'avance.
     */
    public function restore(Media $media): RedirectResponse
    {
        Gate::authorize('view', $media);

        if ($media->isArchived() && $media->restoring_at === null) {
            // Posé tout de suite : la réponse dit déjà « en cours », un second clic ne relance rien.
            $media->forceFill(['restoring_at' => now()])->saveQuietly();
            RestoreMedia::dispatch($media);
        }

        return back()->with('success', "« {$media->original_name} » se reconstruit — quelques secondes.");
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
        $id = $media->id;
        $groupIds = $media->groups()->pluck('groups.id')->all();
        $deleteMedia->handle($media);

        if ($groupIds !== []) {
            MediaRemoved::dispatch($id, $name, $groupIds, $media->user_id);
        }

        return redirect()->route('dashboard')->with('success', "« {$name} » supprimé.");
    }

    /**
     * Réponse inline sur le fichier d'origine — partagée avec la page publique.
     */
    public static function inline(Media $media): BinaryFileResponse
    {
        $response = response()->file($media->absolutePath(), [
            'Content-Type' => $media->mime_type,
            'X-Checksum-SHA256' => $media->checksum_sha256,
            'Cache-Control' => 'private, no-transform',
        ]);

        // Le nom d'origine est conservé, avec un repli ASCII pour les vieux clients.
        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_INLINE,
            $media->original_name,
            str_replace(['%', '/', '\\', '"'], '', Str::ascii($media->original_name)) ?: 'fichier',
        );

        return $response;
    }
}
