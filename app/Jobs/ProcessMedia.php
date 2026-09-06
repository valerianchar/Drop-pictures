<?php

namespace App\Jobs;

use App\Models\Media;
use App\Support\MediaProbe;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;

/**
 * Lit les caractéristiques d'un média fraîchement déposé et en tire un aperçu.
 *
 * Le fichier d'origine est ouvert en lecture seule ; l'aperçu est un fichier à
 * part, dans son propre dossier. Si rien ne peut être lu (RAW, format inconnu),
 * le média reste tel quel, simplement sans aperçu — jamais rejeté.
 */
class ProcessMedia implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 300;

    /**
     * Un média supprimé avant que le worker ne l'atteigne — le selftest de chaque
     * déploiement, ou un fichier retiré aussitôt déposé — n'a plus rien à traiter :
     * le job disparaît en silence au lieu de rejoindre les échecs.
     */
    public bool $deleteWhenMissingModels = true;

    public function __construct(public readonly Media $media) {}

    public function handle(MediaProbe $probe): void
    {
        $media = $this->media->fresh();

        if ($media === null) {
            return;
        }

        $source = $media->absolutePath();
        $attributes = $probe->inspect($source, $media->kind, $media->extension);

        Storage::disk('local')->makeDirectory('thumbnails');
        $thumbnailPath = "thumbnails/{$media->uuid}.jpg";

        $hasThumbnail = $probe->thumbnail(
            $source,
            Storage::disk('local')->path($thumbnailPath),
            $media->kind,
            (int) config('drop.thumbnail_width'),
        );

        $media->forceFill([
            ...$attributes,
            'thumbnail_path' => $hasThumbnail ? $thumbnailPath : null,
            'processed_at' => now(),
        ])->save();
    }
}
