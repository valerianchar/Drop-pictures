<?php

namespace App\Http\Resources;

use App\Models\Media;
use App\Support\FileSize;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Media
 */
class MediaResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $dimensions = $this->width !== null && $this->height !== null ? "{$this->width}×{$this->height}" : null;

        return [
            'id' => $this->id,
            'name' => $this->original_name,
            'extension' => $this->extension,
            'kind' => $this->kind->value,
            'is_video' => $this->isVideo(),
            'size_bytes' => $this->size_bytes,
            'size_label' => FileSize::format($this->size_bytes),
            // « 6000×4000 · 14,2 Mo » sous le nom du fichier.
            'meta' => implode(' · ', array_filter([$dimensions, FileSize::format($this->size_bytes)])),
            'quality' => $this->quality_label,
            'duration_label' => $this->durationLabel(),
            'checksum' => $this->checksum_sha256,
            'thumbnail_url' => $this->hasThumbnail() ? route('media.thumbnail', $this->id) : null,
            'download_url' => route('media.download', $this->id),
            'processed' => $this->processed_at !== null,
            'tags' => $this->whenLoaded('tags', fn () => $this->tags->pluck('name')->values()->all(), []),
            'share_links_count' => $this->whenCounted('share_links_count'),
            'shared_by' => $this->whenPivotLoaded('group_media', fn () => $this->pivot->shared_by),
            'created_at' => $this->created_at?->toIso8601String(),
            'created_label' => $this->created_at?->diffForHumans(),
            'can_edit' => $request->user()?->can('update', $this->resource) ?? false,
        ];
    }

    /** « 2:41 » ou « 1:06:03 ». */
    private function durationLabel(): ?string
    {
        if ($this->duration_seconds === null) {
            return null;
        }

        $seconds = (int) round($this->duration_seconds);
        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        $rest = $seconds % 60;

        return $hours > 0
            ? sprintf('%d:%02d:%02d', $hours, $minutes, $rest)
            : sprintf('%d:%02d', $minutes, $rest);
    }
}
