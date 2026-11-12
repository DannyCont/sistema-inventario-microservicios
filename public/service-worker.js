'use strict';

const cachePrefix = 'flexialmacen:' + self.registration.scope + ':';
const cacheName = cachePrefix + 'v4';
const shell = new URL('index.php', self.registration.scope).href;
const recursos = [
    shell,
    './assets/css/styles.css',
    './assets/js/almacen-local.js',
    './assets/js/pwa.js',
    './assets/js/app.js',
    './manifest.webmanifest',
    './icons/icon-192.png',
    './icons/icon-512.png'
].map(ruta => new URL(ruta, self.registration.scope).href);

self.addEventListener('install', event => {
    event.waitUntil((async () => {
        const cache = await caches.open(cacheName);
        await cache.addAll(recursos.map(url => new Request(url, { cache: 'reload' })));
        await self.skipWaiting();
    })());
});

self.addEventListener('activate', event => {
    event.waitUntil((async () => {
        const claves = await caches.keys();
        await Promise.all(claves.filter(clave => clave.startsWith(cachePrefix) && clave !== cacheName)
            .map(clave => caches.delete(clave)));
        await self.clients.claim();
    })());
});

self.addEventListener('fetch', event => {
    const request = event.request;
    if (request.method !== 'GET') return;
    const url = new URL(request.url);
    url.search = '';

    if (request.mode === 'navigate' && (url.href === shell || url.href === self.registration.scope)) {
        event.respondWith((async () => {
            const cache = await caches.open(cacheName);
            return (await cache.match(shell)) ?? fetch(request);
        })());
    } else if (recursos.includes(url.href) && url.href !== shell) {
        event.respondWith((async () => {
            const cache = await caches.open(cacheName);
            return (await cache.match(url.href)) ?? fetch(request);
        })());
    }
    // Las peticiones de sesión e inventario siempre van al servidor.
});

self.addEventListener('push', event => {
    event.waitUntil((async () => {
        let datos = {};
        try {
            datos = event.data?.json() ?? {};
        } catch {
            // El aviso conserva el mensaje por defecto.
        }
        await self.registration.showNotification(datos.title ?? 'FlexiAlmacén', {
            body: datos.body ?? 'Abre la app para revisar el inventario.',
            icon: './icons/icon-192.png',
            badge: './icons/icon-192.png',
            tag: datos.tag
        });
        const ventanas = await self.clients.matchAll({ type: 'window' });
        ventanas.forEach(ventana => ventana.postMessage({ tipo: 'inventario-actualizado' }));
    })());
});

self.addEventListener('notificationclick', event => {
    event.notification.close();
    event.waitUntil((async () => {
        const ventanas = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
        const abierta = ventanas.find(ventana => ventana.url.startsWith(self.registration.scope));
        return abierta ? abierta.focus() : self.clients.openWindow(shell);
    })());
});
