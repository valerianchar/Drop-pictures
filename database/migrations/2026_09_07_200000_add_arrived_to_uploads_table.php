<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('uploads', function (Blueprint $table): void {
            /*
             * Quels morceaux sont arrivés : un caractère par morceau, « 1 » ou
             * « 0 ». Les morceaux partent désormais à plusieurs de front et
             * peuvent donc arriver dans le désordre — chacun s'écrit à sa place
             * dans le fichier partiel, et l'empreinte n'avance que sur le
             * préfixe contigu déjà reçu. 6 400 caractères pour un fichier de
             * 50 Go : la colonne reste minuscule.
             */
            $table->text('arrived')->nullable()->after('hash_state');
        });
    }

    public function down(): void
    {
        Schema::table('uploads', function (Blueprint $table): void {
            $table->dropColumn('arrived');
        });
    }
};
