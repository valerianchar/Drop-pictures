<?php

namespace App\Models;

use App\Enums\GroupRole;
use Database\Factories\GroupFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['owner_id', 'name', 'invite_token', 'expires_at'])]
class Group extends Model
{
    /** @use HasFactory<GroupFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
        ];
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /** @return HasMany<GroupMember, $this> */
    public function memberships(): HasMany
    {
        return $this->hasMany(GroupMember::class);
    }

    /** @return BelongsToMany<User, $this> */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'group_members')->withPivot('role')->withTimestamps();
    }

    /** @return HasMany<GroupInvitation, $this> */
    public function invitations(): HasMany
    {
        return $this->hasMany(GroupInvitation::class);
    }

    /** @return BelongsToMany<Media, $this> */
    public function media(): BelongsToMany
    {
        return $this->belongsToMany(Media::class, 'group_media')->withPivot('shared_by', 'uploaded_here')->withTimestamps();
    }

    public function hasMember(User $user): bool
    {
        return $this->memberships()->where('user_id', $user->id)->exists();
    }

    public function isOwnedBy(User $user): bool
    {
        return $this->owner_id === $user->id;
    }

    /**
     * Ajoute un membre, sans doublon : rejoindre deux fois le même groupe par
     * deux liens différents ne crée qu'une seule appartenance.
     */
    public function addMember(User $user, GroupRole $role = GroupRole::Membre): GroupMember
    {
        return $this->memberships()->firstOrCreate(
            ['user_id' => $user->id],
            ['role' => $role->value],
        );
    }

    /**
     * Adresse du lien d'invitation, celle que le propriétaire copie.
     */
    public function inviteUrl(): string
    {
        return route('groups.join', $this->invite_token);
    }
}
