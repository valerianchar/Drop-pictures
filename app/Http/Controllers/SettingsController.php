<?php

namespace App\Http\Controllers;

use App\Enums\MediaKind;
use App\Http\Requests\UpdateSettingsRequest;
use App\Support\FileSize;
use App\Support\Limits;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Les réglages de l'instance, réservés à l'administrateur : les limites de
 * dépôt, à côté de ce que le disque du serveur peut réellement accueillir.
 */
class SettingsController extends Controller
{
    private const GIB = 1024 * 1024 * 1024;

    public function edit(): Response
    {
        $limits = Limits::all();
        $free = @disk_free_space(Storage::disk('local')->path(''));
        $reserve = (int) config('drop.disk_reserve_bytes');

        return Inertia::render('Settings/Edit', [
            'limits' => [
                'quota_gb' => round($limits['quota_bytes'] / self::GIB, 1),
                'photo_gb' => round($limits['photo_bytes'] / self::GIB, 1),
                'video_gb' => round($limits['video_bytes'] / self::GIB, 1),
                'autre_gb' => round($limits['autre_bytes'] / self::GIB, 1),
            ],
            'disk' => [
                'free_label' => $free === false ? null : FileSize::format((int) $free),
                'usable_label' => $free === false ? null : FileSize::format((int) max(0, $free - $reserve)),
                'reserve_label' => FileSize::format($reserve),
            ],
            'update_url' => route('settings.update'),
        ]);
    }

    public function update(UpdateSettingsRequest $request): RedirectResponse
    {
        Settings::set('quota_bytes', $request->quotaBytes());

        foreach (MediaKind::cases() as $kind) {
            Settings::set(Limits::keyFor($kind), $request->maxBytesFor($kind));
        }

        return back()->with('success', 'Limites enregistrées — elles s’appliquent aux prochains dépôts.');
    }
}
