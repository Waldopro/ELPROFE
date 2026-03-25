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
    // Si la solicitud no es GET o no es HTTP, se omite
    if (event.request.method !== 'GET' || !event.request.url.startsWith('http')) return;
    
    event.respondWith(
        fetch(event.request).catch(() => {
            return caches.match(event.request).then(response => {
                if (response) return response;
                
                // Si falla fetch() por red, y el cache no tiene el archivo (paginas dinamicas PHP)
                // Se debe retornar una clase <Response> para no bloquear ServiceWorker.
                return new Response(
                    "Error de Conexión: Estás sin conexión o el servidor no responde. No se puede cargar esta pantalla dinámica.", 
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
