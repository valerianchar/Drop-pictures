<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media', function (Blueprint $table) {
            /*
             * Archivage sans perte, passé un délai sans consultation : l'original
             * est remplacé sur le disque par une forme compressée réversible
             * (JPEG XL pour les JPEG, zstd pour RAW/TIFF/PNG) dont on sait
             * reconstruire les octets exacts — l'empreinte SHA-256 en fait foi.
             * Rien n'est jamais archivé sans avoir été décompressé et vérifié.
             */
            $table->timestamp('archived_at')->nullable()->after('processed_at');
            $table->string('archive_path', 512)->nullable()->after('archived_at');
            $table->string('archive_codec', 8)->nullable()->after('archive_path');
            $table->unsignedBigInteger('archived_bytes')->nullable()->after('archive_codec');
            // Une tentative sans gain suffisant, ou sur un format qu'on ne sait pas réduire : on ne réessaie pas.
            $table->timestamp('archive_skipped_at')->nullable()->after('archived_bytes');
            $table->timestamp('restoring_at')->nullable()->after('archive_skipped_at');
            // Le délai d'archivage court depuis la dernière consultation, pas depuis le dépôt.
            $table->timestamp('last_accessed_at')->nullable()->after('restoring_at');

            $table->index(['archived_at', 'archive_skipped_at']);
        });
    }

    public function down(): void
    {
        Schema::table('media', function (Blueprint $table) {
            $table->dropIndex(['archived_at', 'archive_skipped_at']);
            $table->dropColumn(['archived_at', 'archive_path', 'archive_codec', 'archived_bytes', 'archive_skipped_at', 'restoring_at', 'last_accessed_at']);
        });
    }
};
