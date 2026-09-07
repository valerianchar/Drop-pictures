import { computed, reactive, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { postJson } from '../http';
import { routes } from '../routes';
import { useToast } from './useToast';

/*
 * « Enregistrer dans Photos » sur iPhone et iPad : dans la photothèque, comme
 * WhatsApp.
 *
 * Un téléchargement classique (Content-Disposition: attachment) finit dans
 * l'app Fichiers. Pour arriver dans Photos, il faut passer par la feuille de
 * partage d'iOS avec le fichier lui-même : on lit l'original en flux, on le
 * remet tel quel à navigator.share(), et iOS propose « Enregistrer l'image »
 * ou « Enregistrer la vidéo ». Aucun octet n'est transformé — le blob est la
 * réponse HTTP, celle dont l'empreinte est affichée à côté.
 *
 * Deux contraintes d'iOS commandent tout le reste :
 *
 * 1. La feuille n'accepte qu'un lot de taille raisonnable. Vingt vidéos d'un
 *    coup, elle refuse. Alors « tout enregistrer » découpe la sélection en
 *    lots (LOT_BYTES / LOT_COUNT) et les enchaîne — c'est la file d'attente
 *    ci-dessous, partagée par toute l'application.
 * 2. La feuille ne s'ouvre que dans la foulée d'un geste. Un serveur ne peut
 *    donc rien déposer dans la photothèque à notre place : aucune tâche de
 *    fond n'y a accès, seul Safari, et seulement juste après un appui. La file
 *    demande donc un appui par lot — et si la lecture d'une grosse vidéo a
 *    dépassé la fenêtre du geste, le lot reste prêt en mémoire et un second
 *    appui ouvre la feuille sans rien relire.
 *
 * Quand le partage est impossible (fichier trop gros, type refusé), une photo
 * s'affiche inline — un appui long propose « Enregistrer dans Photos » — et
 * une vidéo part dans Fichiers.
 */

/** Au-delà, la feuille de partage d'iOS ne veut pas d'un fichier : il partira dans Fichiers. */
const SHARE_LIMIT_BYTES = 1.5 * 1024 * 1024 * 1024;
/** Le poids d'un lot : au-delà, iOS refuse le partage (« la feuille est trop grosse »). */
const LOT_BYTES = 400 * 1024 * 1024;
/** Et son nombre de fichiers : la feuille reste lisible, et un échec ne coûte pas tout. */
const LOT_COUNT = 8;

export function isApplePhotosDevice() {
    if (typeof navigator === 'undefined') {
        return false;
    }

    const { userAgent, platform, maxTouchPoints } = navigator;

    // L'iPad récent se présente comme un Mac : on le reconnaît à son écran tactile.
    return /iPhone|iPad|iPod/.test(userAgent) || (platform === 'MacIntel' && (maxTouchPoints ?? 0) > 1);
}

export function canShareFiles() {
    return typeof navigator.share === 'function' && typeof navigator.canShare === 'function';
}

/** Ce qui peut atterrir dans la photothèque : une photo ou une vidéo. */
export function goesToPhotos(media) {
    return media.kind === 'photo' || media.is_video;
}

/** Une trace côté serveur : un enregistrement qui « ne marche pas » n'est sinon visible nulle part. */
function report(event, data = {}) {
    postJson(routes.clientLog, {
        event,
        data: { ...data, standalone: window.matchMedia?.('(display-mode: standalone)').matches ?? false },
    }).catch(() => {});
}

/**
 * La photothèque a accepté : le serveur ne peut pas le deviner, la page le lui
 * dit — c'est ce qui allume la pastille « Dans Photos » sur les cartes.
 */
async function markSaved(ids) {
    try {
        await postJson(routes.markDownloaded, { ids });
        router.reload({ only: ['media'], preserveScroll: true, preserveState: true });
    } catch {
        // La pastille n'est qu'un confort : son échec ne gâche pas l'enregistrement.
    }
}

let refreshTimer = null;

/**
 * Après un téléchargement classique, la trace est posée côté serveur pendant
 * que le fichier part : on relit la galerie peu après pour allumer la pastille,
 * sans rien casser de l'écran (ni défilement, ni sélection en cours).
 */
export function refreshDownloadsSoon() {
    clearTimeout(refreshTimer);
    refreshTimer = setTimeout(() => {
        router.reload({ only: ['media'], preserveScroll: true, preserveState: true });
    }, 2500);
}

async function readAll(media, onChunk) {
    const response = await fetch(media.download_url, { credentials: 'same-origin' });

    if (!response.ok || !response.body) {
        throw new Error(`HTTP ${response.status}`);
    }

    const reader = response.body.getReader();
    const chunks = [];

    for (;;) {
        const { done, value } = await reader.read();

        if (done) {
            break;
        }

        chunks.push(value);
        onChunk(value.byteLength);
    }

    return new File(chunks, media.name, { type: media.mime_type || 'application/octet-stream' });
}

/**
 * Ouvre la feuille de partage. À n'appeler que dans la foulée d'un geste :
 * aucun `await` ne doit précéder navigator.share().
 */
async function openSheet(files, title) {
    try {
        await navigator.share({ files, title });

        return 'ok';
    } catch (error) {
        if (error?.name === 'AbortError') {
            return 'annule';
        }

        if (error?.name === 'NotAllowedError') {
            return 'geste';
        }

        return 'refus';
    }
}

/**
 * Découpe une sélection en lots que la feuille de partage accepte. Les fichiers
 * hors de sa portée sont mis de côté plutôt que de faire échouer le lot.
 */
function planLots(mediaList) {
    const lots = [];
    const tooBig = [];
    let current = [];
    let bytes = 0;

    for (const media of mediaList) {
        if (media.size_bytes > SHARE_LIMIT_BYTES) {
            tooBig.push(media);

            continue;
        }

        if (current.length > 0 && (bytes + media.size_bytes > LOT_BYTES || current.length >= LOT_COUNT)) {
            lots.push(current);
            current = [];
            bytes = 0;
        }

        current.push(media);
        bytes += media.size_bytes;
    }

    if (current.length > 0) {
        lots.push(current);
    }

    return { lots, tooBig };
}

/*
 * La file est unique dans la page : le bouton « Tout dans Photos » d'un groupe,
 * celui de la galerie et la barre de sélection la remplissent, et un seul
 * dialogue (monté dans la mise en page) la déroule.
 */
const queue = reactive({
    open: false,
    lots: [],
    tooBig: [],
    index: 0,
    savedCount: 0,
    skipped: [],
    busy: false,
    progress: 0,
    /** Lot lu, feuille refusée faute de geste : un second appui suffit. */
    ready: false,
});

let readyFiles = null;

export function startPhotosQueue(mediaList) {
    const eligible = mediaList.filter((media) => goesToPhotos(media));

    if (eligible.length === 0 || !canShareFiles()) {
        return false;
    }

    const { lots, tooBig } = planLots(eligible);

    queue.lots = lots;
    queue.tooBig = tooBig;
    queue.index = 0;
    queue.savedCount = 0;
    queue.skipped = [];
    queue.busy = false;
    queue.progress = 0;
    queue.ready = false;
    queue.open = true;
    readyFiles = null;

    return true;
}

export function closePhotosQueue() {
    queue.open = false;
    queue.ready = false;
    readyFiles = null;
}

function advance() {
    queue.ready = false;
    readyFiles = null;
    queue.index += 1;
}

async function finishLot(lot, files) {
    const outcome = await openSheet(files, lot.length > 1 ? `${lot.length} fichiers` : lot[0].name);

    if (outcome === 'ok') {
        const ids = lot.map((media) => media.id);
        advance();
        queue.savedCount += lot.length;
        await markSaved(ids);

        return;
    }

    // Geste périmé (grosse vidéo) ou feuille fermée : le lot reste prêt, un
    // second appui l'ouvre sans rien relire.
    if (outcome === 'geste' || outcome === 'annule') {
        readyFiles = files;
        queue.ready = true;

        if (outcome === 'geste') {
            report('photos.share-needs-tap', { count: files.length });
        }

        return;
    }

    report('photos.share-failed', { count: files.length });
    queue.skipped.push(...lot);
    advance();
}

export async function savePhotosLot() {
    if (queue.busy || queue.index >= queue.lots.length) {
        return;
    }

    const lot = queue.lots[queue.index];

    // Second appui : la feuille s'ouvre tout de suite, sans await avant elle.
    if (queue.ready && readyFiles !== null) {
        return finishLot(lot, readyFiles);
    }

    queue.busy = true;
    queue.progress = 0;

    try {
        const total = lot.reduce((sum, media) => sum + media.size_bytes, 0);
        let received = 0;
        const files = [];

        for (const media of lot) {
            files.push(
                await readAll(media, (bytes) => {
                    received += bytes;
                    queue.progress = total ? received / total : 0;
                }),
            );
        }

        if (!navigator.canShare({ files })) {
            report('photos.type-refused', { count: files.length });
            queue.skipped.push(...lot);
            advance();

            return;
        }

        await finishLot(lot, files);
    } catch (error) {
        report('photos.read-failed', { error: String(error?.message ?? error), count: lot.length });
        queue.skipped.push(...lot);
        advance();
    } finally {
        queue.busy = false;
        queue.progress = 0;
    }
}

export function skipPhotosLot() {
    if (queue.index < queue.lots.length) {
        queue.skipped.push(...queue.lots[queue.index]);
        advance();
    }
}

/** L'état de la file, pour le dialogue qui la déroule. */
export function usePhotosQueue() {
    const currentLot = computed(() => queue.lots[queue.index] ?? []);
    const totalFiles = computed(() => queue.lots.reduce((sum, lot) => sum + lot.length, 0));
    const done = computed(() => queue.index >= queue.lots.length);
    const lotBytes = computed(() => currentLot.value.reduce((sum, media) => sum + media.size_bytes, 0));
    const percent = computed(() => Math.round(queue.progress * 100));

    return {
        queue,
        currentLot,
        totalFiles,
        done,
        lotBytes,
        percent,
        saveLot: savePhotosLot,
        skipLot: skipPhotosLot,
        close: closePhotosQueue,
    };
}

/** Un seul fichier : la fiche, la carte, la page publique d'un lien. */
export function useSaveToPhotos() {
    const saving = ref(false);
    const progress = ref(0);
    const readyId = ref(null);
    let readyFile = null;
    const { toast } = useToast();

    function fallback(media) {
        if (media.is_video || !media.view_url) {
            toast('Photos n’a pas pu prendre la vidéo : elle part dans Fichiers.');
            window.location.assign(media.download_url);

            return;
        }

        toast('Appuie longuement sur l’image, puis « Enregistrer dans Photos ».');
        window.location.assign(media.view_url);
    }

    async function offer(media, file) {
        const outcome = await openSheet([file], media.name);

        if (outcome === 'ok') {
            readyId.value = null;
            readyFile = null;
            toast(`Choisis « Enregistrer ${media.is_video ? 'la vidéo' : 'l’image'} » : c’est l’original, sans compression.`);
            await markSaved([media.id]);

            return;
        }

        if (outcome === 'geste' || outcome === 'annule') {
            readyId.value = media.id;
            readyFile = file;

            if (outcome === 'geste') {
                report('photos.share-needs-tap', { bytes: media.size_bytes });
                toast('Prêt — appuie de nouveau pour l’enregistrer dans Photos.');
            }

            return;
        }

        readyId.value = null;
        readyFile = null;
        report('photos.share-failed', { bytes: media.size_bytes });
        fallback(media);
    }

    async function save(media) {
        if (saving.value) {
            return;
        }

        // Second appui : la feuille s'ouvre sans attente.
        if (readyId.value === media.id && readyFile !== null) {
            return offer(media, readyFile);
        }

        readyId.value = null;
        readyFile = null;

        if (!canShareFiles() || media.size_bytes > SHARE_LIMIT_BYTES) {
            report('photos.share-unavailable', { bytes: media.size_bytes, can_share: canShareFiles() });
            fallback(media);

            return;
        }

        saving.value = true;
        progress.value = 0;

        try {
            const total = media.size_bytes;
            let received = 0;
            const file = await readAll(media, (bytes) => {
                received += bytes;
                progress.value = total ? received / total : 0;
            });

            if (!navigator.canShare({ files: [file] })) {
                report('photos.type-refused', { type: file.type, bytes: file.size });
                fallback(media);

                return;
            }

            await offer(media, file);
        } catch (error) {
            report('photos.read-failed', { error: String(error?.message ?? error) });
            fallback(media);
        } finally {
            saving.value = false;
            progress.value = 0;
        }
    }

    return {
        saving,
        progress,
        save,
        isReady: (id) => readyId.value === id,
        isApple: isApplePhotosDevice(),
        startQueue: startPhotosQueue,
    };
}
