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
    'next_chunk_index', 'chunk_bytes', 'hash_state', 'arrived', 'part_path',
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

    /** Combien de morceaux composent ce fichier. */
    public function chunkCount(): int
    {
        return max(1, (int) ceil($this->size_bytes / $this->chunk_bytes));
    }

    /** La longueur attendue d'un morceau — le dernier est plus court. */
    public function chunkLength(int $index): int
    {
        return (int) min($this->chunk_bytes, $this->size_bytes - $index * $this->chunk_bytes);
    }

    public function chunkOffset(int $index): int
    {
        return $index * $this->chunk_bytes;
    }

    /**
     * Le masque des morceaux arrivés. Un dépôt ouvert avant l'envoi parallèle
     * n'en a pas : ses morceaux étaient forcément contigus, on le reconstitue
     * depuis le compteur.
     */
    public function arrivedMask(): string
    {
        $count = $this->chunkCount();

        if ($this->arrived === null || strlen($this->arrived) !== $count) {
            return str_pad(str_repeat('1', min($count, $this->next_chunk_index)), $count, '0');
        }

        return $this->arrived;
    }

    public function hasArrived(int $index): bool
    {
        return ($this->arrivedMask()[$index] ?? '0') === '1';
    }

    /** Les morceaux qui manquent encore — ce qu'un envoi repris doit renvoyer. */
    public function missingChunks(): array
    {
        $mask = $this->arrivedMask();

        return array_values(array_filter(range(0, $this->chunkCount() - 1), fn (int $index): bool => $mask[$index] === '0'));
    }

    /** Les octets réellement reçus, déduits du masque. */
    public function bytesFromMask(string $mask): int
    {
        $bytes = 0;

        for ($index = 0, $count = $this->chunkCount(); $index < $count; $index++) {
            if ($mask[$index] === '1') {
                $bytes += $this->chunkLength($index);
            }
        }

        return $bytes;
    }

    public function absolutePartPath(): string
    {
        return Storage::disk('local')->path($this->part_path);
    }
}
