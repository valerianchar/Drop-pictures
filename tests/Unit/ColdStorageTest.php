<?php

namespace Tests\Unit;

use App\Enums\MediaKind;
use App\Models\Media;
use App\Support\ColdStorage;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ColdStorageTest extends TestCase
{
    /**
     * @return array<string, array{string, MediaKind, ?string}>
     */
    public static function fichiers(): array
    {
        return [
            'jpeg → JPEG XL' => ['JPG', MediaKind::Photo, ColdStorage::JXL],
            'raw Canon → zstd' => ['cr3', MediaKind::Photo, ColdStorage::ZSTD],
            'tiff → zstd' => ['tif', MediaKind::Photo, ColdStorage::ZSTD],
            'png → zstd' => ['png', MediaKind::Photo, ColdStorage::ZSTD],
            'heic : déjà compressé' => ['heic', MediaKind::Photo, null],
            'webp : déjà compressé' => ['webp', MediaKind::Photo, null],
            'vidéo : jamais' => ['mp4', MediaKind::Video, null],
            'autre : jamais' => ['pdf', MediaKind::Autre, null],
        ];
    }

    #[DataProvider('fichiers')]
    public function test_it_chooses_the_lossless_codec(string $extension, MediaKind $kind, ?string $expected): void
    {
        $media = new Media;
        $media->forceFill(['extension' => $extension, 'kind' => $kind]);

        $this->assertSame($expected, ColdStorage::codecFor($media));
    }
}
