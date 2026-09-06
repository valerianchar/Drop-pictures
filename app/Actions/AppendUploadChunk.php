<?php

namespace App\Actions;

use App\Models\Upload;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Ajoute un morceau à la suite du fichier partiel. Les morceaux arrivent dans
 * l'ordre ; un morceau déjà reçu est ignoré (le navigateur a pu réessayer), un
 * morceau en avance est refusé.
 */
final class AppendUploadChunk
{
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

        $handle = fopen($upload->absolutePartPath(), 'ab');

        if ($handle === false) {
            throw new RuntimeException('Impossible d’ouvrir le fichier partiel en écriture.');
        }

        try {
            $written = stream_copy_to_stream($stream, $handle);
        } finally {
            fclose($handle);
        }

        if ($written === false || $written === 0) {
            throw ValidationException::withMessages(['chunk' => 'Morceau vide.']);
        }

        if ($upload->received_bytes + $written > $upload->size_bytes) {
            throw ValidationException::withMessages([
                'chunk' => 'Le fichier reçu dépasse la taille annoncée.',
            ]);
        }

        $upload->forceFill([
            'received_bytes' => $upload->received_bytes + $written,
            'next_chunk_index' => $index + 1,
        ])->save();

        return $upload;
    }
}
