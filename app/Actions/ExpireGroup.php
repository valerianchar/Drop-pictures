<?php

namespace App\Actions;

use App\Models\Group;
use App\Models\Media;
use Illuminate\Support\Facades\DB;

/**
 * Clôt un groupe arrivé à échéance : ce qui y a été déposé directement est
 * détruit — fichier, aperçu, liens —, ce qui venait d'une galerie y reste et
 * n'est que retiré du groupe, puis le groupe disparaît avec ses invitations.
 *
 * Un fichier déposé ici mais qui vit aussi ailleurs — partagé dans un autre
 * groupe, ou par un lien public — n'est pas détruit : il est seulement retiré.
 * On ne casse pas un lien que quelqu'un a envoyé.
 */
final class ExpireGroup
{
    public function __construct(private readonly DeleteMedia $deleteMedia) {}

    /**
     * @return int Le nombre de fichiers détruits.
     */
    public function handle(Group $group): int
    {
        return DB::transaction(function () use ($group): int {
            $destroyed = 0;

            $group->media()->wherePivot('uploaded_here', true)->get()->each(function (Media $media) use ($group, &$destroyed): void {
                $livesElsewhere = $media->groups()->whereKeyNot($group->id)->exists()
                    || $media->shareLinks()->exists();

                if (! $livesElsewhere) {
                    $this->deleteMedia->handle($media);
                    $destroyed++;
                }
            });

            $group->media()->detach();
            $group->delete();

            return $destroyed;
        });
    }
}
