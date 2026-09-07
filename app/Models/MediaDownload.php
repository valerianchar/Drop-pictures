<?php

namespace App\Models;

use App\Enums\DownloadChannel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * « Celui-là, je l'ai déjà récupéré. » Une ligne par fichier et par personne :
 * le premier passage, le dernier, combien de fois, et par quel chemin.
 */
#[Fillable(['media_id', 'user_id', 'via', 'times', 'first_at', 'last_at'])]
class MediaDownload extends Model
{
    public $timestamps = false;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'via' => DownloadChannel::class,
            'times' => 'integer',
            'first_at' => 'datetime',
            'last_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Media, $this> */
    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Ce qu'en montre la carte et la fiche du fichier.
     *
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        return [
            'via' => $this->via->value,
            'label' => $this->via->label(),
            'at' => $this->last_at->toIso8601String(),
            'at_label' => $this->last_at->translatedFormat('j M \à H\hi'),
            'times' => $this->times,
        ];
    }
}
