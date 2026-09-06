<?php

namespace App\Actions;

use App\Models\Upload;
use App\Models\User;
use App\Support\FileSize;
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
    public function handle(User $user, string $originalName, int $sizeBytes, ?string $mimeType): Upload
    {
        $maxBytes = (int) config('drop.max_file_bytes');

        if ($sizeBytes > $maxBytes) {
            throw ValidationException::withMessages([
                'size' => 'Ce fichier dépasse la taille maximale de '.FileSize::format($maxBytes).'.',
            ]);
        }

        $quota = (int) config('drop.quota_bytes');
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
            'chunk_bytes' => (int) config('drop.chunk_bytes'),
            'part_path' => $partPath,
        ]);
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
