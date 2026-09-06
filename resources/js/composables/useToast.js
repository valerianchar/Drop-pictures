/**
 * Notifications locales, sans aller-retour serveur (« Lien copié »). Le
 * FlashToast écoute l'événement et l'affiche comme un message flash.
 */
export function useToast() {
    function toast(message, { error = false } = {}) {
        document.dispatchEvent(new CustomEvent('drop:toast', { detail: { message, error } }));
    }

    return { toast };
}
