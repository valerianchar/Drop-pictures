<?php

namespace App\Actions;

use App\Models\Upload;
use HashContext;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Ajoute un morceau à la suite du fichier partiel. Les morceaux arrivent dans
 * l'ordre ; un morceau déjà reçu est ignoré (le navigateur a pu réessayer), un
 * morceau en avance est refusé.
 *
 * Chaque octet écrit nourrit aussi l'empreinte SHA-256 en cours, dont l'état
 * est conservé entre deux requêtes : à la clôture, l'empreinte est déjà là,
 * sans relire le fichier — un dépôt de 40 Go se clôt aussi vite qu'un de 4 Mo.
 */
final class AppendUploadChunk
{
    private const BUFFER_BYTES = 1024 * 1024;

    /**
     * @param  resource  $stream  Le corps de la requête, lu en flux : un morceau
     *                            ne passe jamais entièrement en mémoire.
     */
    public function handle(Upload $upload, int $index, $stream): Upload
    {
        if ($index < $upload->next_chunk_index) {
            return $upload;
        }

        if ($index > $upload->next_chunk_index) {
            throw ValidationException::withMessages([
                'chunk' => "Morceau {$index} reçu alors que le {$upload->next_chunk_index} est attendu.",
            ]);
        }

        $context = self::restoreContext($upload);
        $handle = fopen($upload->absolutePartPath(), 'ab');

        if ($handle === false) {
            throw new RuntimeException('Impossible d’ouvrir le fichier partiel en écriture.');
        }

        $written = 0;

        try {
            while (($buffer = fread($stream, self::BUFFER_BYTES)) !== false && $buffer !== '') {
                if ($upload->received_bytes + $written + strlen($buffer) > $upload->size_bytes) {
                    // On rend au fichier sa taille d'avant ce morceau : rien de faux n'y reste.
                    ftruncate($handle, $upload->received_bytes);

                    throw ValidationException::withMessages([
                        'chunk' => 'Le fichier reçu dépasse la taille annoncée.',
                    ]);
                }

                if (fwrite($handle, $buffer) !== strlen($buffer)) {
                    throw new RuntimeException('Écriture incomplète du morceau sur le disque.');
                }

                hash_update($context, $buffer);
                $written += strlen($buffer);
            }
        } finally {
            fclose($handle);
        }

        if ($written === 0) {
            throw ValidationException::withMessages(['chunk' => 'Morceau vide.']);
        }

        $upload->forceFill([
            'received_bytes' => $upload->received_bytes + $written,
            'next_chunk_index' => $index + 1,
            'hash_state' => self::storeContext($context),
        ])->save();

        return $upload;
    }

    /**
     * L'état du hachage tel qu'il était à la fin du morceau précédent, ou un
     * hachage neuf pour le premier morceau.
     */
    public static function restoreContext(Upload $upload): HashContext
    {
        if ($upload->hash_state === null || $upload->received_bytes === 0) {
            return hash_init('sha256');
        }

        $context = unserialize(base64_decode($upload->hash_state), ['allowed_classes' => [HashContext::class]]);

        if (! $context instanceof HashContext) {
            throw new RuntimeException('État du hachage illisible : le dépôt doit être recommencé.');
        }

        return $context;
    }

    private static function storeContext(HashContext $context): string
    {
        // hash_final consommerait le contexte : on sérialise une copie.
        return base64_encode(serialize(hash_copy($context)));
    }
}
