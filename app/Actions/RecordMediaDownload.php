<?php

namespace App\Actions;

use App\Enums\DownloadChannel;
use App\Models\Media;
use App\Models\MediaDownload;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Noter qu'une personne a récupéré un fichier.
 *
 * Jamais bloquant et jamais bruyant : c'est un indicateur de confort, il ne
 * doit pas faire échouer le téléchargement qu'il accompagne.
 */
final class RecordMediaDownload
{
    public function handle(Media $media, ?User $user, DownloadChannel $via): void
    {
        if ($user === null) {
            return;
        }

        $existing = MediaDownload::query()
            ->where('media_id', $media->id)
            ->where('user_id', $user->id)
            ->first();

        if ($existing !== null) {
            $existing->forceFill(['via' => $via, 'times' => $existing->times + 1, 'last_at' => now()])->save();

            return;
        }

        try {
            MediaDownload::query()->create([
                'media_id' => $media->id,
                'user_id' => $user->id,
                'via' => $via,
                'times' => 1,
                'first_at' => now(),
                'last_at' => now(),
            ]);
        } catch (UniqueConstraintViolationException) {
            // Deux requêtes en même temps (le lot de la photothèque, par exemple) :
            // la ligne existe désormais, on la complète.
            MediaDownload::query()
                ->where('media_id', $media->id)
                ->where('user_id', $user->id)
                ->update(['via' => $via, 'times' => 2, 'last_at' => now()]);
        }
    }

    /**
     * Plusieurs fichiers d'un coup — un ZIP de 500 entrées, un lot vers la
     * photothèque. En deux requêtes, pas deux par fichier : ce marquage
     * précède un téléchargement, il n'a pas à le retarder.
     *
     * @param  iterable<Media>  $media
     */
    public function many(iterable $media, ?User $user, DownloadChannel $via): void
    {
        if ($user === null) {
            return;
        }

        $ids = collect($media)->pluck('id')->filter()->unique()->values();

        if ($ids->isEmpty()) {
            return;
        }

        $now = now();
        $known = MediaDownload::query()
            ->where('user_id', $user->id)
            ->whereIn('media_id', $ids)
            ->pluck('media_id');

        if ($known->isNotEmpty()) {
            MediaDownload::query()
                ->where('user_id', $user->id)
                ->whereIn('media_id', $known)
                ->update(['via' => $via->value, 'times' => DB::raw('times + 1'), 'last_at' => $now]);
        }

        $fresh = $ids->diff($known);

        if ($fresh->isNotEmpty()) {
            // insertOrIgnore : deux lots simultanés ne se marchent pas dessus.
            MediaDownload::query()->insertOrIgnore($fresh->map(fn (int $id): array => [
                'media_id' => $id,
                'user_id' => $user->id,
                'via' => $via->value,
                'times' => 1,
                'first_at' => $now,
                'last_at' => $now,
            ])->all());
        }
    }
}
