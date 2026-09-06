<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('uploads', function (Blueprint $table) {
            /*
             * L'état du hachage SHA-256, sérialisé entre deux morceaux. L'empreinte
             * se calcule ainsi au fil du dépôt : la clôture n'a plus à relire un
             * fichier de plusieurs dizaines de Go, elle est immédiate.
             */
            $table->text('hash_state')->nullable()->after('chunk_bytes');
        });
    }

    public function down(): void
    {
        Schema::table('uploads', function (Blueprint $table) {
            $table->dropColumn('hash_state');
        });
    }
};
