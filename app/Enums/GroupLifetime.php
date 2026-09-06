<?php

namespace App\Enums;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Les durées de vie proposées à la création d'un groupe. Une échéance courte
 * fait office de ménage : les fichiers déposés dans le groupe disparaissent
 * avec lui.
 */
enum GroupLifetime: string
{
    case UneSemaine = '7j';
    case UnMois = '30j';
    case UnAn = '1an';
    case SansLimite = 'illimite';

    public function label(): string
    {
        return match ($this) {
            self::UneSemaine => '7 jours',
            self::UnMois => '30 jours',
            self::UnAn => '1 an',
            self::SansLimite => 'Sans limite',
        };
    }

    public function expiresAt(CarbonInterface $from): ?CarbonImmutable
    {
        $from = CarbonImmutable::instance($from);

        return match ($this) {
            self::UneSemaine => $from->addDays(7),
            self::UnMois => $from->addDays(30),
            self::UnAn => $from->addYear(),
            self::SansLimite => null,
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $lifetime): array => [
            'value' => $lifetime->value,
            'label' => $lifetime->label(),
        ], self::cases());
    }
}
