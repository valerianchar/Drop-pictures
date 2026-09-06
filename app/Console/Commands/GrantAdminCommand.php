<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class GrantAdminCommand extends Command
{
    protected $signature = 'drop:admin {email : L’e-mail du compte} {--revoke : Retirer le rôle au lieu de le donner}';

    protected $description = 'Nomme (ou destitue) un administrateur de l’instance — celui qui règle les limites de dépôt';

    public function handle(): int
    {
        $user = User::query()->where('email', $this->argument('email'))->first();

        if ($user === null) {
            $this->error('Aucun compte avec cet e-mail.');

            return self::FAILURE;
        }

        $user->forceFill(['is_admin' => ! $this->option('revoke')])->save();

        $this->info($user->is_admin
            ? "{$user->name} administre l’instance."
            : "{$user->name} n’administre plus l’instance.");

        return self::SUCCESS;
    }
}
