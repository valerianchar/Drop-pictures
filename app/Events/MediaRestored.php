<?php

namespace App\Events;

use App\Models\Media;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Un original archivé vient d'être reconstruit : la carte redevient
 * téléchargeable chez le déposant et dans les groupes concernés.
 */
class MediaRestored implements ShouldBroadcastNow
{
    use Dispatchable;

    /**
     * @param  list<int>  $groupIds
     */
    public function __construct(
        public readonly Media $media,
        public readonly array $groupIds,
    ) {}

    /**
     * @return list<PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('users.'.$this->media->user_id),
            ...array_map(fn (int $groupId) => new PrivateChannel('groups.'.$groupId), $this->groupIds),
        ];
    }

    public function broadcastAs(): string
    {
        return 'media.restored';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return ['media_id' => $this->media->id, 'name' => $this->media->original_name];
    }
}
