<?php

namespace App\Enums;

/**
 * Par où un fichier est reparti chez son destinataire. C'est ce qui fait la
 * différence entre « rangé dans la photothèque » et « téléchargé » — la carte
 * l'affiche pour qu'on sache d'un coup d'œil ce qui est déjà récupéré.
 */
enum DownloadChannel: string
{
    case Photos = 'photos';
    case File = 'fichier';
    case Zip = 'zip';

    public function label(): string
    {
        return match ($this) {
            self::Photos => 'Dans Photos',
            self::File, self::Zip => 'Téléchargé',
        };
    }
}
