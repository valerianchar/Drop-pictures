<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Un fichier a quitté un ou plusieurs groupes — retiré, ou supprimé par son
 * déposant : les pages ouvertes le font disparaître.
 */
class MediaRemoved implements ShouldBroadcastNow
{
    use Dispatchable;

    /**
     * @param  list<int>  $groupIds
     */
    public function __construct(
        public readonly int $mediaId,
        public readonly string $name,
        public readonly array $groupIds,
        public readonly int $actorId,
    ) {}

    /**
     * @return list<PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return array_map(fn (int $groupId) => new PrivateChannel('groups.'.$groupId), $this->groupIds);
    }

    public function broadcastAs(): string
    {
        return 'media.removed';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return ['media_id' => $this->mediaId, 'name' => $this->name, 'actor_id' => $this->actorId];
    }
}
