<?php

namespace App\Http\Controllers\Auth;

use App\Actions\JoinGroup;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use App\Support\PendingInvitation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/Register');
    }

    public function store(RegisterRequest $request, JoinGroup $joinGroup): RedirectResponse
    {
        // Le premier compte créé administre l'instance : c'est lui qui règle les limites.
        $user = User::create([
            ...$request->only('name', 'email', 'password'),
            'is_admin' => User::query()->doesntExist(),
        ]);

        Auth::login($user);
        $request->session()->regenerate();

        // L'invité « crée un compte en un clic et arrive directement dans le groupe ».
        if (($pending = PendingInvitation::pull()) !== null) {
            [$group, $invitation] = $pending;
            $joinGroup->handle($group, $user, $invitation);

            return redirect()->route('groups.show', $group)
                ->with('success', "Bienvenue {$user->first_name} ! Tu es dans « {$group->name} ».");
        }

        return redirect()->route('dashboard')
            ->with('success', 'Bienvenue sur Drop Picture ! Tes fichiers resteront en qualité d’origine.');
    }
}
