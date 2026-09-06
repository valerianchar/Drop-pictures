<?php

namespace App\Support;

use App\Enums\MediaKind;

/**
 * Les limites de dépôt effectives : un réglage posé dans l'application l'emporte
 * sur la valeur de config/drop.php (elle-même issue du .env).
 */
final class Limits
{
    public static function quotaBytes(): int
    {
        return (int) Settings::get('quota_bytes', config('drop.quota_bytes'));
    }

    /**
     * La taille maximale d'un fichier dépend de sa famille : on peut vouloir
     * 50 Go pour une vidéo et rester raisonnable sur une photo.
     */
    public static function maxBytesFor(MediaKind $kind): int
    {
        return (int) Settings::get(self::keyFor($kind), config('drop.max_file_bytes'));
    }

    /**
     * La plus grande des trois : ce que l'interface annonce (« jusqu'à 50 Go »).
     */
    public static function maxBytes(): int
    {
        return max(array_map(fn (MediaKind $kind): int => self::maxBytesFor($kind), MediaKind::cases()));
    }

    /**
     * @return array{quota_bytes: int, photo_bytes: int, video_bytes: int, autre_bytes: int}
     */
    public static function all(): array
    {
        return [
            'quota_bytes' => self::quotaBytes(),
            'photo_bytes' => self::maxBytesFor(MediaKind::Photo),
            'video_bytes' => self::maxBytesFor(MediaKind::Video),
            'autre_bytes' => self::maxBytesFor(MediaKind::Autre),
        ];
    }

    /** Jours sans consultation avant archivage sans perte ; 0 : jamais. */
    public static function archiveAfterDays(): int
    {
        return (int) Settings::get('archive_after_days', config('drop.archive_after_days'));
    }

    public static function keyFor(MediaKind $kind): string
    {
        return "max_{$kind->value}_bytes";
    }
}
