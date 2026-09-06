<?php

namespace App\Enums;

enum GroupRole: string
{
    case Proprietaire = 'proprietaire';
    case Membre = 'membre';

    public function label(): string
    {
        return match ($this) {
            self::Proprietaire => 'Propriétaire',
            self::Membre => 'Membre',
        };
    }
}
