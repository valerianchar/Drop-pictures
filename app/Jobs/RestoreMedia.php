<?php

namespace App\Jobs;

use App\Events\MediaRestored;
use App\Models\Media;
use App\Support\ColdStorage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Reconstruit l'original depuis son archive, vérifie qu'il est identique au
 * dépôt — l'empreinte SHA-256 ne laisse aucune place au doute —, le remet à
 * sa place et jette l'archive. Le fichier redevient « chaud » pour un nouveau
 * délai avant archivage.
 *
 * Deux demandes simultanées — deux téléchargements du même fichier archivé —
 * se sérialisent sur un verrou : la seconde trouve l'original déjà reconstruit.
 */
class RestoreMedia implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 1800;

    public bool $deleteWhenMissingModels = true;

    public function __construct(public readonly Media $media) {}

    public function handle(): void
    {
        Cache::lock("media-restore:{$this->media->id}", 1800)->block(1800, function (): void {
            $media = $this->media->fresh();

            if ($media === null || ! $media->isArchived()) {
                return;
            }

            $this->restore($media);
        });
    }

    private function restore(Media $media): void
    {
        $archive = Storage::disk('local')->path($media->archive_path);
        $destination = $media->absolutePath();
        // Même extension que l'original : djxl s'en sert pour rendre le JPEG d'origine.
        $temporary = dirname($destination).'/restaure-'.basename($destination);

        if (! is_file($archive)) {
            throw new RuntimeException("Archive introuvable pour le média {$media->id}.");
        }

        $media->forceFill(['restoring_at' => now()])->saveQuietly();

        try {
            Storage::disk('local')->makeDirectory(dirname($media->disk_path));
            ColdStorage::decompress($archive, $temporary, $media->archive_codec);

            if (hash_file('sha256', $temporary) !== $media->checksum_sha256) {
                @unlink($temporary);

                throw new RuntimeException("L'original reconstruit du média {$media->id} n'a pas l'empreinte attendue.");
            }

            if (! rename($temporary, $destination)) {
                throw new RuntimeException("Impossible de remettre l'original du média {$media->id} à sa place.");
            }

            $media->forceFill([
                'archived_at' => null,
                'archive_path' => null,
                'archive_codec' => null,
                'archived_bytes' => null,
                'restoring_at' => null,
                'last_accessed_at' => now(),
            ])->save();

            @unlink($archive);

            MediaRestored::dispatch($media, $media->groups()->pluck('groups.id')->all());
        } finally {
            $media->forceFill(['restoring_at' => null])->saveQuietly();
        }
    }
}
