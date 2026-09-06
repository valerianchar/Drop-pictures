<?php

namespace App\Support;

use App\Models\Media;

/**
 * Ce que dit le badge « Original · … » d'un fichier : la preuve, en un mot, qu'il
 * est là dans sa définition d'origine. 4K 60 pour une vidéo, RAW ou TIFF pour un
 * fichier de travail, 24 Mpx pour une photo classique.
 */
final class QualityLabel
{
    public static function for(Media $media): string
    {
        if ($media->isRaw()) {
            return 'RAW';
        }

        if (in_array($media->extension, ['tif', 'tiff'], true)) {
            return 'TIFF';
        }

        if ($media->isVideo()) {
            return self::forVideo($media);
        }

        if ($media->width !== null && $media->height !== null && $media->width * $media->height > 0) {
            return self::megapixels($media->width * $media->height);
        }

        return strtoupper($media->extension);
    }

    private static function forVideo(Media $media): string
    {
        // La définition se lit sur le plus petit côté : une vidéo verticale 2160×3840 reste de la 4K.
        $shortSide = min($media->width ?? 0, $media->height ?? 0);

        $definition = match (true) {
            $shortSide >= 4320 => '8K',
            $shortSide >= 2160 => '4K',
            $shortSide >= 1440 => '1440p',
            $shortSide >= 1080 => '1080p',
            $shortSide >= 720 => '720p',
            $shortSide > 0 => $shortSide.'p',
            default => strtoupper($media->extension),
        };

        if (in_array($media->extension, ['mov', 'mxf'], true) && str_contains(strtolower($media->mime_type), 'prores')) {
            $definition .= ' ProRes';
        }

        // Au-delà du 30 i/s, la cadence fait partie de la qualité (« 4K 60 »).
        if ($media->frame_rate !== null && $media->frame_rate >= 48) {
            $definition .= ' '.(int) round($media->frame_rate);
        }

        return $definition;
    }

    private static function megapixels(int $pixels): string
    {
        $megapixels = $pixels / 1_000_000;

        if ($megapixels < 1) {
            return number_format($megapixels, 1, ',', ' ').' Mpx';
        }

        return (int) round($megapixels).' Mpx';
    }
}
