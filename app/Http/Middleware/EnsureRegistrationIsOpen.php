<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ferme l'écran d'inscription quand l'instance ne veut plus d'inconnus.
 *
 * Une invitation de groupe en attente dans la session garde la porte ouverte :
 * l'invité a été convié nommément, il n'est pas un inconnu.
 */
class EnsureRegistrationIsOpen
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('drop.registration_open') && ! $request->session()->has('invitation')) {
            abort(404);
        }

        return $next($request);
    }
}
