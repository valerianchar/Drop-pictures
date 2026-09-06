<?php

namespace App\Actions;

use App\Models\Media;
use App\Models\Tag;

/**
 * Remplace les tags d'un média par la liste donnée. Les tags inconnus sont créés
 * pour l'utilisateur ; ceux qui ne servent plus à rien disparaissent, pour que
 * la barre de filtres ne garde pas de bouton vide.
 */
final class SyncMediaTags
{
    /**
     * @param  list<string>  $names
     */
    public function handle(Media $media, array $names): void
    {
        $ids = collect($names)
            ->map(fn (string $name): string => trim($name))
            ->filter()
            ->unique()
            ->map(fn (string $name): int => Tag::query()->firstOrCreate(
                ['user_id' => $media->user_id, 'name' => $name],
            )->id)
            ->all();

        $media->tags()->sync($ids);

        Tag::query()
            ->where('user_id', $media->user_id)
            ->whereDoesntHave('media')
            ->delete();
    }
}
