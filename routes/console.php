<?php

use App\Console\Commands\PurgeExpiredShareLinksCommand;
use App\Console\Commands\PurgeStaleUploadsCommand;
use Illuminate\Support\Facades\Schedule;

/*
 * Un dépôt interrompu laisse un fichier partiel sur le disque. Le passage est
 * horaire : un fichier de 5 Go abandonné ne doit pas peser un jour entier.
 */
Schedule::command(PurgeStaleUploadsCommand::class)
    ->hourly()
    ->withoutOverlapping();

/*
 * Les liens à durée limitée répondent 404 dès l'expiration ; la purge nocturne
 * ne fait que ranger la table.
 */
Schedule::command(PurgeExpiredShareLinksCommand::class)
    ->dailyAt('03:15')
    ->withoutOverlapping();
