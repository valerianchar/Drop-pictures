<?php

namespace App\Console\Commands;

use App\Models\Upload;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class PurgeStaleUploadsCommand extends Command
{
    protected $signature = 'drop:purge-uploads';

    protected $description = 'Supprime les dépôts restés inachevés trop longtemps, avec leur fichier partiel';

    public function handle(): int
    {
        $limit = now()->subHours((int) config('drop.stale_upload_hours'));
        $count = 0;

        Upload::query()->where('updated_at', '<', $limit)->each(function (Upload $upload) use (&$count): void {
            Storage::disk('local')->delete($upload->part_path);
            $upload->delete();
            $count++;
        });

        $this->info("{$count} dépôt(s) abandonné(s) purgé(s).");

        return self::SUCCESS;
    }
}
