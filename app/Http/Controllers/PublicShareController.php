<?php

namespace App\Http\Controllers;

use App\Http\Resources\MediaResource;
use App\Models\ShareLink;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Ce que voit le destinataire d'un lien : le fichier, ses caractéristiques,
 * son empreinte, et le bouton qui lui rend l'original.
 */
class PublicShareController extends Controller
{
    public function show(string $token): Response
    {
        $link = $this->activeLink($token);
        $media = $link->media;

        return Inertia::render('Share/Show', [
            'media' => [
                ...MediaResource::make($media)->resolve(),
                'thumbnail_url' => $media->hasThumbnail() ? route('share.thumbnail', $token) : null,
                'download_url' => route('share.download', $token),
                'view_url' => route('share.view', $token),
                'can_edit' => false,
            ],
            'owner' => $link->user->first_name,
            'expires_label' => $link->expires_at?->translatedFormat('j F Y \à H\hi'),
        ]);
    }

    public function download(string $token): BinaryFileResponse
    {
        $link = $this->activeLink($token);
        $link->increment('downloads_count');

        return response()
            ->download($link->media->absolutePath(), $link->media->original_name, [
                'Content-Type' => $link->media->mime_type,
                'X-Checksum-SHA256' => $link->media->checksum_sha256,
                'Cache-Control' => 'private, no-transform',
            ]);
    }

    /**
     * L'original affiché dans le navigateur (« Enregistrer dans Photos » sur iPhone).
     */
    public function view(string $token): BinaryFileResponse
    {
        $link = $this->activeLink($token);
        $link->increment('downloads_count');

        return MediaController::inline($link->media);
    }

    public function thumbnail(string $token): BinaryFileResponse
    {
        $link = $this->activeLink($token);

        abort_if($link->media->thumbnail_path === null, 404);

        return response()->file(
            Storage::disk('local')->path($link->media->thumbnail_path),
            ['Content-Type' => 'image/jpeg', 'Cache-Control' => 'private, max-age=86400'],
        );
    }

    /**
     * Un lien expiré répond 404 comme un lien inexistant : rien ne dit au
     * visiteur qu'un fichier a existé ici.
     */
    private function activeLink(string $token): ShareLink
    {
        $link = ShareLink::query()->with(['media', 'user'])->where('token', $token)->first();

        abort_if($link === null || $link->isExpired(), 404);

        return $link;
    }
}
