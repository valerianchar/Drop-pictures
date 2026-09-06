<?php

namespace Tests\Unit;

use App\Support\MimeType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MimeTypeTest extends TestCase
{
    /**
     * @return array<string, array{string, string}>
     */
    public static function extensions(): array
    {
        return [
            'jpeg' => ['JPG', 'image/jpeg'],
            'heic iPhone' => ['heic', 'image/heic'],
            'raw Canon' => ['cr3', 'image/x-canon-cr3'],
            'mov iPhone' => ['MOV', 'video/quicktime'],
            'mp4' => ['mp4', 'video/mp4'],
            'inconnu' => ['xyz', 'application/octet-stream'],
        ];
    }

    #[DataProvider('extensions')]
    public function test_it_guesses_from_the_extension(string $extension, string $expected): void
    {
        $this->assertSame($expected, MimeType::guess($extension));
    }

    public function test_it_prefers_the_type_the_browser_sent(): void
    {
        $this->assertSame('image/png', MimeType::resolve('image/png', 'jpg'));
    }

    public function test_it_falls_back_when_the_browser_sent_nothing_useful(): void
    {
        $this->assertSame('image/heic', MimeType::resolve('', 'heic'));
        $this->assertSame('image/heic', MimeType::resolve(null, 'HEIC'));
        $this->assertSame('video/quicktime', MimeType::resolve('application/octet-stream', 'mov'));
    }
}
