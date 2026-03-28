// sw.js
const CACHE_NAME = 'elprofe-v2';
const ASSETS_TO_CACHE = [
    '/ELPROFE/manifest.json',
    '/ELPROFE/assets/css/style.css',
    '/ELPROFE/assets/js/main.js',
    '/ELPROFE/assets/img/logo.png',
    '/ELPROFE/assets/img/favicon.ico',
    '/ELPROFE/assets/img/android-icon-192x192.png'
];

self.addEventListener('install', event => {
    event.waitUntil(
        caches.open(CACHE_NAME).then(cache => {
            return cache.addAll(ASSETS_TO_CACHE);
        })
    );
});

self.addEventListener('fetch', event => {
    // Si la solicitud no es GET o no es HTTP, se omite
    if (event.request.method !== 'GET' || !event.request.url.startsWith('http')) return;

    const url = new URL(event.request.url);
    const isAsset = ASSETS_TO_CACHE.some(asset => url.pathname.endsWith(asset)) ||
        url.pathname.startsWith('/ELPROFE/assets/');

    // No interceptar API ni páginas dinámicas para evitar falsos 503.
    if (!isAsset) return;

    event.respondWith(
        caches.match(event.request).then((cached) => {
            const networkFetch = fetch(event.request)
                .then((response) => {
                    const cloned = response.clone();
                    caches.open(CACHE_NAME).then((cache) => cache.put(event.request, cloned)).catch(() => {});
                    return response;
                })
                .catch(() => cached || new Response("Asset offline y no cacheado.", { status: 404 }));
            return cached || networkFetch;
        })
    );
});

self.addEventListener('activate', event => {
    event.waitUntil(
        caches.keys().then(cacheNames => {
            return Promise.all(
                cacheNames.filter(name => name !== CACHE_NAME)
                          .map(name => caches.delete(name))
            );
        })
    );
});
