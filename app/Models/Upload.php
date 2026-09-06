<?php

namespace App\Models;

use Database\Factories\UploadFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * Un dépôt en cours, morceau par morceau. Il disparaît une fois le média créé,
 * ou purgé s'il est resté inachevé trop longtemps.
 */
#[Fillable([
    'uuid', 'user_id', 'original_name', 'mime_type', 'size_bytes', 'received_bytes',
    'next_chunk_index', 'chunk_bytes', 'part_path',
])]
class Upload extends Model
{
    /** @use HasFactory<UploadFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'received_bytes' => 'integer',
            'next_chunk_index' => 'integer',
            'chunk_bytes' => 'integer',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isComplete(): bool
    {
        return $this->received_bytes === $this->size_bytes;
    }

    public function absolutePartPath(): string
    {
        return Storage::disk('local')->path($this->part_path);
    }
}
