import { router } from '@inertiajs/vue3';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

/*
 * Temps réel via le Soketi de la pile (protocole Pusher). Le navigateur
 * s'abonne à son canal privé — l'aperçu d'un fichier est prêt, un original
 * reconstruit — et, sur la page d'un groupe, au canal du groupe : ce qu'un
 * membre y dépose apparaît chez les autres sans recharger.
 *
 * Sans clé dans les props partagées, rien ne se connecte : l'app vit très bien
 * sans temps réel.
 */

let echo = null;
let currentUserId = null;
let reloadTimer = null;
const pendingReload = new Set();
const groupSubscriptions = new Map();
// Une page de groupe peut demander son canal avant que la connexion existe : on la sert plus tard.
const wantedGroups = new Set();

function toast(message, asError = false) {
    document.dispatchEvent(new CustomEvent('drop:toast', { detail: { message, error: asError } }));
}

/* Plusieurs événements coup sur coup — un dépôt de dix photos — ne rechargent qu'une fois, avec l'union des props demandées. */
function quietReload(only) {
    only.forEach((key) => pendingReload.add(key));
    clearTimeout(reloadTimer);
    reloadTimer = setTimeout(() => {
        const keys = [...pendingReload];
        pendingReload.clear();
        router.reload({ only: keys, preserveScroll: true });
    }, 400);
}

function xsrfToken() {
    const cookie = document.cookie.split('; ').find((row) => row.startsWith('XSRF-TOKEN='));

    return cookie ? decodeURIComponent(cookie.split('=')[1]) : '';
}

export function connectRealtime(broadcast) {
    if (!broadcast?.key || !broadcast.user_id) {
        return;
    }

    // Un autre compte s'est connecté dans le même onglet : on repart de zéro.
    if (echo !== null && broadcast.user_id !== currentUserId) {
        disconnectRealtime();
    }

    if (echo !== null) {
        return;
    }

    window.Pusher = Pusher;
    currentUserId = broadcast.user_id;

    /* Hôte absent = même domaine que la page, en wss derrière Traefik. */
    const scheme = broadcast.scheme ?? 'https';
    const port = Number(broadcast.port ?? (scheme === 'https' ? 443 : 80));

    echo = new Echo({
        broadcaster: 'pusher',
        key: broadcast.key,
        wsHost: broadcast.host || window.location.hostname,
        wsPort: port,
        wssPort: port,
        forceTLS: scheme === 'https',
        enabledTransports: ['ws', 'wss'],
        cluster: 'mt1',
        disableStats: true,
        auth: { headers: { 'X-XSRF-TOKEN': xsrfToken() } },
    });

    echo.private(`users.${broadcast.user_id}`)
        .listen('.media.processed', () => quietReload(['media', 'storage']))
        .listen('.media.restored', (event) => {
            toast(`« ${event.name} » est de retour, prêt à télécharger.`);
            quietReload(['media', 'storage']);
        });

    wantedGroups.forEach((groupId) => subscribeGroup(groupId));
}

export function disconnectRealtime() {
    if (echo !== null) {
        echo.disconnect();
    }

    echo = null;
    currentUserId = null;
    groupSubscriptions.clear();
}

/**
 * La page d'un groupe s'abonne à son canal ; elle se désabonne en partant.
 * Appelée avant la connexion (la page se monte avant la coquille), la demande
 * attend et sera honorée par connectRealtime.
 */
export function subscribeGroup(groupId) {
    wantedGroups.add(groupId);

    if (echo === null || groupSubscriptions.has(groupId)) {
        return;
    }

    const channel = echo
        .private(`groups.${groupId}`)
        .listen('.media.added', (event) => {
            if (event.actor_id !== currentUserId) {
                toast(`${event.actor_name} a ajouté « ${event.name} » — en qualité d'origine.`);
            }

            quietReload(['media', 'group']);
        })
        .listen('.media.removed', (event) => {
            if (event.actor_id !== currentUserId) {
                toast(`« ${event.name} » a été retiré du groupe.`);
            }

            quietReload(['media', 'group']);
        })
        .listen('.media.processed', () => quietReload(['media']))
        .listen('.media.restored', () => quietReload(['media']));

    groupSubscriptions.set(groupId, channel);
}

export function unsubscribeGroup(groupId) {
    wantedGroups.delete(groupId);

    if (echo === null || !groupSubscriptions.has(groupId)) {
        return;
    }

    echo.leave(`groups.${groupId}`);
    groupSubscriptions.delete(groupId);
}
