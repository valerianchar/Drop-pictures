<?php

namespace App\Support;

use App\Enums\MediaKind;
use Illuminate\Support\Facades\Process;

/**
 * Lit les caractéristiques d'un fichier — dimensions, durée, cadence — et en
 * dérive un aperçu réduit. Tout se fait en LECTURE du fichier d'origine : rien
 * ici ne l'écrit, ne le réencode ni ne le déplace.
 */
final class MediaProbe
{
    /**
     * @return array{width: ?int, height: ?int, duration_seconds: ?float, frame_rate: ?float}
     */
    public function inspect(string $path, MediaKind $kind, string $extension): array
    {
        $empty = ['width' => null, 'height' => null, 'duration_seconds' => null, 'frame_rate' => null];

        if ($kind === MediaKind::Video) {
            return $this->inspectVideo($path) ?? $empty;
        }

        if ($kind === MediaKind::Photo && ! MediaKind::isRaw($extension)) {
            $size = @getimagesize($path);

            if (is_array($size) && $size[0] > 0 && $size[1] > 0) {
                return ['width' => $size[0], 'height' => $size[1], 'duration_seconds' => null, 'frame_rate' => null];
            }
        }

        return $empty;
    }

    /**
     * Écrit un aperçu JPEG réduit à `$destination`. Vrai si un aperçu a pu être
     * produit ; faux pour les formats que ni GD ni ffmpeg ne lisent (RAW, PSD…),
     * qui garderont une tuile neutre dans la galerie.
     */
    public function thumbnail(string $source, string $destination, MediaKind $kind, int $width): bool
    {
        return match ($kind) {
            MediaKind::Photo => $this->imageThumbnail($source, $destination, $width),
            MediaKind::Video => $this->videoThumbnail($source, $destination, $width),
            MediaKind::Autre => false,
        };
    }

    /**
     * @return array{width: ?int, height: ?int, duration_seconds: ?float, frame_rate: ?float}|null
     */
    private function inspectVideo(string $path): ?array
    {
        $ffprobe = config('drop.ffprobe');

        if (! is_string($ffprobe) || $ffprobe === '') {
            return null;
        }

        $result = Process::timeout(60)->run([
            $ffprobe, '-v', 'error', '-select_streams', 'v:0',
            '-show_entries', 'stream=width,height,r_frame_rate:format=duration',
            '-of', 'json', $path,
        ]);

        if (! $result->successful()) {
            return null;
        }

        $data = json_decode($result->output(), true);
        $stream = $data['streams'][0] ?? [];

        return [
            'width' => isset($stream['width']) ? (int) $stream['width'] : null,
            'height' => isset($stream['height']) ? (int) $stream['height'] : null,
            'duration_seconds' => isset($data['format']['duration']) ? round((float) $data['format']['duration'], 2) : null,
            'frame_rate' => $this->parseFrameRate($stream['r_frame_rate'] ?? null),
        ];
    }

    /** ffprobe donne la cadence en fraction (« 60000/1001 »). */
    private function parseFrameRate(?string $ratio): ?float
    {
        if ($ratio === null || ! str_contains($ratio, '/')) {
            return $ratio === null ? null : (float) $ratio;
        }

        [$numerator, $denominator] = array_map('floatval', explode('/', $ratio, 2));

        return $denominator > 0 ? round($numerator / $denominator, 2) : null;
    }

    private function imageThumbnail(string $source, string $destination, int $width): bool
    {
        if (! function_exists('imagecreatefromstring')) {
            return false;
        }

        $contents = @file_get_contents($source, false, null, 0, 64 * 1024 * 1024);

        if ($contents === false) {
            return false;
        }

        $image = @imagecreatefromstring($contents);
        unset($contents);

        if ($image === false) {
            return false;
        }

        $sourceWidth = imagesx($image);
        $sourceHeight = imagesy($image);
        $targetWidth = min($width, $sourceWidth);
        $targetHeight = (int) round($sourceHeight * $targetWidth / $sourceWidth);

        $thumbnail = imagecreatetruecolor($targetWidth, $targetHeight);
        imagecopyresampled($thumbnail, $image, 0, 0, 0, 0, $targetWidth, $targetHeight, $sourceWidth, $sourceHeight);
        imagedestroy($image);

        $written = imagejpeg($thumbnail, $destination, 82);
        imagedestroy($thumbnail);

        return $written;
    }

    private function videoThumbnail(string $source, string $destination, int $width): bool
    {
        $ffmpeg = config('drop.ffmpeg');

        if (! is_string($ffmpeg) || $ffmpeg === '') {
            return false;
        }

        // Une image à la première seconde, réduite : le fichier source n'est que lu.
        $result = Process::timeout(120)->run([
            $ffmpeg, '-v', 'error', '-y', '-ss', '1', '-i', $source,
            '-frames:v', '1', '-vf', "scale={$width}:-2", '-q:v', '4', $destination,
        ]);

        return $result->successful() && is_file($destination);
    }
}
