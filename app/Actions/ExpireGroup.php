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
 * @return int Le nombre de fichiers détruits.
 */
final class ExpireGroup
{
    public function __construct(private readonly DeleteMedia $deleteMedia) {}

    public function handle(Group $group): int
    {
        return DB::transaction(function () use ($group): int {
            $destroyed = 0;

            $group->media()->wherePivot('uploaded_here', true)->get()->each(function (Media $media) use (&$destroyed): void {
                $this->deleteMedia->handle($media);
                $destroyed++;
            });

            $group->media()->detach();
            $group->delete();

            return $destroyed;
        });
    }
}
