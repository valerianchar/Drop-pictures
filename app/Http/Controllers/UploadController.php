<?php

namespace App\Http\Controllers;

use App\Actions\AppendUploadChunk;
use App\Actions\FinalizeUpload;
use App\Actions\StartUpload;
use App\Http\Requests\FinishUploadRequest;
use App\Http\Requests\StartUploadRequest;
use App\Http\Resources\MediaResource;
use App\Models\Upload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Le dépôt par morceaux, en trois temps : ouvrir, ajouter chaque morceau,
 * clore avec l'empreinte. Répond en JSON — c'est le navigateur, pas Inertia,
 * qui pilote ce protocole, morceau après morceau, avec sa barre de progression.
 */
class UploadController extends Controller
{
    public function store(StartUploadRequest $request, StartUpload $startUpload): JsonResponse
    {
        try {
            $upload = $startUpload->handle(
                $request->user(),
                $request->string('name')->value(),
                $request->integer('size'),
                $request->input('type'),
                $request->integer('chunk_bytes') ?: null,
            );
        } catch (ValidationException $exception) {
            // Un refus ne laisse aucune ligne : on le note, pour qu'un dépôt qui
            // « ne marche pas » depuis un téléphone ait une trace côté serveur.
            Log::info('Dépôt refusé', [
                'user_id' => $request->user()->id,
                'name' => $request->string('name')->value(),
                'size' => $request->integer('size'),
                'type' => $request->input('type'),
                'errors' => $exception->errors(),
                'agent' => Str::limit((string) $request->userAgent(), 200),
            ]);

            throw $exception;
        }

        return response()->json([
            'id' => $upload->uuid,
            'chunk_bytes' => $upload->chunk_bytes,
            'chunk_url' => route('uploads.chunk', [$upload->uuid, 'CHUNK']),
            'finish_url' => route('uploads.finish', $upload->uuid),
            'cancel_url' => route('uploads.destroy', $upload->uuid),
        ], 201);
    }

    /**
     * L'état d'un dépôt interrompu : ce qui manque encore. Le navigateur s'en
     * sert pour reprendre là où il s'était arrêté — quitter l'application ne
     * perd plus ce qui était déjà monté.
     */
    public function show(Request $request, Upload $upload): JsonResponse
    {
        $this->ensureOwner($request, $upload);

        return response()->json([
            'id' => $upload->uuid,
            'name' => $upload->original_name,
            'size' => $upload->size_bytes,
            'chunk_bytes' => $upload->chunk_bytes,
            'received_bytes' => $upload->received_bytes,
            'missing' => $upload->missingChunks(),
            'chunk_url' => route('uploads.chunk', [$upload->uuid, 'CHUNK']),
            'finish_url' => route('uploads.finish', $upload->uuid),
            'cancel_url' => route('uploads.destroy', $upload->uuid),
        ]);
    }

    public function chunk(Request $request, Upload $upload, int $index, AppendUploadChunk $appendChunk): JsonResponse
    {
        $this->ensureOwner($request, $upload);

        $stream = $request->getContent(asResource: true);
        $announced = $request->header('Content-Length');

        try {
            $upload = $appendChunk->handle($upload, $index, $stream, is_numeric($announced) ? (int) $announced : null);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        return response()->json([
            'received_bytes' => $upload->received_bytes,
            'next_chunk_index' => $upload->next_chunk_index,
        ]);
    }

    public function finish(FinishUploadRequest $request, Upload $upload, FinalizeUpload $finalizeUpload): JsonResponse
    {
        $this->ensureOwner($request, $upload);

        $group = $request->group();

        if ($group !== null) {
            Gate::authorize('share', $group);
        }

        $media = $finalizeUpload->handle($upload, $request->string('checksum')->value(), $request->tagNames(), $group);
        $media->load('tags')->loadCount('shareLinks');

        return response()->json([
            'media' => MediaResource::make($media)->resolve(),
        ], 201);
    }

    public function destroy(Request $request, Upload $upload): JsonResponse
    {
        $this->ensureOwner($request, $upload);

        Storage::disk('local')->delete($upload->part_path);
        $upload->delete();

        return response()->json(status: 204);
    }

    private function ensureOwner(Request $request, Upload $upload): void
    {
        abort_unless($upload->user_id === $request->user()->id, 404);
    }
}
