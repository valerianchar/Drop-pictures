<?php

namespace App\Support;

use App\Enums\MediaKind;
use App\Jobs\RestoreMedia;
use App\Models\Media;
use Illuminate\Support\Facades\Process;
use RuntimeException;

/**
 * L'archivage sans perte : réduire la place d'un original sans en perdre un
 * seul octet, et savoir le reconstruire à l'identique.
 *
 * Deux outils, deux familles : cjxl recompresse un JPEG en JPEG XL avec ses
 * données de reconstruction — djxl rend alors le fichier .jpg d'origine, bit
 * pour bit — ; zstd compresse ce qui ne l'est pas déjà (RAW, TIFF, PNG…).
 * Les vidéos et les HEIC sont déjà au plus serré : on ne les touche pas.
 */
final class ColdStorage
{
    public const JXL = 'jxl';

    public const ZSTD = 'zstd';

    private const JPEG_EXTENSIONS = ['jpg', 'jpeg', 'jpe'];

    private const ZSTD_EXTENSIONS = [
        'png', 'tif', 'tiff', 'bmp', 'psd', 'dng', 'cr2', 'cr3', 'nef', 'nrw', 'arw', 'srf', 'sr2',
        'orf', 'rw2', 'raf', 'pef', 'x3f', '3fr', 'erf', 'kdc', 'mrw', 'mos', 'iiq', 'rwl', 'srw',
    ];

    public static function codecFor(Media $media): ?string
    {
        if ($media->kind !== MediaKind::Photo) {
            return null;
        }

        $extension = strtolower($media->extension);

        return match (true) {
            in_array($extension, self::JPEG_EXTENSIONS, true) => self::JXL,
            in_array($extension, self::ZSTD_EXTENSIONS, true) => self::ZSTD,
            default => null,
        };
    }

    /** Les outils sont-ils installés ? Sans eux, l'archivage se met en veille. */
    public static function available(): bool
    {
        $result = Process::timeout(10)->run(sprintf(
            'command -v %s >/dev/null && command -v %s >/dev/null && command -v %s >/dev/null',
            escapeshellarg((string) config('drop.cjxl')),
            escapeshellarg((string) config('drop.djxl')),
            escapeshellarg((string) config('drop.zstd')),
        ));

        return $result->successful();
    }

    public static function archivePath(Media $media, string $codec): string
    {
        return "archives/{$media->uuid}.".($codec === self::JXL ? 'jxl' : 'zst');
    }

    public static function compress(string $source, string $destination, string $codec): void
    {
        $result = match ($codec) {
            // --lossless_jpeg=1 : la recompression conserve les données de reconstruction du JPEG.
            self::JXL => Process::timeout(1800)->run([config('drop.cjxl'), $source, $destination, '--lossless_jpeg=1', '-e', '7', '--num_threads=2']),
            self::ZSTD => Process::timeout(1800)->run([config('drop.zstd'), '-19', '--long=27', '-T2', '-q', '-f', '-o', $destination, $source]),
            default => throw new RuntimeException("Codec inconnu : {$codec}"),
        };

        if (! $result->successful() || ! is_file($destination)) {
            @unlink($destination);

            throw new RuntimeException("Compression {$codec} impossible : ".trim($result->errorOutput()));
        }
    }

    public static function decompress(string $archive, string $destination, string $codec): void
    {
        $result = match ($codec) {
            self::JXL => Process::timeout(1800)->run([config('drop.djxl'), $archive, $destination]),
            self::ZSTD => Process::timeout(1800)->run([config('drop.zstd'), '-d', '-q', '-f', '-o', $destination, $archive]),
            default => throw new RuntimeException("Codec inconnu : {$codec}"),
        };

        if (! $result->successful() || ! is_file($destination)) {
            @unlink($destination);

            throw new RuntimeException("Décompression {$codec} impossible : ".trim($result->errorOutput()));
        }
    }

    /**
     * L'original, chaud : s'il est archivé, il est reconstruit tout de suite —
     * quelques secondes — avant d'être servi. Aucun chemin ne sert jamais
     * autre chose que le fichier d'origine.
     */
    public static function ensureHot(Media $media): Media
    {
        if ($media->isArchived()) {
            (new RestoreMedia($media))->handle();
            $media = $media->fresh() ?? $media;
        }

        $media->forceFill(['last_accessed_at' => now()])->saveQuietly();

        return $media;
    }
}
