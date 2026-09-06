import { ref } from 'vue';
import { useToast } from './useToast';

/*
 * « Enregistrer dans Photos » sur iPhone et iPad.
 *
 * Un téléchargement classique (Content-Disposition: attachment) finit dans
 * l'app Fichiers. Pour arriver dans Photos, il faut passer par la feuille de
 * partage d'iOS avec le fichier lui-même : on lit l'original en flux, on le
 * remet tel quel à navigator.share(), et iOS propose « Enregistrer l'image »
 * ou « Enregistrer la vidéo ». Aucun octet n'est transformé — le blob est la
 * réponse HTTP, celle dont l'empreinte est affichée à côté.
 *
 * Quand le partage n'est pas possible (fichier trop gros pour la mémoire du
 * téléphone, type refusé par iOS, partage annulé par une erreur), on affiche
 * l'original inline : un appui long propose alors « Enregistrer dans Photos ».
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

export function useSaveToPhotos() {
    const saving = ref(false);
    const progress = ref(0);
    const { toast } = useToast();

    function openInline(media) {
        toast('Appuie longuement sur l’image, puis « Enregistrer dans Photos ».');
        window.location.assign(media.view_url);
    }

    async function save(media) {
        if (saving.value) {
            return;
        }

        saving.value = true;
        progress.value = 0;

        try {
            const canShareFiles = typeof navigator.share === 'function' && typeof navigator.canShare === 'function';

            if (!canShareFiles || media.size_bytes > SHARE_LIMIT_BYTES) {
                openInline(media);

                return;
            }

            const response = await fetch(media.download_url, { credentials: 'same-origin' });

            if (!response.ok || !response.body) {
                throw new Error(`HTTP ${response.status}`);
            }

            const total = Number(response.headers.get('Content-Length')) || media.size_bytes;
            const reader = response.body.getReader();
            const chunks = [];
            let received = 0;

            for (;;) {
                const { done, value } = await reader.read();

                if (done) {
                    break;
                }

                chunks.push(value);
                received += value.byteLength;
                progress.value = total ? received / total : 0;
            }

            const file = new File(chunks, media.name, { type: media.mime_type || 'application/octet-stream' });

            if (!navigator.canShare({ files: [file] })) {
                openInline(media);

                return;
            }

            await navigator.share({ files: [file], title: media.name });
            toast(`Choisis « Enregistrer ${media.is_video ? 'la vidéo' : 'l’image'} » : c’est l’original, sans compression.`);
        } catch (error) {
            // L'utilisateur a fermé la feuille de partage : rien à rattraper.
            if (error?.name === 'AbortError') {
                return;
            }

            openInline(media);
        } finally {
            saving.value = false;
            progress.value = 0;
        }
    }

    return { saving, progress, save, isApple: isApplePhotosDevice() };
}
