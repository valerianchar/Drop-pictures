<?php

namespace App\Jobs;

use App\Events\MediaProcessed;
use App\Models\Media;
use App\Support\ColdStorage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Archive un original sans perte, puis seulement l'efface du disque.
 *
 * L'archive est décompressée aussitôt dans un fichier témoin dont l'empreinte
 * est comparée à celle du dépôt : si elle diffère d'un octet, l'archive est
 * jetée et l'original reste. Si le gain est trop faible, idem. Ce n'est
 * qu'après cette preuve que l'original cède sa place.
 */
class ArchiveMedia implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 3600;

    public bool $deleteWhenMissingModels = true;

    public function __construct(public readonly Media $media) {}

    public function handle(): void
    {
        $media = $this->media->fresh();

        if ($media === null || $media->isArchived() || $media->restoring_at !== null) {
            return;
        }

        $codec = ColdStorage::codecFor($media);
        $source = $media->absolutePath();

        if ($codec === null || ! is_file($source)) {
            $media->forceFill(['archive_skipped_at' => now()])->saveQuietly();

            return;
        }

        $archivePath = ColdStorage::archivePath($media, $codec);
        $archive = Storage::disk('local')->path($archivePath);
        // djxl choisit le format de sortie d'après l'extension : le témoin garde celle de l'original.
        $witness = dirname($source).'/temoin-'.basename($source);

        Storage::disk('local')->makeDirectory('archives');

        try {
            ColdStorage::compress($source, $archive, $codec);
            ColdStorage::decompress($archive, $witness, $codec);

            $identical = hash_file('sha256', $witness) === $media->checksum_sha256;
            $archivedBytes = (int) filesize($archive);
            $saving = 1 - $archivedBytes / max(1, $media->size_bytes);

            if (! $identical) {
                Log::warning("Archive non reconstructible à l'identique pour le média {$media->id} ({$codec}) : original conservé.");
            }

            if (! $identical || $saving < (float) config('drop.archive_min_saving')) {
                @unlink($archive);
                $media->forceFill(['archive_skipped_at' => now()])->saveQuietly();

                return;
            }

            // Mise à jour conditionnelle : si le média a été supprimé ou restauré
            // entre-temps, aucune ligne ne bouge, et l'archive est jetée sans
            // toucher à l'original.
            $updated = Media::query()
                ->whereKey($media->id)
                ->whereNull('archived_at')
                ->whereNull('restoring_at')
                ->update([
                    'archived_at' => now(),
                    'archive_path' => $archivePath,
                    'archive_codec' => $codec,
                    'archived_bytes' => $archivedBytes,
                ]);

            if ($updated !== 1) {
                @unlink($archive);

                return;
            }

            // Seulement maintenant : l'archive est là, vérifiée, enregistrée.
            @unlink($source);

            $media->refresh();
            MediaProcessed::dispatch($media, $media->groups()->pluck('groups.id')->all());
        } catch (Throwable $exception) {
            @unlink($archive);
            Log::warning("Archivage du média {$media->id} abandonné : {$exception->getMessage()}");
            $media->forceFill(['archive_skipped_at' => now()])->saveQuietly();
        } finally {
            @unlink($witness);
        }
    }
}
