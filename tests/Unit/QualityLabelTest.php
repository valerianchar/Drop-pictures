<?php

namespace Tests\Unit;

use App\Enums\MediaKind;
use App\Models\Media;
use App\Support\QualityLabel;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class QualityLabelTest extends TestCase
{
    /**
     * @return array<string, array{array<string, mixed>, string}>
     */
    public static function fichiers(): array
    {
        return [
            'photo 24 Mpx' => [['extension' => 'jpg', 'kind' => MediaKind::Photo, 'mime_type' => 'image/jpeg', 'width' => 6000, 'height' => 4000], '24 Mpx'],
            'photo 20 Mpx' => [['extension' => 'jpg', 'kind' => MediaKind::Photo, 'mime_type' => 'image/jpeg', 'width' => 5472, 'height' => 3648], '20 Mpx'],
            'RAW Canon' => [['extension' => 'cr3', 'kind' => MediaKind::Photo, 'mime_type' => 'application/octet-stream', 'width' => null, 'height' => null], 'RAW'],
            'TIFF' => [['extension' => 'tif', 'kind' => MediaKind::Photo, 'mime_type' => 'image/tiff', 'width' => 7000, 'height' => 4667], 'TIFF'],
            'vidéo 4K 60' => [['extension' => 'mp4', 'kind' => MediaKind::Video, 'mime_type' => 'video/mp4', 'width' => 3840, 'height' => 2160, 'frame_rate' => 59.94], '4K 60'],
            'vidéo 4K 24' => [['extension' => 'mov', 'kind' => MediaKind::Video, 'mime_type' => 'video/quicktime', 'width' => 4096, 'height' => 2160, 'frame_rate' => 23.98], '4K'],
            'vidéo verticale 4K' => [['extension' => 'mp4', 'kind' => MediaKind::Video, 'mime_type' => 'video/mp4', 'width' => 2160, 'height' => 3840, 'frame_rate' => 30], '4K'],
            'vidéo 1080p' => [['extension' => 'mp4', 'kind' => MediaKind::Video, 'mime_type' => 'video/mp4', 'width' => 1920, 'height' => 1080, 'frame_rate' => 25], '1080p'],
            'vidéo sans sonde' => [['extension' => 'mkv', 'kind' => MediaKind::Video, 'mime_type' => 'video/x-matroska', 'width' => null, 'height' => null, 'frame_rate' => null], 'MKV'],
            'inconnu' => [['extension' => 'psd', 'kind' => MediaKind::Photo, 'mime_type' => 'image/vnd.adobe.photoshop', 'width' => null, 'height' => null], 'PSD'],
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    #[DataProvider('fichiers')]
    public function test_it_names_the_original_quality(array $attributes, string $expected): void
    {
        $media = new Media;
        $media->forceFill($attributes);

        $this->assertSame($expected, QualityLabel::for($media));
    }
}
