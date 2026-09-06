<?php

namespace App\Models;

use Database\Factories\GroupInvitationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Une invitation nominative envoyée par e-mail. Elle porte son propre jeton :
 * l'invité arrive dans le groupe en cliquant, et l'invitation se marque
 * acceptée — le lien ne sert plus ensuite.
 */
#[Fillable(['group_id', 'invited_by', 'email', 'token', 'accepted_at'])]
class GroupInvitation extends Model
{
    /** @use HasFactory<GroupInvitationFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'accepted_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Group, $this> */
    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    /** @return BelongsTo<User, $this> */
    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function isAccepted(): bool
    {
        return $this->accepted_at !== null;
    }

    public function url(): string
    {
        return route('invitations.show', $this->token);
    }
}
