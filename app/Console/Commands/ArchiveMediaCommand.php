<?php

namespace App\Console\Commands;

use App\Jobs\ArchiveMedia;
use App\Models\Media;
use App\Support\ColdStorage;
use App\Support\Limits;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

class ArchiveMediaCommand extends Command
{
    protected $signature = 'drop:archive {--limit= : Nombre de fichiers à traiter (défaut : drop.archive_batch)}';

    protected $description = 'Archive sans perte les photos restées sans consultation au-delà du délai réglé';

    public function handle(): int
    {
        $days = Limits::archiveAfterDays();

        if ($days <= 0) {
            $this->info('Archivage désactivé (délai à 0).');

            return self::SUCCESS;
        }

        if (! ColdStorage::available()) {
            $this->warn('Outils d’archivage absents (cjxl, djxl, zstd) : rien n’est archivé.');

            return self::SUCCESS;
        }

        $threshold = now()->subDays($days);
        $limit = (int) ($this->option('limit') ?? config('drop.archive_batch'));

        $candidates = Media::query()
            ->whereNull('archived_at')
            ->whereNull('archive_skipped_at')
            ->whereNull('restoring_at')
            ->where('kind', 'photo')
            ->where('size_bytes', '>=', (int) config('drop.archive_min_bytes'))
            // Sans consultation depuis le délai : la dernière ouverture compte, sinon le dépôt.
            ->whereRaw('COALESCE(last_accessed_at, created_at) < ?', [$threshold])
            // Un fichier déposé dans un groupe qui se clôt bientôt sera détruit : inutile de l'archiver.
            ->whereDoesntHave('groups', fn (Builder $query) => $query
                ->whereNotNull('groups.expires_at')
                ->where('groups.expires_at', '<', now()->addDays($days)))
            ->orderBy('id')
            ->limit($limit)
            ->get();

        foreach ($candidates as $media) {
            ArchiveMedia::dispatch($media);
        }

        $this->info("{$candidates->count()} fichier(s) mis en file d’archivage (délai {$days} j).");

        return self::SUCCESS;
    }
}
