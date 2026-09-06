<?php

namespace Tests\Unit;

use App\Support\FileSize;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class FileSizeTest extends TestCase
{
    /**
     * @return array<string, array{int, string}>
     */
    public static function tailles(): array
    {
        return [
            'octets' => [512, '512 o'],
            'kilo' => [2048, '2,0 Ko'],
            'méga avec décimale' => [14_890_000, '14 Mo'],
            'méga sous dix' => [9_540_000, '9,1 Mo'],
            'giga' => [1_288_490_189, '1,2 Go'],
            'quota' => [10 * 1024 * 1024 * 1024, '10 Go'],
        ];
    }

    #[DataProvider('tailles')]
    public function test_it_formats_sizes_in_french(int $bytes, string $expected): void
    {
        $this->assertSame($expected, FileSize::format($bytes));
    }
}
