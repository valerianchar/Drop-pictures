<?php

namespace App\Support;

/**
 * Tailles de fichiers en français : « 14,2 Mo », « 1,2 Go », « 480 Mo ».
 */
final class FileSize
{
    private const UNITS = ['o', 'Ko', 'Mo', 'Go', 'To'];

    public static function format(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes.' o';
        }

        $power = min((int) floor(log($bytes, 1024)), count(self::UNITS) - 1);
        $value = $bytes / (1024 ** $power);

        // Une décimale sous 10, aucune au-dessus : « 9,1 Mo » mais « 480 Mo ».
        $decimals = $value < 10 ? 1 : 0;

        return number_format($value, $decimals, ',', ' ').' '.self::UNITS[$power];
    }
}
