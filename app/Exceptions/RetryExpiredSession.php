<?php

namespace App\Exceptions;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Rattrape les sessions expirées pour les visites Inertia.
 *
 * Quand le jeton CSRF d'une page ne vaut plus rien — session vidée, onglet resté
 * ouvert trop longtemps —, Laravel répond une page « Page Expired » en HTML. Une
 * visite Inertia attend du JSON : elle ne sait rien en faire, et le bouton paraît
 * mort. On renvoie donc l'utilisateur sur sa page, qui se recharge avec un jeton
 * frais, en lui disant quoi faire.
 */
final class RetryExpiredSession
{
    /**
     * Statut « Page Expired » de Laravel. Il ne figure pas parmi les constantes de
     * Symfony : 419 n'est pas un code HTTP normalisé.
     */
    private const PAGE_EXPIRED = 419;

    public function __invoke(Response $response, Throwable $exception, Request $request): Response
    {
        if ($response->getStatusCode() !== self::PAGE_EXPIRED || ! $request->header('X-Inertia')) {
            return $response;
        }

        // Le 303 est celui qu'Inertia impose après un PUT, PATCH ou DELETE ; il
        // convient aussi après un POST.
        return back(Response::HTTP_SEE_OTHER)
            ->with('error', 'Ta session avait expiré. Réessaie, la page est à jour.');
    }
}
