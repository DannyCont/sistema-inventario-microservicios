
const CACHE_NAME = "inventia-static-v1";
const OFFLINE_URL = "./offline.html";

self.addEventListener("install", (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => {
            return cache.add(OFFLINE_URL);
        })
    );

    self.skipWaiting();
});

self.addEventListener("activate", (event) => {
    event.waitUntil(
        caches.keys().then((keys) => {
            return Promise.all(
                keys
                    .filter((key) =>
                        key.startsWith("inventia-") &&
                        key !== CACHE_NAME
                    )
                    .map((key) => caches.delete(key))
            );
        })
    );

    self.clients.claim();
});

self.addEventListener("fetch", (event) => {
    const request = event.request;
    const url = new URL(request.url);

    // Solo solicitudes GET del mismo origen.
    if (
        request.method !== "GET" ||
        url.origin !== self.location.origin
    ) {
        return;
    }

    // Nunca interceptar endpoints ni páginas PHP.
    if (
        url.pathname.endsWith(".php") ||
        url.pathname.includes("/api/") ||
        url.pathname.includes("/servicio-")
    ) {
        return;
    }

    // No almacenar otros tipos de recursos dinámicos.
    const isStaticAsset =
        /\.(css|js|png|jpg|jpeg|webp|svg|ico|woff2?)$/i
            .test(url.pathname);

    if (isStaticAsset) {
        event.respondWith(
            caches.open(CACHE_NAME).then(async (cache) => {
                const cached = await cache.match(request);

                if (cached) {
                    return cached;
                }

                const response = await fetch(request);

                if (response.ok && response.type === "basic") {
                    await cache.put(request, response.clone());
                }

                return response;
            })
        );

        return;
    }

    // Las páginas se consultan al servidor.
    // Si no hay conexión, mostrar el respaldo.
    if (request.mode === "navigate") {
        event.respondWith(
            fetch(request).catch(async () => {
                const cache = await caches.open(CACHE_NAME);
                return (
                    await cache.match(OFFLINE_URL)
                ) || Response.error();
            })
        );
    }
});
