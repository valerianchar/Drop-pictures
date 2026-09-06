<?php

namespace Tests\Unit;

use App\Enums\MediaKind;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MediaKindTest extends TestCase
{
    /**
     * @return array<string, array{string, MediaKind}>
     */
    public static function extensions(): array
    {
        return [
            'jpeg' => ['JPG', MediaKind::Photo],
            'raw Sony' => ['arw', MediaKind::Photo],
            'dng' => ['dng', MediaKind::Photo],
            'tiff' => ['tiff', MediaKind::Photo],
            'mp4' => ['mp4', MediaKind::Video],
            'mov' => ['MOV', MediaKind::Video],
            'braw' => ['braw', MediaKind::Video],
            'pdf' => ['pdf', MediaKind::Autre],
            'sans extension' => ['', MediaKind::Autre],
        ];
    }

    #[DataProvider('extensions')]
    public function test_it_classifies_by_extension(string $extension, MediaKind $expected): void
    {
        $this->assertSame($expected, MediaKind::fromExtension($extension));
    }

    public function test_it_knows_raw_files(): void
    {
        $this->assertTrue(MediaKind::isRaw('CR2'));
        $this->assertFalse(MediaKind::isRaw('jpg'));
    }
}
