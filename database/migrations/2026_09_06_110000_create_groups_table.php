<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->string('name', 80);
            /*
             * Le lien d'invitation du groupe : quiconque le détient rejoint le
             * groupe. Le propriétaire peut le régénérer pour révoquer les
             * anciens liens en circulation.
             */
            $table->string('invite_token', 32)->unique();
            $table->timestamps();
        });

        Schema::create('group_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 16);
            $table->timestamps();

            $table->unique(['group_id', 'user_id']);
        });

        Schema::create('group_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invited_by')->constrained('users')->cascadeOnDelete();
            $table->string('email');
            $table->string('token', 40)->unique();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();

            $table->index(['group_id', 'email']);
        });

        /*
         * Un média partagé vers un groupe : chaque membre le voit et télécharge
         * le fichier d'origine. Le média reste la propriété de son déposant.
         */
        Schema::create('group_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('media_id')->constrained('media')->cascadeOnDelete();
            $table->foreignId('shared_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['group_id', 'media_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('group_media');
        Schema::dropIfExists('group_invitations');
        Schema::dropIfExists('group_members');
        Schema::dropIfExists('groups');
    }
};
