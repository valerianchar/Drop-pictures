<?php

namespace App\Http\Controllers;

use App\Actions\RecordMediaDownload;
use App\Enums\DownloadChannel;
use App\Http\Requests\MarkMediaDownloadedRequest;
use App\Models\Media;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/**
 * Ce que seul le navigateur sait : la feuille de partage d'iOS a bien accepté
 * les fichiers, ils sont dans la photothèque. Le serveur ne peut pas le
 * deviner — un dépôt dans Photos ne passe par aucune de ses routes —, alors la
 * page le lui dit, et la pastille « Dans Photos » apparaît sur les cartes.
 */
class MediaDownloadController extends Controller
{
    public function store(MarkMediaDownloadedRequest $request, RecordMediaDownload $record): Response
    {
        $media = Media::query()->whereIn('id', $request->mediaIds())->get();

        $media->each(fn (Media $item) => Gate::authorize('view', $item));

        $record->many($media, $request->user(), DownloadChannel::Photos);

        return response()->noContent();
    }
}
