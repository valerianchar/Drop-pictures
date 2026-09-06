import { computed, reactive, readonly } from 'vue';
import { router } from '@inertiajs/vue3';
import { createSHA256 } from 'hash-wasm';
import { HttpError, postJson, request } from '../http';
import { routes } from '../routes';

/*
 * Le dépôt par morceaux, côté navigateur.
 *
 * Chaque fichier est lu tranche par tranche : la tranche nourrit l'empreinte
 * SHA-256 (calculée en flux, un fichier de 50 Go ne passe jamais en mémoire) et
 * part telle quelle au serveur — aucun redimensionnement, aucune conversion, le
 * navigateur ne touche pas aux octets. À la fin, l'empreinte est envoyée : le
 * serveur la recalcule sur ce qu'il a reçu et refuse tout écart.
 *
 * L'état vit au niveau du module : l'en-tête déclenche le dépôt, la page
 * l'affiche, et une navigation Inertia ne perd pas la progression.
 */
const state = reactive({
    items: [],
    tagsForNext: [],
});

const CONCURRENCY = 2;

// Un fichier de plusieurs dizaines de Go traverse des milliers de morceaux : une
// coupure passagère ne doit pas tout perdre. Six essais, jusqu'à 30 s d'attente.
const CHUNK_RETRIES = 6;

let queue = Promise.resolve();
let running = 0;
const waiting = [];
let nextId = 1;

function slot() {
    if (running < CONCURRENCY) {
        running += 1;

        return Promise.resolve();
    }

    return new Promise((resolve) => waiting.push(resolve));
}

function release() {
    const next = waiting.shift();

    if (next) {
        next();
    } else {
        running -= 1;
    }
}

async function uploadOne(item, file, tags) {
    const chunkBytes = window.__dropChunkBytes ?? 8 * 1024 * 1024;

    try {
        item.status = 'preparation';

        const start = await postJson(routes.uploads, { name: file.name, size: file.size, type: file.type || null });
        const size = start.chunk_bytes || chunkBytes;
        item.cancelUrl = start.cancel_url;

        const hasher = await createSHA256();
        hasher.init();

        item.status = 'envoi';
        let offset = 0;
        let index = 0;

        while (offset < file.size) {
            if (item.cancelled) {
                throw new Error('cancelled');
            }

            const slice = file.slice(offset, Math.min(offset + size, file.size));
            const bytes = new Uint8Array(await slice.arrayBuffer());

            hasher.update(bytes);
            await sendChunk(start.chunk_url.replace('CHUNK', String(index)), bytes, item);

            offset += bytes.byteLength;
            index += 1;
            item.sent = offset;
            item.progress = offset / file.size;
        }

        item.status = 'verification';
        const checksum = hasher.digest('hex');
        item.checksum = checksum;

        const finished = await postJson(start.finish_url, { checksum, tags });

        item.status = 'termine';
        item.progress = 1;
        item.media = finished.media;
    } catch (error) {
        if (item.cancelled || error.message === 'cancelled') {
            item.status = 'annule';

            if (item.cancelUrl) {
                request(item.cancelUrl, { method: 'DELETE' }).catch(() => {});
            }

            return;
        }

        item.status = 'erreur';
        item.error =
            error instanceof HttpError
                ? error.message
                : 'Le dépôt a été interrompu. Vérifie ta connexion et réessaie.';
    }
}

async function sendChunk(url, bytes, item) {
    for (let attempt = 1; ; attempt++) {
        try {
            await request(url, {
                method: 'PUT',
                body: bytes,
                headers: { 'Content-Type': 'application/octet-stream' },
            });

            return;
        } catch (error) {
            // Une coupure réseau se réessaie ; un refus du serveur (422, 419…) non.
            const retryable = !(error instanceof HttpError) || error.status >= 500;

            if (!retryable || attempt >= CHUNK_RETRIES || item.cancelled) {
                throw error;
            }

            await new Promise((resolve) => setTimeout(resolve, Math.min(30000, 1000 * 2 ** (attempt - 1))));
        }
    }
}

function refreshGallery(item) {
    router.reload({ only: ['media', 'storage', 'tags'] });

    // En production l'aperçu se calcule dans un worker, quelques secondes après
    // le dépôt : on repasse chercher la galerie tant que le fichier n'est pas traité.
    if (item?.media && !item.media.processed) {
        [4000, 12000, 30000].forEach((delay) => setTimeout(() => router.reload({ only: ['media'] }), delay));
    }
}

export function useUploader() {
    function addFiles(fileList, tags = []) {
        const files = Array.from(fileList ?? []).filter((file) => file.size > 0);

        for (const file of files) {
            const item = reactive({
                id: nextId++,
                name: file.name,
                size: file.size,
                sent: 0,
                progress: 0,
                status: 'attente',
                error: null,
                checksum: null,
                media: null,
                cancelled: false,
                cancelUrl: null,
            });

            state.items.unshift(item);

            queue = queue.then(async () => {
                await slot();

                uploadOne(item, file, tags).finally(() => {
                    release();

                    if (item.status === 'termine') {
                        refreshGallery(item);
                    }
                });
            });
        }
    }

    /**
     * Ouvre le sélecteur de fichiers du système — le bouton « Déposer ».
     *
     * Sur téléphone et tablette, annoncer photos et vidéos fait proposer la
     * photothèque et l'appareil (et non seulement « Fichiers »). HEIC et HEIF
     * sont nommés explicitement : sans cela, Safari convertit les HEIC en JPEG
     * au passage — l'inverse de la promesse du produit. Sur ordinateur, aucun
     * filtre : un RAW au type inconnu doit rester sélectionnable.
     */
    function pickFiles(tags = []) {
        const input = document.createElement('input');
        input.type = 'file';
        input.multiple = true;

        if (window.matchMedia?.('(pointer: coarse)').matches) {
            input.accept = [
                'image/*', 'video/*', 'image/heic', 'image/heif', '.heic', '.heif',
                '.dng', '.cr2', '.cr3', '.nef', '.arw', '.raf', '.orf', '.rw2', '.mov', '.mp4',
            ].join(',');
        }
        input.addEventListener('change', () => addFiles(input.files, tags), { once: true });
        input.click();
    }

    function cancel(item) {
        if (item.status === 'termine' || item.status === 'erreur' || item.status === 'annule') {
            dismiss(item);

            return;
        }

        // L'élément vient d'un proxy en lecture seule : on écrit sur l'original.
        const original = state.items.find((candidate) => candidate.id === item.id);

        if (original) {
            original.cancelled = true;
        }
    }

    /* Le tableau est toujours modifié en place : les vues en gardent la même référence. */
    function dismiss(item) {
        const index = state.items.findIndex((candidate) => candidate.id === item.id);

        if (index !== -1) {
            state.items.splice(index, 1);
        }
    }

    function clearFinished() {
        for (let index = state.items.length - 1; index >= 0; index--) {
            if (['termine', 'erreur', 'annule'].includes(state.items[index].status)) {
                state.items.splice(index, 1);
            }
        }
    }

    const active = computed(() => state.items.filter((item) => !['termine', 'erreur', 'annule'].includes(item.status)));

    return {
        items: readonly(state).items,
        active,
        addFiles,
        pickFiles,
        cancel,
        dismiss,
        clearFinished,
    };
}
