<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\Media;
use App\Support\ColdStorage;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipStream\CompressionMethod;
use ZipStream\ZipStream;

/**
 * Plusieurs originaux d'un coup, dans un ZIP fabriqué en flux — méthode
 * « store », sans aucune compression : chaque entrée du ZIP est le fichier
 * d'origine, octet pour octet, et rien ne passe par la mémoire de PHP.
 */
class ZipDownloadController extends Controller
{
    private const MAX_FILES = 500;

    /** Tout le groupe. */
    public function group(Group $group): StreamedResponse
    {
        Gate::authorize('view', $group);

        $media = $group->media()->orderBy('group_media.created_at')->get();

        abort_if($media->isEmpty(), 404, 'Ce groupe ne contient aucun fichier.');

        return $this->zip($media, Str::slug($group->name) ?: 'groupe');
    }

    /** Une sélection de la galerie : ?ids[]=… */
    public function many(Request $request): StreamedResponse
    {
        $ids = collect($request->query('ids', []))
            ->map(fn ($id): int => (int) $id)
            ->filter()
            ->unique()
            ->take(self::MAX_FILES);

        abort_if($ids->isEmpty(), 404, 'Aucun fichier sélectionné.');

        $media = Media::query()->whereIn('id', $ids)->get();

        abort_if($media->count() !== $ids->count(), 404);
        $media->each(fn (Media $item) => Gate::authorize('view', $item));

        return $this->zip($media, 'drop-picture-'.now()->format('Y-m-d-Hi'));
    }

    /**
     * @param  Collection<int, Media>  $media
     */
    private function zip(Collection $media, string $name): StreamedResponse
    {
        $entries = $this->uniqueNames($media);

        return response()->streamDownload(function () use ($entries): void {
            $zip = new ZipStream(
                sendHttpHeaders: false,
                defaultCompressionMethod: CompressionMethod::STORE,
                enableZip64: true,
                flushOutput: true,
            );

            foreach ($entries as $entryName => $item) {
                // Un original archivé est reconstruit juste avant d'entrer dans le ZIP.
                $zip->addFileFromPath(fileName: $entryName, path: ColdStorage::ensureHot($item)->absolutePath());
            }

            $zip->finish();
        }, "{$name}.zip", [
            'Content-Type' => 'application/zip',
            'Cache-Control' => 'private, no-transform',
            // Traefik et Caddy ne mettent pas le flux en tampon : le ZIP part au fil de l'eau.
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /**
     * Deux fichiers peuvent porter le même nom : le second reçoit un suffixe,
     * l'original sur le disque n'est bien sûr pas renommé.
     *
     * @param  Collection<int, Media>  $media
     * @return array<string, Media>
     */
    private function uniqueNames(Collection $media): array
    {
        $entries = [];

        foreach ($media as $item) {
            $name = $item->original_name;
            $base = pathinfo($name, PATHINFO_FILENAME);
            $extension = pathinfo($name, PATHINFO_EXTENSION);

            for ($n = 2; isset($entries[$name]); $n++) {
                $name = $base." ({$n})".($extension !== '' ? ".{$extension}" : '');
            }

            $entries[$name] = $item;
        }

        return $entries;
    }
}
