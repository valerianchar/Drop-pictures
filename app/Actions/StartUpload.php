<?php

namespace App\Actions;

use App\Enums\MediaKind;
use App\Models\Upload;
use App\Models\User;
use App\Support\FileSize;
use App\Support\Limits;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Ouvre un dépôt : vérifie que le fichier tient sous la taille maximale, dans le
 * quota du compte et sur le disque du serveur, puis réserve un fichier partiel
 * où les morceaux viendront s'ajouter.
 */
final class StartUpload
{
    public function handle(User $user, string $originalName, int $sizeBytes, ?string $mimeType, ?int $wantedChunkBytes = null): Upload
    {
        $kind = MediaKind::fromExtension(pathinfo($originalName, PATHINFO_EXTENSION));
        $maxBytes = Limits::maxBytesFor($kind);

        if ($sizeBytes > $maxBytes) {
            throw ValidationException::withMessages([
                'size' => 'Ce fichier dépasse la taille maximale de '.FileSize::format($maxBytes).' pour une '.self::kindLabel($kind).'.',
            ]);
        }

        $quota = Limits::quotaBytes();
        $pending = (int) $user->uploads()->sum('size_bytes');

        if ($user->usedBytes() + $pending + $sizeBytes > $quota) {
            throw ValidationException::withMessages([
                'size' => 'Plus assez de place : il reste '.FileSize::format(max(0, $quota - $user->usedBytes() - $pending)).' sur '.FileSize::format($quota).'.',
            ]);
        }

        $this->ensureDiskCanHold($sizeBytes);

        $uuid = (string) Str::uuid();
        $partPath = "uploads/{$uuid}.part";

        Storage::disk('local')->makeDirectory('uploads');
        Storage::disk('local')->put($partPath, '');

        return $user->uploads()->create([
            'uuid' => $uuid,
            'original_name' => $this->sanitizeName($originalName),
            'mime_type' => $mimeType !== null ? Str::limit($mimeType, 120, '') : null,
            'size_bytes' => $sizeBytes,
            'chunk_bytes' => $this->chunkBytes($wantedChunkBytes),
            'part_path' => $partPath,
        ]);
    }

    /**
     * La taille de morceau retenue : celle demandée par le navigateur, sans
     * jamais dépasser celle du serveur — au-delà, un morceau ne passerait pas
     * `post_max_size`. C'est cette valeur, renvoyée à l'ouverture, qui définit
     * le découpage : le serveur en déduit la longueur attendue de chaque morceau
     * et son décalage dans le fichier.
     */
    private function chunkBytes(?int $wanted): int
    {
        $server = (int) config('drop.chunk_bytes');

        return $wanted === null ? $server : (int) max(256 * 1024, min($wanted, $server));
    }

    /**
     * Le quota par compte ne protège pas le disque : plusieurs comptes peuvent
     * le dépasser ensemble. On refuse un dépôt qui n'y tiendrait pas, en gardant
     * une réserve pour la base, les journaux et les aperçus — plutôt que de le
     * laisser échouer au dernier morceau, ou de mettre tout le serveur à genoux.
     */
    private function ensureDiskCanHold(int $sizeBytes): void
    {
        $free = @disk_free_space(Storage::disk('local')->path(''));

        if ($free === false) {
            return;
        }

        $reserve = (int) config('drop.disk_reserve_bytes');
        $available = (int) max(0, $free - $reserve);

        if ($sizeBytes > $available) {
            throw ValidationException::withMessages([
                'size' => 'Plus assez de place sur le serveur pour ce fichier : '.FileSize::format($available).' disponibles.',
            ]);
        }
    }

    private static function kindLabel(MediaKind $kind): string
    {
        return match ($kind) {
            MediaKind::Photo => 'photo',
            MediaKind::Video => 'vidéo',
            MediaKind::Autre => 'fichier de ce type',
        };
    }

    /**
     * Le nom d'origine est conservé — c'est lui que le destinataire retrouve —,
     * débarrassé seulement de ce qui ferait un chemin ou un caractère de contrôle.
     */
    private function sanitizeName(string $name): string
    {
        $name = basename(str_replace('\\', '/', $name));
        $name = preg_replace('/[\x00-\x1F\x7F]/u', '', $name) ?? $name;
        $name = trim($name);

        return $name === '' || $name === '.' || $name === '..' ? 'fichier' : Str::limit($name, 200, '');
    }
}
