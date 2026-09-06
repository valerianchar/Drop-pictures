<?php

namespace App\Http\Controllers\Auth;

use App\Actions\JoinGroup;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Support\PendingInvitation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/Login');
    }

    public function store(LoginRequest $request, JoinGroup $joinGroup): RedirectResponse
    {
        $request->authenticate();
        $request->session()->regenerate();

        // Un lien de groupe cliqué avant de se connecter : on y entre maintenant.
        if (($pending = PendingInvitation::pull()) !== null) {
            [$group, $invitation] = $pending;
            $joinGroup->handle($group, $request->user(), $invitation);

            return redirect()->route('groups.show', $group)
                ->with('success', "Bon retour ! Tu es dans « {$group->name} ».");
        }

        return redirect()->intended(route('dashboard'))
            ->with('success', 'Bonne journée '.$request->user()->first_name.' !');
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
