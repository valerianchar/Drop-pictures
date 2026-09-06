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
 * Un groupe à durée de vie se clôt à l'heure dite, pas au lendemain : ses
 * fichiers déposés sont détruits, c'est une promesse faite aux membres.
 */
Schedule::command(ExpireGroupsCommand::class)
    ->hourly()
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
