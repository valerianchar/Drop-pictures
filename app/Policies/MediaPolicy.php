<?php

namespace App\Policies;

use App\Models\Media;
use App\Models\User;

class MediaPolicy
{
    /**
     * Voir et télécharger : le déposant, et les membres des groupes où le
     * média a été partagé.
     */
    public function view(User $user, Media $media): bool
    {
        return $media->isAccessibleBy($user);
    }

    /** Partager, taguer, supprimer : le déposant seul. */
    public function update(User $user, Media $media): bool
    {
        return $media->user_id === $user->id;
    }

    public function delete(User $user, Media $media): bool
    {
        return $media->user_id === $user->id;
    }
}
