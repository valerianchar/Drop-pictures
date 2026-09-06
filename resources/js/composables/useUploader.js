import { computed, reactive, readonly } from 'vue';
import { router } from '@inertiajs/vue3';
import { createSHA256 } from 'hash-wasm';
import { HttpError, postJson, request } from '../http';
import { isApplePhotosDevice } from './useSaveToPhotos';
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
let nextId = 1;

const state = reactive({
    items: [],
    // La page d'un groupe s'y déclare : ce qu'on dépose alors arrive directement dans le groupe.
    targetGroupId: null,
});

/*
 * Journal côté client : les échecs qui n'atteignent jamais le serveur (sélecteur
 * annulé par iOS, fichier vide, lecture refusée) y laissent une trace lisible
 * dans les journaux de l'application. Silencieux si le réseau manque.
 */
function report(event, data = {}) {
    postJson(routes.clientLog, {
        event,
        data: {
            ...data,
            visibility: document.visibilityState,
            standalone: window.matchMedia?.('(display-mode: standalone)').matches ?? false,
        },
    }).catch(() => {});
}

/** Une ligne d'erreur dans le panneau, sans fichier : la sélection elle-même a échoué. */
function pushNotice(message) {
    state.items.unshift(reactive({
        id: nextId++,
        name: 'Sélection',
        size: 0,
        groupId: null,
        sent: 0,
        progress: 0,
        status: 'erreur',
        error: message,
        checksum: null,
        media: null,
        cancelled: false,
        cancelUrl: null,
    }));
}

const PICKING_KEY = 'drop.picking';
let pickerElement = null;

function picker() {
    if (pickerElement === null || !document.body.contains(pickerElement)) {
        pickerElement = document.createElement('input');
        pickerElement.type = 'file';
        pickerElement.multiple = true;
        pickerElement.tabIndex = -1;
        pickerElement.setAttribute('aria-hidden', 'true');
        pickerElement.style.cssText = 'position:fixed;left:-9999px;top:0;width:1px;height:1px;opacity:0;';
        document.body.appendChild(pickerElement);
    }

    return pickerElement;
}

// L'app a été rechargée pendant que le téléphone préparait le fichier (mémoire, PWA remise à zéro).
if (typeof sessionStorage !== 'undefined' && sessionStorage.getItem(PICKING_KEY)) {
    sessionStorage.removeItem(PICKING_KEY);
    queueMicrotask(() => {
        pushNotice('L’application a été rechargée pendant la préparation du fichier par le téléphone : réessaie, avec un seul fichier à la fois.');
        report('picker.reloaded');
    });
}

const CONCURRENCY = 2;

// Safari iOS supporte mal les gros corps de requête : morceaux plus petits sur iPhone et iPad.
const IOS_CHUNK_BYTES = 4 * 1024 * 1024;

// Un fichier de plusieurs dizaines de Go traverse des milliers de morceaux : une
// coupure passagère ne doit pas tout perdre. Six essais, jusqu'à 30 s d'attente.
const CHUNK_RETRIES = 6;

let queue = Promise.resolve();
let running = 0;
const waiting = [];

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

async function uploadOne(item, file, tags, groupId) {
    const chunkBytes = window.__dropChunkBytes ?? 8 * 1024 * 1024;

    try {
        item.status = 'preparation';

        const start = await openUpload(file);
        const size = Math.min(start.chunk_bytes || chunkBytes, isApplePhotosDevice() ? IOS_CHUNK_BYTES : Infinity);
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
            // Le corps envoyé est le Blob lui-même — Safari le gère mieux qu'un tableau d'octets —,
            // ce sont exactement les octets qui viennent d'être hachés.
            await sendChunk(start.chunk_url.replace('CHUNK', String(index)), slice, item);

            offset += bytes.byteLength;
            index += 1;
            item.sent = offset;
            item.progress = offset / file.size;
        }

        item.status = 'verification';
        const checksum = hasher.digest('hex');
        item.checksum = checksum;

        const finished = await postJson(start.finish_url, { checksum, tags, group_id: groupId ?? undefined });

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
        item.error = describeError(error);
        console.error('[drop-picture] dépôt interrompu', file.name, error);
        report('upload.failed', {
            name: file.name, size: file.size, type: file.type, sent: item.sent,
            error: `${error?.name ?? 'Error'}: ${error?.message ?? ''}`.slice(0, 300), status: error?.status ?? null,
        });
    }
}

/**
 * Ouvre le dépôt. Au retour du sélecteur, Safari iOS peut refuser la première
 * requête (« Load failed ») : on attend que la page soit visible et on réessaie.
 */
async function openUpload(file) {
    for (let attempt = 1; ; attempt++) {
        try {
            return await postJson(routes.uploads, { name: file.name, size: file.size, type: file.type || null });
        } catch (error) {
            const transient = !(error instanceof HttpError) && attempt < 4;

            if (!transient) {
                throw error;
            }

            await visible();
            await new Promise((resolve) => setTimeout(resolve, 600 * attempt));
        }
    }
}

function visible() {
    if (document.visibilityState === 'visible') {
        return Promise.resolve();
    }

    return new Promise((resolve) => {
        const onChange = () => {
            if (document.visibilityState === 'visible') {
                document.removeEventListener('visibilitychange', onChange);
                resolve();
            }
        };

        document.addEventListener('visibilitychange', onChange);
    });
}

/**
 * Le message d'échec dit ce qui s'est vraiment passé : une lecture de fichier
 * refusée par le système (iOS retire l'accès aux vidéos de la photothèque
 * après un moment) n'est pas une coupure réseau, et ne se répare pas pareil.
 */
function describeError(error) {
    if (error instanceof HttpError) {
        return error.message;
    }

    if (error?.name === 'NotReadableError' || error?.name === 'NotFoundError') {
        return 'Le téléphone n’a pas laissé lire le fichier jusqu’au bout. Réessaie en le choisissant à nouveau, ou depuis l’app Fichiers.';
    }

    if (error?.name === 'TypeError' && /fetch|network|Load failed/i.test(error.message ?? '')) {
        return 'Le dépôt a été interrompu par le réseau. Vérifie ta connexion et réessaie.';
    }

    return `Le dépôt a échoué : ${error?.name ?? 'erreur'}${error?.message ? ' — ' + error.message : ''}.`;
}

// Un morceau qui n'avance plus depuis deux minutes est abandonné puis renvoyé.
const CHUNK_TIMEOUT_MS = 120000;

async function sendChunk(url, body, item) {
    for (let attempt = 1; ; attempt++) {
        const controller = new AbortController();
        const timer = setTimeout(() => controller.abort(), CHUNK_TIMEOUT_MS);

        try {
            await request(url, {
                method: 'PUT',
                body,
                headers: { 'Content-Type': 'application/octet-stream' },
                signal: controller.signal,
            });

            return;
        } catch (error) {
            // Une coupure réseau, un délai dépassé ou un morceau tronqué (409) se réessaient ;
            // un refus du serveur (422, 419…) non.
            const retryable = !(error instanceof HttpError) || error.status >= 500 || [408, 409, 425].includes(error.status);

            if (!retryable || attempt >= CHUNK_RETRIES || item.cancelled) {
                throw error;
            }

            await visible();
            await new Promise((resolve) => setTimeout(resolve, Math.min(30000, 1000 * 2 ** (attempt - 1))));
        } finally {
            clearTimeout(timer);
        }
    }
}

function refreshGallery(item) {
    router.reload({ only: ['media', 'storage', 'tags', 'group'] });

    // En production l'aperçu se calcule dans un worker, quelques secondes après
    // le dépôt : on repasse chercher la galerie tant que le fichier n'est pas traité.
    if (item?.media && !item.media.processed) {
        [4000, 12000, 30000].forEach((delay) => setTimeout(() => router.reload({ only: ['media'] }), delay));
    }
}

export function useUploader() {
    function addFiles(fileList, tags = [], groupId = state.targetGroupId) {
        const files = Array.from(fileList ?? []);

        for (const file of files) {
            const item = reactive({
                id: nextId++,
                name: file.name,
                size: file.size,
                groupId,
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

            // Un fichier vide — ou remis vide par le système, ce qu'iOS fait parfois
            // avec une vidéo de la photothèque — se voit, au lieu de disparaître sans un mot.
            if (file.size === 0) {
                item.status = 'erreur';
                item.error = 'Fichier vide ou inaccessible : le téléphone n’a pas transmis son contenu. Réessaie depuis l’app Fichiers.';
                report('file.empty', { name: file.name, type: file.type, lastModified: file.lastModified });
                continue;
            }

            queue = queue.then(async () => {
                await slot();

                uploadOne(item, file, tags, groupId).finally(() => {
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
     * Un seul <input>, attaché au document : détaché, WebKit peut le ramasser
     * pendant la longue préparation d'une vidéo et aucun événement ne revient.
     * On écoute aussi « cancel » : c'est ce que Safari iOS envoie quand la
     * photothèque n'a pas réussi à préparer une vidéo (transcodage, original
     * dans iCloud, stockage saturé) — sans lui, rien ne se passe, sans un mot.
     *
     * Sur téléphone et tablette, annoncer photos et vidéos fait proposer la
     * photothèque et l'appareil (et non seulement « Fichiers »). HEIC et HEIF
     * sont nommés explicitement : sans cela, Safari convertit les HEIC en JPEG
     * au passage — l'inverse de la promesse du produit. Sur ordinateur, aucun
     * filtre : un RAW au type inconnu doit rester sélectionnable.
     */
    function pickFiles(tags = []) {
        const input = picker();
        input.value = '';

        if (window.matchMedia?.('(pointer: coarse)').matches) {
            input.accept = [
                'image/*', 'video/*', 'image/heic', 'image/heif', '.heic', '.heif',
                '.dng', '.cr2', '.cr3', '.nef', '.arw', '.raf', '.orf', '.rw2', '.mov', '.mp4',
            ].join(',');
        } else {
            input.removeAttribute('accept');
        }

        const finish = (files, cancelled) => {
            input.removeEventListener('change', onChange);
            input.removeEventListener('cancel', onCancel);
            sessionStorage.removeItem(PICKING_KEY);

            if (cancelled) {
                pushNotice('Le téléphone n’a pas pu préparer le fichier — c’est fréquent avec une vidéo : original encore dans iCloud, espace insuffisant, ou vidéo importée d’une autre app. Ouvre-la une fois dans Photos, ou passe par l’app Fichiers.');
                report('picker.cancelled');

                return;
            }

            if (!files?.length) {
                pushNotice('Aucun fichier transmis par le téléphone. Réessaie, ou passe par l’app Fichiers.');
                report('picker.empty');

                return;
            }

            addFiles(files, tags);
        };
        const onChange = () => finish(input.files, false);
        const onCancel = () => finish(null, true);

        input.addEventListener('change', onChange);
        input.addEventListener('cancel', onCancel);
        sessionStorage.setItem(PICKING_KEY, String(Date.now()));
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

    /** La page d'un groupe déclare sa cible en arrivant, et la retire en partant. */
    function setTargetGroup(groupId) {
        state.targetGroupId = groupId;
    }

    return {
        items: readonly(state).items,
        active,
        addFiles,
        pickFiles,
        cancel,
        dismiss,
        clearFinished,
        setTargetGroup,
    };
}
