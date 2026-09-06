/*
 * Service worker de Drop Picture.
 *
 * Parti pris : les pages HTML ne sont jamais mises en cache — la galerie
 * change à chaque dépôt et un cache survivrait à la déconnexion. Sont
 * conservés : les assets construits (nom empreinté, donc immuables), la
 * coquille hors ligne, et les aperçus déjà vus (un dérivé réduit, jamais le
 * fichier d'origine, qui se télécharge toujours en direct).
 */

const CACHE_VERSION = 'drop-picture-v1';
const THUMBNAIL_CACHE = 'drop-picture-thumbnails-v1';
const OFFLINE_PAGE = '/offline.html';
const THUMBNAIL_LIMIT = 400;

const SHELL_ASSETS = [
    OFFLINE_PAGE,
    '/manifest.webmanifest',
    '/icons/icon.svg',
    '/icons/icon-192.png',
    '/icons/icon-512.png',
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_VERSION)
            .then((cache) => cache.addAll(SHELL_ASSETS))
            .then(() => self.skipWaiting()),
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((names) => Promise.all(
                names.filter((name) => name !== CACHE_VERSION && name !== THUMBNAIL_CACHE).map((name) => caches.delete(name)),
            ))
            .then(() => self.clients.claim()),
    );
});

self.addEventListener('fetch', (event) => {
    const { request } = event;
    const url = new URL(request.url);

    if (request.method !== 'GET' || url.origin !== self.location.origin) {
        return;
    }

    // Les visites Inertia attendent du JSON : leur répondre du HTML en cache
    // casserait la navigation. Elles restent donc strictement en ligne.
    if (request.headers.get('X-Inertia')) {
        return;
    }

    if (url.pathname.startsWith('/build/')) {
        event.respondWith(cacheFirst(request, CACHE_VERSION));

        return;
    }

    // Les aperçus : cache d'abord, avec un plafond pour ne pas remplir l'appareil.
    if (/^\/fichiers\/\d+\/apercu$/.test(url.pathname)) {
        event.respondWith(cacheFirst(request, THUMBNAIL_CACHE, THUMBNAIL_LIMIT));

        return;
    }

    if (request.mode === 'navigate') {
        event.respondWith(networkThenOfflinePage(request));
    }
});

async function cacheFirst(request, cacheName, limit = null) {
    const cache = await caches.open(cacheName);
    const cached = await cache.match(request);

    if (cached) {
        return cached;
    }

    const response = await fetch(request);

    if (response.ok) {
        cache.put(request, response.clone());

        if (limit !== null) {
            trim(cache, limit);
        }
    }

    return response;
}

async function trim(cache, limit) {
    const keys = await cache.keys();

    if (keys.length > limit) {
        await Promise.all(keys.slice(0, keys.length - limit).map((key) => cache.delete(key)));
    }
}

async function networkThenOfflinePage(request) {
    try {
        return await fetch(request);
    } catch {
        const cache = await caches.open(CACHE_VERSION);

        return (
            (await cache.match(OFFLINE_PAGE)) ??
            new Response('Hors ligne', { status: 503, headers: { 'Content-Type': 'text/plain; charset=utf-8' } })
        );
    }
}
