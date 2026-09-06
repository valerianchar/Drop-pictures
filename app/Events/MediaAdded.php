<?php

namespace App\Events;

use App\Models\Group;
use App\Models\Media;
use App\Models\User;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Un fichier vient d'arriver dans un groupe — déposé là directement ou partagé
 * depuis une galerie : les membres présents sur la page le voient apparaître.
 */
class MediaAdded implements ShouldBroadcastNow
{
    use Dispatchable;

    public function __construct(
        public readonly Media $media,
        public readonly Group $group,
        public readonly User $actor,
    ) {}

    /**
     * @return list<PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('groups.'.$this->group->id)];
    }

    public function broadcastAs(): string
    {
        return 'media.added';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'group_id' => $this->group->id,
            'media_id' => $this->media->id,
            'name' => $this->media->original_name,
            'actor_id' => $this->actor->id,
            'actor_name' => $this->actor->first_name,
        ];
    }
}
