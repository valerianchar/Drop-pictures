<?php

namespace App\Models;

use App\Enums\MediaKind;
use App\Support\QualityLabel;
use Database\Factories\MediaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

/**
 * Un fichier déposé, conservé octet pour octet.
 *
 * Le chemin sur le disque pointe vers le fichier d'origine ; rien ne l'écrit
 * jamais après le dépôt. Les dimensions, la durée et l'aperçu sont lus ou
 * dérivés à part, sans le modifier.
 */
#[Fillable([
    'user_id', 'uuid', 'original_name', 'extension', 'mime_type', 'kind', 'size_bytes',
    'checksum_sha256', 'disk_path', 'width', 'height', 'duration_seconds', 'frame_rate',
    'thumbnail_path', 'processed_at',
])]
class Media extends Model
{
    /** @use HasFactory<MediaFactory> */
    use HasFactory;

    protected $table = 'media';

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'kind' => MediaKind::class,
            'size_bytes' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'duration_seconds' => 'float',
            'frame_rate' => 'float',
            'processed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsToMany<Tag, $this> */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    /** @return HasMany<ShareLink, $this> */
    public function shareLinks(): HasMany
    {
        return $this->hasMany(ShareLink::class);
    }

    /** @return BelongsToMany<Group, $this> */
    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(Group::class, 'group_media')->withPivot('shared_by', 'uploaded_here')->withTimestamps();
    }

    public function isVideo(): bool
    {
        return $this->kind === MediaKind::Video;
    }

    public function isRaw(): bool
    {
        return MediaKind::isRaw($this->extension);
    }

    public function hasThumbnail(): bool
    {
        return $this->thumbnail_path !== null;
    }

    /**
     * Chemin absolu du fichier d'origine sur le disque local.
     */
    public function absolutePath(): string
    {
        return Storage::disk('local')->path($this->disk_path);
    }

    /**
     * Ce qui suit « Original · » sur le badge : 4K 60, RAW, 24 Mpx…
     */
    protected function qualityLabel(): Attribute
    {
        return Attribute::get(fn (): string => QualityLabel::for($this));
    }

    /**
     * L'utilisateur peut voir et télécharger ce média s'il en est le déposant
     * ou s'il appartient à un groupe où il a été partagé.
     */
    public function isAccessibleBy(User $user): bool
    {
        if ($this->user_id === $user->id) {
            return true;
        }

        return $this->groups()
            ->whereIn('groups.id', GroupMember::query()->select('group_id')->where('user_id', $user->id))
            ->exists();
    }
}
