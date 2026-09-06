<?php

namespace Database\Factories;

use App\Enums\MediaKind;
use App\Models\Media;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Media>
 */
class MediaFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $uuid = (string) Str::uuid();
        $name = 'IMG_'.fake()->numberBetween(1000, 9999).'.jpg';

        return [
            'user_id' => User::factory(),
            'uuid' => $uuid,
            'original_name' => $name,
            'extension' => 'jpg',
            'mime_type' => 'image/jpeg',
            'kind' => MediaKind::Photo,
            'size_bytes' => fake()->numberBetween(2_000_000, 30_000_000),
            'checksum_sha256' => hash('sha256', $uuid),
            'disk_path' => "media/1/{$uuid}/{$name}",
            'width' => 6000,
            'height' => 4000,
            'processed_at' => now(),
        ];
    }

    public function named(string $name): static
    {
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));

        return $this->state(fn (array $attributes) => [
            'original_name' => $name,
            'extension' => $extension,
            'kind' => MediaKind::fromExtension($extension),
            'disk_path' => "media/1/{$attributes['uuid']}/{$name}",
        ]);
    }

    public function video(int $width = 3840, int $height = 2160, float $frameRate = 60.0): static
    {
        return $this->state(fn (array $attributes) => [
            'original_name' => 'plage-drone.mp4',
            'extension' => 'mp4',
            'mime_type' => 'video/mp4',
            'kind' => MediaKind::Video,
            'width' => $width,
            'height' => $height,
            'duration_seconds' => 161,
            'frame_rate' => $frameRate,
            'disk_path' => "media/1/{$attributes['uuid']}/plage-drone.mp4",
        ]);
    }

    public function rawFile(): static
    {
        return $this->state(fn (array $attributes) => [
            'original_name' => 'nuit-lyon.cr3',
            'extension' => 'cr3',
            'mime_type' => 'application/octet-stream',
            'kind' => MediaKind::Photo,
            'width' => null,
            'height' => null,
            'disk_path' => "media/1/{$attributes['uuid']}/nuit-lyon.cr3",
        ]);
    }

    public function sized(int $bytes): static
    {
        return $this->state(fn () => ['size_bytes' => $bytes]);
    }
}
