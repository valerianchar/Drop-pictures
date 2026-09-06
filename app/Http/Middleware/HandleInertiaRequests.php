<?php

namespace App\Http\Middleware;

use App\Support\Limits;
use App\Support\PendingInvitation;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user === null ? null : [
                    'name' => $user->name,
                    'first_name' => $user->first_name,
                    'email' => $user->email,
                    'initials' => $user->initials,
                    'is_admin' => $user->is_admin,
                ],
            ],
            'flash' => [
                'success' => fn (): ?string => $request->session()->get('success'),
                'error' => fn (): ?string => $request->session()->get('error'),
                // Le lien tout juste créé, pour l'afficher dans le dialogue de partage.
                'share_link' => fn (): ?array => $request->session()->get('share_link'),
            ],
            'registration_open' => config('drop.registration_open'),
            'upload' => [
                'chunk_bytes' => (int) config('drop.chunk_bytes'),
                // La plus grande des limites, pour les textes ; le serveur juge fichier par fichier.
                'max_file_bytes' => Limits::maxBytes(),
            ],
            // Le groupe qui attend derrière l'écran de connexion ou d'inscription.
            'pending_group' => fn (): ?string => PendingInvitation::groupName(),
        ];
    }
}
