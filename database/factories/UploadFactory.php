<?php

namespace Database\Factories;

use App\Models\Upload;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Upload>
 */
class UploadFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $uuid = (string) Str::uuid();

        return [
            'uuid' => $uuid,
            'user_id' => User::factory(),
            'original_name' => 'IMG_2041.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 1024,
            'received_bytes' => 0,
            'next_chunk_index' => 0,
            'chunk_bytes' => 512,
            'part_path' => "uploads/{$uuid}.part",
        ];
    }
}
