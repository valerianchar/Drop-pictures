<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_downloads', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('media_id')->constrained('media')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Le dernier chemin emprunté : la photothèque, un téléchargement, un ZIP.
            $table->string('via', 20);
            $table->unsignedInteger('times')->default(1);
            $table->timestamp('first_at');
            $table->timestamp('last_at');

            // Une seule ligne par fichier et par personne : on incrémente.
            $table->unique(['media_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_downloads');
    }
};
