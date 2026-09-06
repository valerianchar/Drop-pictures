<?php

namespace App\Actions;

use App\Models\Media;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Supprime un média avec son fichier d'origine, son aperçu, ses liens et ses
 * partages de groupe. Sans corbeille : la suppression est demandée deux fois
 * dans l'interface, elle est définitive ici.
 */
final class DeleteMedia
{
    public function handle(Media $media): void
    {
        DB::transaction(function () use ($media): void {
            $directory = dirname($media->disk_path);
            $thumbnail = $media->thumbnail_path;
            $archive = $media->archive_path;

            $media->delete();

            Storage::disk('local')->deleteDirectory($directory);

            if ($thumbnail !== null) {
                Storage::disk('local')->delete($thumbnail);
            }

            if ($archive !== null) {
                Storage::disk('local')->delete($archive);
            }
        });
    }
}
