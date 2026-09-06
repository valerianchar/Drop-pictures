<?php

use App\Console\Commands\ArchiveMediaCommand;
use App\Console\Commands\ExpireGroupsCommand;
use App\Console\Commands\PurgeExpiredShareLinksCommand;
use App\Console\Commands\PurgeStaleUploadsCommand;
use Illuminate\Support\Facades\Schedule;

/*
 * L'archivage sans perte tourne la nuit, quand le processeur ne manque à
 * personne : recompresser un JPEG en JPEG XL prend quelques secondes chacun.
 */
Schedule::command(ArchiveMediaCommand::class)
    ->dailyAt('04:00')
    ->withoutOverlapping();

/*
 * Un groupe arrivé à échéance est fermé à l'instant même par les autorisations ;
 * le passage ne fait que ranger — détruire ce qui doit l'être, supprimer le
 * groupe — et n'a pas besoin d'attendre l'heure pleine.
 */
Schedule::command(ExpireGroupsCommand::class)
    ->everyFiveMinutes()
    ->withoutOverlapping();

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
