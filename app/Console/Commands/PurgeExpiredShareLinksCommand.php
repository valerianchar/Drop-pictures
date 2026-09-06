<?php

namespace App\Console\Commands;

use App\Models\ShareLink;
use Illuminate\Console\Command;

class PurgeExpiredShareLinksCommand extends Command
{
    protected $signature = 'drop:purge-share-links';

    protected $description = 'Supprime les liens de partage à durée limitée arrivés à expiration';

    public function handle(): int
    {
        // Un lien expiré répond déjà 404 ; la purge ne fait que ranger la table.
        $count = ShareLink::query()->whereNotNull('expires_at')->where('expires_at', '<', now())->delete();

        $this->info("{$count} lien(s) expiré(s) supprimé(s).");

        return self::SUCCESS;
    }
}
