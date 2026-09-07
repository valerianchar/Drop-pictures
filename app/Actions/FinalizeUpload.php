<?php

namespace App\Actions;

use App\Enums\MediaKind;
use App\Events\MediaAdded;
use App\Jobs\ProcessMedia;
use App\Models\Group;
use App\Models\Media;
use App\Models\Tag;
use App\Models\Upload;
use App\Support\MimeType;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Clôt un dépôt : le fichier partiel doit avoir exactement la taille annoncée et
 * l'empreinte SHA-256 des octets reçus doit être celle que le navigateur a
 * calculée sur le fichier d'origine. Alors seulement il devient un média — par
 * un simple renommage, sans copie ni réécriture : ce sont les octets reçus qui
 * sont servis.
 */
final class FinalizeUpload
{
    /**
     * @param  list<string>  $tagNames
     * @param  ?Group  $group  Le groupe où le fichier est déposé directement, s'il y en a un.
     */
    public function handle(Upload $upload, string $clientChecksum, array $tagNames = [], ?Group $group = null): Media
    {
        if (! $upload->isComplete()) {
            throw ValidationException::withMessages([
                'checksum' => 'Le fichier est incomplet : '.$upload->received_bytes.' octets reçus sur '.$upload->size_bytes.'.',
            ]);
        }

        // Le dernier morceau reçu a pu combler un trou : l'empreinte rattrape
        // alors tout le préfixe d'un coup avant d'être arrêtée.
        $upload = AppendUploadChunk::catchUp($upload);
        $partPath = $upload->absolutePartPath();

        // L'empreinte a été calculée au fil des morceaux ; un dépôt ouvert avant
        // cette mécanique (sans état conservé) relit le fichier, comme avant.
        $checksum = $upload->hash_state !== null && $upload->next_chunk_index >= $upload->chunkCount()
            ? hash_final(AppendUploadChunk::restoreContext($upload))
            : hash_file('sha256', $partPath);

        if (! hash_equals(strtolower($clientChecksum), $checksum)) {
            Storage::disk('local')->delete($upload->part_path);
            $upload->delete();

            throw ValidationException::withMessages([
                'checksum' => 'Le fichier reçu ne correspond pas à l’original (empreinte différente). Recommence le dépôt.',
            ]);
        }

        $extension = strtolower(pathinfo($upload->original_name, PATHINFO_EXTENSION));
        $directory = "media/{$upload->user_id}/{$upload->uuid}";
        $finalPath = "{$directory}/{$upload->original_name}";

        Storage::disk('local')->makeDirectory($directory);

        // rename() déplace l'inode : aucun octet n'est relu ni réécrit.
        if (! rename($partPath, Storage::disk('local')->path($finalPath))) {
            throw ValidationException::withMessages(['checksum' => 'Impossible de ranger le fichier.']);
        }

        $media = DB::transaction(function () use ($upload, $extension, $finalPath, $checksum, $tagNames, $group): Media {
            $media = Media::create([
                'user_id' => $upload->user_id,
                'uuid' => $upload->uuid,
                'original_name' => $upload->original_name,
                'extension' => $extension,
                'mime_type' => MimeType::resolve($upload->mime_type, $extension),
                'kind' => MediaKind::fromExtension($extension),
                'size_bytes' => $upload->size_bytes,
                'checksum_sha256' => $checksum,
                'disk_path' => $finalPath,
            ]);

            $this->attachTags($media, $tagNames);

            if ($group !== null) {
                $group->media()->attach($media->id, ['shared_by' => $upload->user_id, 'uploaded_here' => true]);
            }

            $upload->delete();

            return $media;
        });

        if ($group !== null) {
            MediaAdded::dispatch($media, $group, $upload->user);
        }

        ProcessMedia::dispatch($media);

        // Avec une file synchrone (local, tests), l'aperçu existe déjà : on
        // renvoie l'état à jour plutôt que l'instance d'avant le traitement.
        return $media->refresh();
    }

    /**
     * @param  list<string>  $tagNames
     */
    private function attachTags(Media $media, array $tagNames): void
    {
        $ids = collect($tagNames)
            ->map(fn (string $name): string => trim($name))
            ->filter()
            ->unique()
            ->map(fn (string $name): int => Tag::query()->firstOrCreate(
                ['user_id' => $media->user_id, 'name' => $name],
            )->id)
            ->all();

        if ($ids !== []) {
            $media->tags()->sync($ids);
        }
    }
}
