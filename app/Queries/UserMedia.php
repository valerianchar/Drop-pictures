<?php

namespace App\Queries;

use App\Enums\MediaKind;
use App\Models\Media;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

final class UserMedia
{
    /**
     * La galerie de l'utilisateur, du plus récent au plus ancien, filtrée par
     * tag, par famille et par recherche sur le nom.
     *
     * @return Collection<int, Media>
     */
    public function forGallery(User $user, ?string $tag, ?string $kind, ?string $search, int $limit = 300): Collection
    {
        return $user->media()
            ->with([
                'tags' => fn ($query) => $query->orderBy('name'),
                'downloads' => fn ($query) => $query->where('user_id', $user->id),
            ])
            ->withCount('shareLinks')
            ->when($tag !== null && $tag !== '', fn (Builder $query) => $query
                ->whereHas('tags', fn (Builder $tags) => $tags->where('name', $tag)))
            ->when(MediaKind::tryFrom((string) $kind) !== null, fn (Builder $query) => $query
                ->where('kind', $kind))
            ->when($search !== null && trim($search) !== '', fn (Builder $query) => $query
                ->where('original_name', 'like', '%'.addcslashes(trim($search), '%_\\').'%'))
            ->latest('id')
            ->limit($limit)
            ->get();
    }
}
