<?php

namespace App\Support;

/**
 * Le type MIME d'un fichier, d'après son extension quand le navigateur ne l'a
 * pas fourni. Il ne sert qu'aux en-têtes de réponse — Safari, par exemple,
 * n'affiche une image inline (pour « Enregistrer dans Photos ») que si le type
 * annoncé est un image/* — et jamais à transformer le fichier.
 */
final class MimeType
{
    private const BY_EXTENSION = [
        'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'jpe' => 'image/jpeg',
        'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp', 'avif' => 'image/avif',
        'heic' => 'image/heic', 'heif' => 'image/heif', 'tif' => 'image/tiff', 'tiff' => 'image/tiff',
        'bmp' => 'image/bmp', 'svg' => 'image/svg+xml', 'psd' => 'image/vnd.adobe.photoshop', 'jxl' => 'image/jxl',
        'dng' => 'image/x-adobe-dng', 'cr2' => 'image/x-canon-cr2', 'cr3' => 'image/x-canon-cr3',
        'nef' => 'image/x-nikon-nef', 'arw' => 'image/x-sony-arw', 'raf' => 'image/x-fuji-raf',
        'orf' => 'image/x-olympus-orf', 'rw2' => 'image/x-panasonic-rw2', 'pef' => 'image/x-pentax-pef',
        'mp4' => 'video/mp4', 'm4v' => 'video/mp4', 'mov' => 'video/quicktime', 'mkv' => 'video/x-matroska',
        'webm' => 'video/webm', 'avi' => 'video/x-msvideo', 'mts' => 'video/mp2t', 'm2ts' => 'video/mp2t',
        'mxf' => 'application/mxf', '3gp' => 'video/3gpp', 'mpg' => 'video/mpeg', 'mpeg' => 'video/mpeg',
        'wmv' => 'video/x-ms-wmv',
    ];

    public static function guess(string $extension): string
    {
        return self::BY_EXTENSION[strtolower($extension)] ?? 'application/octet-stream';
    }

    /**
     * Le type annoncé par le navigateur quand il en a un ; sinon celui déduit de
     * l'extension. « application/octet-stream » compte comme une absence.
     */
    public static function resolve(?string $clientType, string $extension): string
    {
        $clientType = trim((string) $clientType);

        if ($clientType !== '' && $clientType !== 'application/octet-stream') {
            return $clientType;
        }

        return self::guess($extension);
    }
}
