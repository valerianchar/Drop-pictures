<?php

namespace App\Actions;

use App\Models\Media;
use App\Models\ShareLink;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Crée un lien public vers un média. Le fichier servi par ce lien est le fichier
 * d'origine — le lien ne fait que donner accès, il ne produit aucune version.
 */
final class CreateShareLink
{
    public function handle(Media $media, User $user, bool $limited): ShareLink
    {
        return $media->shareLinks()->create([
            'user_id' => $user->id,
            'token' => $this->uniqueToken(),
            'expires_at' => $limited ? now()->addDays((int) config('drop.share_link_days')) : null,
        ]);
    }

    /**
     * Jeton court mais imprévisible : 16 caractères alphanumériques, ~95 bits.
     * Il est l'unique clé d'accès du destinataire ; il ne doit pas se deviner.
     */
    private function uniqueToken(): string
    {
        do {
            $token = Str::random(16);
        } while (ShareLink::query()->where('token', $token)->exists());

        return $token;
    }
}
