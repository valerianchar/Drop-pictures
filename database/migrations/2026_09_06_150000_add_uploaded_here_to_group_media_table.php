<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('group_media', function (Blueprint $table) {
            /*
             * Déposé directement dans le groupe, ou partagé depuis une galerie ?
             * À l'expiration du groupe, seuls les premiers sont détruits : les
             * autres ont une vie ailleurs.
             */
            $table->boolean('uploaded_here')->default(false)->after('shared_by');
        });
    }

    public function down(): void
    {
        Schema::table('group_media', function (Blueprint $table) {
            $table->dropColumn('uploaded_here');
        });
    }
};
