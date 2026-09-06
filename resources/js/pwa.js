/**
 * Enregistre le service worker qui rend l'application installable et lui donne un
 * écran hors ligne. Il est volontairement absent en développement, pour ne pas
 * servir d'anciens assets à la place de ceux du serveur Vite.
 */
export function registerServiceWorker() {
    if (import.meta.env.DEV || !('serviceWorker' in navigator)) {
        return;
    }

    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js').catch(() => {
            // Un enregistrement refusé (navigation privée, réglage navigateur) ne
            // doit pas empêcher l'application de fonctionner normalement.
        });
    });
}
