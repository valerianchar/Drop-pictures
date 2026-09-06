<?php

namespace App\Providers;

use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Les libellés de dates de l'interface sont en français (« il y a 2 h », « 6 sept. »).
        Carbon::setLocale(config('app.locale'));
        Date::use(CarbonImmutable::class);

        $this->keepGeneratedUrlsOnTheSameSchemeAsTheSite();
    }

    /**
     * Chez un hébergeur qui termine le TLS devant l'application, celle-ci reçoit du
     * HTTP en interne et fabriquerait des URL en clair — le navigateur les
     * refuserait sur une page servie en HTTPS, et les liens de partage envoyés
     * par e-mail seraient faux.
     *
     * Le schéma est déduit de APP_URL plutôt que des en-têtes X-Forwarded-*, qu'il
     * faudrait alors déclarer dignes de confiance : n'importe qui pourrait y
     * annoncer une fausse adresse IP et contourner la limite de tentatives de
     * connexion.
     */
    private function keepGeneratedUrlsOnTheSameSchemeAsTheSite(): void
    {
        if (Str::startsWith(config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }
    }
}
