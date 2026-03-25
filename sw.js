// sw.js
const CACHE_NAME = 'elprofe-v1';
const ASSETS_TO_CACHE = [
    '/ELPROFE/manifest.json',
    '/ELPROFE/assets/css/style.css',
    '/ELPROFE/assets/js/main.js',
    // Las páginas dinámicas no se cachean en PWA básicas de POS,
    // pero podemos definir una página de fallback o cachear la app shell.
    // De momento, proveemos offline fallback solo para assets gráficos/JS
];

self.addEventListener('install', event => {
    event.waitUntil(
        caches.open(CACHE_NAME).then(cache => {
            return cache.addAll(ASSETS_TO_CACHE);
        })
    );
});

self.addEventListener('fetch', event => {
    // Si la solicitud no es una página PHP o la URL no empieza por HTTP
    if (event.request.method !== 'GET' || !event.request.url.startsWith('http')) return;
    
    event.respondWith(
        fetch(event.request).catch(() => {
            return caches.match(event.request).then(response => {
                // If it's a page and we are offline, we could return a specific offline.html
                return response;
            });
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
