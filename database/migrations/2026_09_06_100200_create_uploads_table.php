<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
         * Un dépôt en cours : le fichier arrive morceau par morceau et s'écrit à
         * la suite dans un fichier partiel. À la fin, l'empreinte du tout est
         * comparée à celle calculée par le navigateur avant de devenir un média.
         */
        Schema::create('uploads', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('original_name');
            $table->string('mime_type', 128)->nullable();
            $table->unsignedBigInteger('size_bytes');
            $table->unsignedBigInteger('received_bytes')->default(0);
            $table->unsignedInteger('next_chunk_index')->default(0);
            $table->unsignedInteger('chunk_bytes');
            $table->string('part_path', 512);
            $table->timestamps();

            $table->index(['user_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('uploads');
    }
};
