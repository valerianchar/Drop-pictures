<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Les réglages de l'instance conservés en base, lus en une requête et mis en
 * cache : ils sont consultés à chaque ouverture de dépôt.
 */
final class Settings
{
    private const CACHE_KEY = 'drop.settings';

    public static function get(string $key, mixed $default = null): mixed
    {
        return self::all()[$key] ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        DB::table('settings')->updateOrInsert(
            ['key' => $key],
            ['value' => $value === null ? null : (string) $value, 'updated_at' => now(), 'created_at' => now()],
        );

        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return array<string, ?string>
     */
    public static function all(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, fn (): array => DB::table('settings')
            ->pluck('value', 'key')
            ->all());
    }
}
