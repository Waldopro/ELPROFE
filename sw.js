// sw.js
const CACHE_NAME = 'elprofe-v1';
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
    const isAsset = ASSETS_TO_CACHE.some(asset => url.pathname.endsWith(asset));

    event.respondWith(
        fetch(event.request).catch(() => {
            return caches.match(event.request).then(response => {
                if (response) return response;
                
                // Solo si es un asset conocido y falla, dar error
                if (isAsset) {
                    return new Response("Asset offline y no cacheado.", { status: 404 });
                }
                
                // Si es una página PHP y estamos offline sin cache
                return new Response(
                    "Error de Conexión: Estás sin conexión o el servidor no responde.", 
                    {
                        status: 503,
                        statusText: "Service Unavailable",
                        headers: new Headers({"Content-Type": "text/plain; charset=utf-8"})
                    }
                );
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
