<?php

namespace App\Enums;

/**
 * Famille d'un fichier déposé. Elle ne préjuge de rien sur son traitement — tout
 * est conservé tel quel — ; elle sert aux filtres de la galerie et au choix de
 * l'aperçu.
 */
enum MediaKind: string
{
    case Photo = 'photo';
    case Video = 'video';
    case Autre = 'autre';

    /** Extensions RAW des principaux constructeurs, plus le DNG d'Adobe. */
    private const RAW_EXTENSIONS = [
        'raw', 'dng', 'cr2', 'cr3', 'nef', 'nrw', 'arw', 'srf', 'sr2', 'orf', 'rw2',
        'raf', 'pef', 'x3f', '3fr', 'erf', 'kdc', 'mrw', 'mos', 'iiq', 'rwl', 'srw',
    ];

    private const PHOTO_EXTENSIONS = [
        'jpg', 'jpeg', 'jpe', 'png', 'gif', 'webp', 'avif', 'heic', 'heif', 'tif', 'tiff', 'bmp', 'psd', 'svg', 'jxl',
    ];

    private const VIDEO_EXTENSIONS = [
        'mp4', 'm4v', 'mov', 'mkv', 'webm', 'avi', 'mts', 'm2ts', 'mxf', 'braw', 'r3d', 'wmv', 'mpg', 'mpeg', '3gp',
    ];

    public static function fromExtension(string $extension): self
    {
        $extension = strtolower($extension);

        return match (true) {
            in_array($extension, self::PHOTO_EXTENSIONS, true),
            in_array($extension, self::RAW_EXTENSIONS, true) => self::Photo,
            in_array($extension, self::VIDEO_EXTENSIONS, true) => self::Video,
            default => self::Autre,
        };
    }

    public static function isRaw(string $extension): bool
    {
        return in_array(strtolower($extension), self::RAW_EXTENSIONS, true);
    }

    public function label(): string
    {
        return match ($this) {
            self::Photo => 'Photos',
            self::Video => 'Vidéos',
            self::Autre => 'Autres',
        };
    }

    /**
     * Les filtres de type de la galerie, dans l'ordre d'affichage.
     *
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $kind): array => [
            'value' => $kind->value,
            'label' => $kind->label(),
        ], self::cases());
    }
}
