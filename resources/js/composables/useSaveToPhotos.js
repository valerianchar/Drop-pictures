import { ref } from 'vue';
import { postJson } from '../http';
import { routes } from '../routes';
import { useToast } from './useToast';

/*
 * « Enregistrer » sur iPhone et iPad : dans Photos, comme WhatsApp.
 *
 * Un téléchargement classique (Content-Disposition: attachment) finit dans
 * l'app Fichiers. Pour arriver dans Photos, il faut passer par la feuille de
 * partage d'iOS avec le fichier lui-même : on lit l'original en flux, on le
 * remet tel quel à navigator.share(), et iOS propose « Enregistrer l'image »
 * ou « Enregistrer la vidéo » — et, dans la même feuille, « Enregistrer dans
 * Fichiers » pour qui préfère. Aucun octet n'est transformé — le blob est la
 * réponse HTTP, celle dont l'empreinte est affichée à côté.
 *
 * Safari n'ouvre la feuille que dans la foulée d'un geste de l'utilisateur.
 * Quand la lecture du fichier a duré trop longtemps (une vidéo de plusieurs
 * centaines de Mo), le geste est périmé et share() répond NotAllowedError :
 * on garde alors le fichier prêt en mémoire et le bouton demande un second
 * appui, qui ouvre la feuille sans attente.
 *
 * Quand le partage n'est pas possible du tout (fichier trop gros pour la
 * mémoire du téléphone, type refusé par iOS), une photo s'affiche inline —
 * un appui long propose « Enregistrer dans Photos » — et une vidéo part vers
 * Fichiers par le téléchargement classique.
 */
const SHARE_LIMIT_BYTES = 1.5 * 1024 * 1024 * 1024;

export function isApplePhotosDevice() {
    if (typeof navigator === 'undefined') {
        return false;
    }

    const { userAgent, platform, maxTouchPoints } = navigator;

    // L'iPad récent se présente comme un Mac : on le reconnaît à son écran tactile.
    return /iPhone|iPad|iPod/.test(userAgent) || (platform === 'MacIntel' && (maxTouchPoints ?? 0) > 1);
}

function canShareFiles() {
    return typeof navigator.share === 'function' && typeof navigator.canShare === 'function';
}

/** Une trace côté serveur : un enregistrement qui « ne marche pas » n'est sinon visible nulle part. */
function report(event, data = {}) {
    postJson(routes.clientLog, {
        event,
        data: { ...data, standalone: window.matchMedia?.('(display-mode: standalone)').matches ?? false },
    }).catch(() => {});
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

export function useSaveToPhotos() {
    const saving = ref(false);
    const progress = ref(0);
    // Fichier(s) lu(s) mais feuille refusée faute de geste : un second appui suffit.
    const ready = ref(null);
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

    /**
     * Ouvre la feuille de partage. Doit être appelé dans la foulée d'un geste :
     * aucun `await` avant navigator.share().
     */
    async function share(key, files, title, onDone, onRefused) {
        try {
            const promise = navigator.share({ files, title });
            ready.value = null;
            await promise;
            onDone();
        } catch (error) {
            // L'utilisateur a fermé la feuille : rien à rattraper.
            if (error?.name === 'AbortError') {
                ready.value = null;

                return;
            }

            if (error?.name === 'NotAllowedError') {
                ready.value = { key, files, title, onDone, onRefused };
                report('photos.share-needs-tap', { count: files.length, bytes: files.reduce((sum, file) => sum + file.size, 0) });
                toast('Prêt — appuie de nouveau pour l’enregistrer dans Photos.');

                return;
            }

            ready.value = null;
            report('photos.share-failed', { error: String(error?.name ?? error), count: files.length });
            onRefused();
        }
    }

    function shareReady() {
        const { key, files, title, onDone, onRefused } = ready.value;

        return share(key, files, title, onDone, onRefused);
    }

    async function save(media) {
        if (saving.value) {
            return;
        }

        if (ready.value?.key === media.id) {
            return shareReady();
        }

        ready.value = null;

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

            await share(
                media.id,
                [file],
                media.name,
                () => toast(`Choisis « Enregistrer ${media.is_video ? 'la vidéo' : 'l’image'} » : c’est l’original, sans compression.`),
                () => fallback(media),
            );
        } catch (error) {
            report('photos.read-failed', { error: String(error?.message ?? error) });
            fallback(media);
        } finally {
            saving.value = false;
            progress.value = 0;
        }
    }

    /**
     * Plusieurs originaux d'un coup dans la feuille de partage : iOS propose
     * « Enregistrer N images ». Les fichiers sont lus l'un après l'autre ; la
     * progression est globale.
     */
    async function saveMany(mediaList) {
        if (saving.value || !mediaList.length) {
            return;
        }

        const key = mediaList.map((media) => media.id).join(',');

        if (ready.value?.key === key) {
            return shareReady();
        }

        ready.value = null;
        const total = mediaList.reduce((sum, media) => sum + media.size_bytes, 0);

        if (!canShareFiles() || total > SHARE_LIMIT_BYTES) {
            toast('Trop lourd pour la feuille de partage : télécharge le ZIP, ou enregistre les photos une par une.', { error: true });

            return;
        }

        saving.value = true;
        progress.value = 0;
        let received = 0;

        try {
            const files = [];

            for (const media of mediaList) {
                files.push(
                    await readAll(media, (bytes) => {
                        received += bytes;
                        progress.value = total ? received / total : 0;
                    }),
                );
            }

            if (!navigator.canShare({ files })) {
                toast('Le téléphone refuse ce lot dans la feuille de partage : essaie avec moins de fichiers.', { error: true });

                return;
            }

            await share(
                key,
                files,
                `${files.length} fichiers`,
                () => toast(`Choisis « Enregistrer ${files.length} images » : les originaux, sans compression.`),
                () => toast('L’enregistrement dans Photos a échoué : télécharge plutôt le ZIP.', { error: true }),
            );
        } catch (error) {
            report('photos.read-failed', { error: String(error?.message ?? error), count: mediaList.length });
            toast('L’enregistrement dans Photos a échoué : télécharge plutôt le ZIP.', { error: true });
        } finally {
            saving.value = false;
            progress.value = 0;
        }
    }

    function isReady(key) {
        return ready.value?.key === key;
    }

    return { saving, progress, save, saveMany, isReady, isApple: isApplePhotosDevice() };
}
