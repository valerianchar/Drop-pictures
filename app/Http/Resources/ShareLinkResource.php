<?php

namespace App\Http\Resources;

use App\Models\ShareLink;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ShareLink
 */
class ShareLinkResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'media_id' => $this->media_id,
            'url' => $this->url(),
            'short' => preg_replace('#^https?://#', '', $this->url()),
            'expires_at' => $this->expires_at?->toIso8601String(),
            'expires_label' => $this->expires_at === null
                ? 'Sans limite'
                : ($this->isExpired() ? 'Expiré' : 'Expire '.$this->expires_at->diffForHumans()),
            'is_expired' => $this->isExpired(),
            'downloads_count' => $this->downloads_count,
            'media' => $this->whenLoaded('media', fn () => MediaResource::make($this->media)->resolve()),
            'delete_url' => route('share-links.destroy', $this->id),
        ];
    }
}
