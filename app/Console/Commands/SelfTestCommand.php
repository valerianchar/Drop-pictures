<?php

namespace App\Console\Commands;

use App\Actions\AppendUploadChunk;
use App\Actions\CreateShareLink;
use App\Actions\DeleteMedia;
use App\Actions\FinalizeUpload;
use App\Actions\StartUpload;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/**
 * Le critère d'acceptation n°1, joué sur l'instance elle-même : un fichier
 * d'octets aléatoires est déposé par le même chemin que le navigateur (dépôt
 * par morceaux, empreinte), puis redescendu par HTTP via un lien de partage.
 * Les deux empreintes doivent être identiques, sinon la commande échoue et
 * le déploiement avec elle.
 */
class SelfTestCommand extends Command
{
    protected $signature = 'drop:selftest
        {--base=http://127.0.0.1 : Adresse interne à laquelle l’application répond}
        {--size=3000000 : Taille du fichier de test, en octets}';

    protected $description = 'Vérifie qu’un fichier déposé est rendu octet pour octet (upload par morceaux → téléchargement HTTP)';

    public function handle(
        StartUpload $startUpload,
        AppendUploadChunk $appendChunk,
        FinalizeUpload $finalizeUpload,
        CreateShareLink $createShareLink,
        DeleteMedia $deleteMedia,
    ): int {
        $size = (int) $this->option('size');
        $bytes = random_bytes($size);
        $checksum = hash('sha256', $bytes);
        $user = null;
        $media = null;

        try {
            $user = User::query()->create([
                'name' => 'Selftest',
                'email' => 'selftest-'.Str::lower(Str::random(8)).'@drop.invalid',
                'password' => Str::random(32),
            ]);

            $upload = $startUpload->handle($user, 'selftest.bin', $size, 'application/octet-stream');
            $this->line("→ dépôt ouvert ({$size} octets, morceaux de {$upload->chunk_bytes})");

            foreach (str_split($bytes, $upload->chunk_bytes) as $index => $chunk) {
                $stream = fopen('php://temp', 'r+');
                fwrite($stream, $chunk);
                rewind($stream);
                $upload = $appendChunk->handle($upload, $index, $stream);
                fclose($stream);
            }

            $media = $finalizeUpload->handle($upload, $checksum);
            $this->line("→ média créé, empreinte serveur {$media->checksum_sha256}");

            if ($media->checksum_sha256 !== $checksum) {
                $this->error('✗ L’empreinte calculée par le serveur diffère de celle du fichier envoyé.');

                return self::FAILURE;
            }

            $link = $createShareLink->handle($media, $user, limited: true);
            $url = rtrim($this->option('base'), '/')."/p/{$link->token}/telecharger";
            $this->line("→ téléchargement HTTP : {$url}");

            $response = Http::timeout(120)->withOptions(['verify' => false])->get($url);

            if (! $response->successful()) {
                $this->error("✗ Le téléchargement a répondu {$response->status()}.");

                return self::FAILURE;
            }

            $downloaded = hash('sha256', $response->body());
            $this->line("→ empreinte du téléchargement {$downloaded}");

            if ($downloaded !== $checksum || strlen($response->body()) !== $size) {
                $this->error('✗ Le fichier téléchargé n’est pas identique au fichier déposé.');

                return self::FAILURE;
            }

            $this->info("✓ Intégrité vérifiée : {$size} octets, SHA-256 identique du dépôt au téléchargement.");

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error('✗ '.$exception->getMessage());

            return self::FAILURE;
        } finally {
            if ($media !== null && $media->exists) {
                $deleteMedia->handle($media);
            }

            $user?->delete();
        }
    }
}
