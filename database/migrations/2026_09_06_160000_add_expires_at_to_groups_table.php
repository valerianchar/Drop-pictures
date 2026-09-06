<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('groups', function (Blueprint $table) {
            /*
             * Durée de vie choisie à la création. Nulle : le groupe ne périme
             * pas. Passée : le planificateur détruit les fichiers déposés dans
             * le groupe, retire les autres et supprime le groupe.
             */
            $table->timestamp('expires_at')->nullable()->after('invite_token');
        });
    }

    public function down(): void
    {
        Schema::table('groups', function (Blueprint $table) {
            $table->dropColumn('expires_at');
        });
    }
};
