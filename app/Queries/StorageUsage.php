<?php

namespace App\Queries;

use App\Models\User;
use App\Support\FileSize;

final class StorageUsage
{
    /**
     * Ce que résume la ligne sous le titre : « 412 fichiers · 3,4 Go sur 10 Go ».
     *
     * @return array{count: int, used_bytes: int, quota_bytes: int, used_label: string, quota_label: string, ratio: float}
     */
    public function forUser(User $user): array
    {
        $count = $user->media()->count();
        $used = $user->usedBytes();
        $quota = (int) config('drop.quota_bytes');

        return [
            'count' => $count,
            'used_bytes' => $used,
            'quota_bytes' => $quota,
            'used_label' => FileSize::format($used),
            'quota_label' => FileSize::format($quota),
            'ratio' => $quota > 0 ? round(min(1, $used / $quota), 3) : 0.0,
        ];
    }
}
