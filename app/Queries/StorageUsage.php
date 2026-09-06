<?php

namespace App\Queries;

use App\Models\User;
use App\Support\FileSize;
use App\Support\Limits;

final class StorageUsage
{
    /**
     * Ce que résume la ligne sous le titre : « 412 fichiers · 3,4 Go sur 10 Go ».
     *
     * @return array{count: int, used_bytes: int, quota_bytes: int, used_label: string, quota_label: string, ratio: float, saved_bytes: int, saved_label: ?string}
     */
    public function forUser(User $user): array
    {
        $count = $user->media()->count();
        $used = $user->usedBytes();
        $quota = Limits::quotaBytes();
        // Ce que l'archivage sans perte fait gagner sur le disque, en octets.
        $saved = (int) $user->media()->whereNotNull('archived_at')->selectRaw('COALESCE(SUM(size_bytes - archived_bytes), 0) as saved')->value('saved');

        return [
            'count' => $count,
            'used_bytes' => $used,
            'quota_bytes' => $quota,
            'used_label' => FileSize::format($used),
            'quota_label' => FileSize::format($quota),
            'ratio' => $quota > 0 ? round(min(1, $used / $quota), 3) : 0.0,
            'saved_bytes' => $saved,
            'saved_label' => $saved > 0 ? FileSize::format($saved) : null,
        ];
    }
}
