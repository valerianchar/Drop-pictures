<?php

namespace App\Actions;

use App\Models\Upload;
use HashContext;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Écrit un morceau à sa place dans le fichier partiel.
 *
 * Les morceaux partent à plusieurs de front — c'est ce qui fait la vitesse : un
 * seul flux ne remplit pas un lien mobile, quatre le remplissent (mesuré ×2,9).
 * Ils arrivent donc dans le désordre, et chacun s'écrit à son décalage plutôt
 * qu'« à la suite ». Un morceau déjà reçu est ignoré : le navigateur a pu le
 * renvoyer.
 *
 * L'empreinte SHA-256 doit, elle, être calculée dans l'ordre. Elle avance donc
 * sur le seul préfixe contigu déjà reçu, morceau après morceau, et son état est
 * conservé entre deux requêtes : à la clôture l'empreinte est déjà là, sans
 * relire le fichier — un dépôt de 40 Go se clôt aussi vite qu'un de 4 Mo. Un
 * morceau tronqué en route n'est pas marqué comme arrivé : sa zone sera
 * réécrite au renvoi, et n'aura jamais nourri l'empreinte.
 */
final class AppendUploadChunk
{
    private const BUFFER_BYTES = 1024 * 1024;

    /**
     * @param  resource  $stream  Le corps de la requête, lu en flux : un morceau
     *                            ne passe jamais entièrement en mémoire.
     * @param  ?int  $announcedBytes  La longueur annoncée par le navigateur : un
     *                                morceau coupé en route ne doit pas passer
     *                                pour complet.
     */
    public function handle(Upload $upload, int $index, $stream, ?int $announcedBytes = null): Upload
    {
        if ($index < 0 || $index >= $upload->chunkCount()) {
            throw ValidationException::withMessages([
                'chunk' => "Morceau {$index} hors du fichier ({$upload->chunkCount()} morceaux attendus).",
            ]);
        }

        if ($upload->hasArrived($index)) {
            return $upload;
        }

        $expected = $upload->chunkLength($index);

        if ($announcedBytes !== null && $announcedBytes !== $expected) {
            throw ValidationException::withMessages([
                'chunk' => "Morceau {$index} : {$announcedBytes} octets annoncés, {$expected} attendus.",
            ]);
        }

        $written = $this->writeAt($upload, $upload->chunkOffset($index), $stream, $expected);

        if ($written !== $expected) {
            // Rien n'est marqué : le morceau sera renvoyé, et ces octets-là
            // n'entrent pas dans l'empreinte.
            abort(409, "Morceau {$index} incomplet : {$written} octets reçus sur {$expected} attendus.");
        }

        return $this->register($upload, $index);
    }

    /**
     * Marque le morceau et fait avancer l'empreinte sur le préfixe contigu.
     *
     * Sous verrou : plusieurs morceaux du même dépôt arrivent en même temps, et
     * ni le masque ni l'état du hachage ne supportent deux écritures
     * concurrentes. L'écriture des octets, elle, reste hors du verrou — c'est
     * elle qui prend du temps, et deux morceaux n'écrivent jamais au même
     * endroit.
     */
    private function register(Upload $upload, int $index): Upload
    {
        Cache::lock("depot-{$upload->uuid}", 60)->block(30, function () use ($upload, $index): void {
            $upload->refresh();

            $mask = $upload->arrivedMask();
            $mask[$index] = '1';

            $upload->forceFill([
                'arrived' => $mask,
                'received_bytes' => $upload->bytesFromMask($mask),
            ]);

            self::advance($upload, $mask);
            $upload->save();
        });

        return $upload->refresh();
    }

    /**
     * Fait courir l'empreinte sur les morceaux contigus déjà reçus, en les
     * relisant depuis le disque — le prix du parallélisme, payé au fil de
     * l'envoi plutôt qu'en bloc à la clôture.
     */
    private static function advance(Upload $upload, string $mask): void
    {
        $next = $upload->next_chunk_index;
        $count = $upload->chunkCount();

        if ($next >= $count || $mask[$next] !== '1') {
            return;
        }

        $context = self::restoreContext($upload);
        $handle = fopen($upload->absolutePartPath(), 'rb');

        if ($handle === false) {
            throw new RuntimeException('Impossible de relire le fichier partiel.');
        }

        try {
            while ($next < $count && $mask[$next] === '1') {
                self::hashChunk($context, $handle, $upload->chunkOffset($next), $upload->chunkLength($next));
                $next++;
            }
        } finally {
            fclose($handle);
        }

        $upload->forceFill([
            'next_chunk_index' => $next,
            'hash_state' => self::storeContext($context),
        ]);
    }

    /**
     * @param  resource  $handle
     */
    private static function hashChunk(HashContext $context, $handle, int $offset, int $length): void
    {
        if (fseek($handle, $offset) !== 0) {
            throw new RuntimeException('Fichier partiel plus court que prévu.');
        }

        $remaining = $length;

        while ($remaining > 0) {
            $buffer = fread($handle, (int) min(self::BUFFER_BYTES, $remaining));

            if ($buffer === false || $buffer === '') {
                throw new RuntimeException('Lecture incomplète du fichier partiel.');
            }

            hash_update($context, $buffer);
            $remaining -= strlen($buffer);
        }
    }

    /**
     * @param  resource  $stream
     */
    private function writeAt(Upload $upload, int $offset, $stream, int $expected): int
    {
        // « c+b » : ouvre sans tronquer, crée au besoin. Écrire au-delà de la fin
        // laisse un trou que le morceau manquant viendra combler.
        $handle = fopen($upload->absolutePartPath(), 'c+b');

        if ($handle === false) {
            throw new RuntimeException('Impossible d’ouvrir le fichier partiel en écriture.');
        }

        $written = 0;

        try {
            if (fseek($handle, $offset) !== 0) {
                throw new RuntimeException('Impossible de se placer dans le fichier partiel.');
            }

            while ($written < $expected) {
                $buffer = fread($stream, (int) min(self::BUFFER_BYTES, $expected - $written));

                if ($buffer === false || $buffer === '') {
                    break;
                }

                if (fwrite($handle, $buffer) !== strlen($buffer)) {
                    throw new RuntimeException('Écriture incomplète du morceau sur le disque.');
                }

                $written += strlen($buffer);
            }

            // Un corps plus long que le morceau attendu : refusé, rien n'est marqué.
            if ($written === $expected && ($extra = fread($stream, 1)) !== false && $extra !== '') {
                throw ValidationException::withMessages([
                    'chunk' => 'Le morceau reçu est plus long que sa taille attendue.',
                ]);
            }
        } finally {
            fclose($handle);
        }

        return $written;
    }

    /**
     * L'état du hachage au bout du préfixe contigu déjà reçu, ou un hachage
     * neuf si rien n'y est encore entré.
     */
    public static function restoreContext(Upload $upload): HashContext
    {
        if ($upload->hash_state === null || $upload->next_chunk_index === 0) {
            return hash_init('sha256');
        }

        $context = unserialize(base64_decode($upload->hash_state), ['allowed_classes' => [HashContext::class]]);

        if (! $context instanceof HashContext) {
            throw new RuntimeException('État du hachage illisible : le dépôt doit être recommencé.');
        }

        return $context;
    }

    /**
     * Rattrape l'empreinte à la clôture, si le dernier morceau reçu comblait un
     * trou et que le préfixe a bondi d'un coup.
     */
    public static function catchUp(Upload $upload): Upload
    {
        if ($upload->next_chunk_index >= $upload->chunkCount()) {
            return $upload;
        }

        Cache::lock("depot-{$upload->uuid}", 60)->block(30, function () use ($upload): void {
            $upload->refresh();
            self::advance($upload, $upload->arrivedMask());
            $upload->save();
        });

        return $upload->refresh();
    }

    private static function storeContext(HashContext $context): string
    {
        // hash_final consommerait le contexte : on sérialise une copie.
        return base64_encode(serialize(hash_copy($context)));
    }
}
