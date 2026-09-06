<?php

namespace Database\Seeders;

use App\Actions\CreateGroup;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Un profil de démonstration et un groupe vide. Pas de fichiers : un média
     * n'existe qu'avec ses octets d'origine sur le disque, et on ne fabrique pas
     * de faux originaux.
     */
    public function run(CreateGroup $createGroup): void
    {
        $marie = User::query()->firstOrCreate(
            ['email' => 'demo@drop.pictures'],
            ['name' => 'Marie Demo', 'password' => 'password'],
        );

        if ($marie->ownedGroups()->doesntExist()) {
            $createGroup->handle($marie, 'Famille');
        }
    }
}
