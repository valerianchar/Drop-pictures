<?php

namespace App\Actions;

use App\Models\Group;
use App\Models\Media;
use App\Models\User;

/**
 * Partage un média vers un groupe : chaque membre le voit apparaître et peut
 * télécharger le fichier d'origine. Repartager le même média ne crée rien.
 */
final class ShareMediaToGroup
{
    public function handle(Media $media, Group $group, User $sharedBy): bool
    {
        if ($group->media()->where('media.id', $media->id)->exists()) {
            return false;
        }

        $group->media()->attach($media->id, ['shared_by' => $sharedBy->id]);

        return true;
    }
}
