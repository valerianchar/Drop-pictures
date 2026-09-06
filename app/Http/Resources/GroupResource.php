<?php

namespace App\Http\Resources;

use App\Models\Group;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Group
 */
class GroupResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $members = $this->memberships_count ?? $this->memberships()->count();
        $files = $this->media_count ?? $this->media()->count();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'members_count' => $members,
            'members_label' => $members.' '.($members > 1 ? 'membres' : 'membre'),
            'files_count' => $files,
            'detail' => $files === 0
                ? 'Aucun fichier pour l’instant'
                : $files.' '.($files > 1 ? 'fichiers partagés' : 'fichier partagé'),
            'is_owner' => $this->owner_id === $request->user()?->id,
            'url' => route('groups.show', $this->id),
            'invite_url' => $this->inviteUrl(),
            'invite_email_url' => route('groups.invitations.store', $this->id),
            'share_url' => route('groups.media.store', $this->id),
        ];
    }
}
