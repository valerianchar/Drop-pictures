<?php

namespace App\Console\Commands;

use App\Actions\ExpireGroup;
use App\Models\Group;
use Illuminate\Console\Command;

class ExpireGroupsCommand extends Command
{
    protected $signature = 'drop:expire-groups';

    protected $description = 'Clôt les groupes arrivés à échéance : fichiers déposés détruits, groupe supprimé';

    public function handle(ExpireGroup $expireGroup): int
    {
        $groups = 0;
        $files = 0;

        // get() et non each() : supprimer des lignes pendant une pagination par décalage en sauterait.
        Group::query()->whereNotNull('expires_at')->where('expires_at', '<=', now())->get()->each(function (Group $group) use ($expireGroup, &$groups, &$files): void {
            $name = $group->name;
            $destroyed = $expireGroup->handle($group);
            $groups++;
            $files += $destroyed;
            $this->line("→ « {$name} » clos, {$destroyed} fichier(s) détruit(s)");
        });

        $this->info("{$groups} groupe(s) clos, {$files} fichier(s) détruit(s).");

        return self::SUCCESS;
    }
}
