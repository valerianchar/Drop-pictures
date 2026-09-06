<?php

namespace App\Queries;

use App\Models\Group;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

final class UserGroups
{
    /**
     * Les groupes de l'utilisateur avec leur nombre de membres et de fichiers,
     * agrégés en base — une requête, pas une par carte.
     *
     * @return Collection<int, Group>
     */
    public function forDashboard(User $user): Collection
    {
        return $user->accessibleGroups()
            // Un groupe arrivé à échéance est déjà fermé ; le planificateur le supprimera.
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->withCount(['memberships', 'media'])
            ->orderBy('id')
            ->get();
    }
}
