<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->uuid('uuid')->unique();
            $table->string('original_name');
            $table->string('extension', 16);
            $table->string('mime_type', 128);
            $table->string('kind', 16);
            /*
             * Taille et empreinte SHA-256 du fichier tel qu'il a été déposé. Elles
             * ne changent jamais : le fichier n'est ni compressé, ni réencodé, ni
             * converti. Le téléchargement redonne exactement ces octets, et
             * l'empreinte permet à quiconque de le vérifier.
             */
            $table->unsignedBigInteger('size_bytes');
            $table->char('checksum_sha256', 64);
            $table->string('disk_path', 512);
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->decimal('duration_seconds', 10, 2)->nullable();
            $table->decimal('frame_rate', 6, 2)->nullable();
            // Seul dérivé produit : un aperçu réduit. Absent pour RAW, TIFF, etc.
            $table->string('thumbnail_path', 512)->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'id']);
            $table->index(['user_id', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media');
    }
};
