/**
 * كاشير سوبر ماركت المنزل السوري - Service Worker v2026.1
 * يدعم التثبيت كـ PWA والعمل بدون إنترنت مع جلب التحديثات فوراً (Network-First).
 */

const CACHE_NAME = 'syrian-home-pos-v2026-1';
const SHELL_ASSETS = [
  './',
  './index.html',
  './manifest.json'
];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => {
      return cache.addAll(SHELL_ASSETS).catch(err => {
        console.warn('POS Cache install warning:', err);
      });
    }).then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) => {
      return Promise.all(
        keys.filter((key) => key !== CACHE_NAME).map((key) => {
          console.log('Clearing old POS cache:', key);
          return caches.delete(key);
        })
      );
    }).then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', (event) => {
  const url = new URL(event.request.url);

  // Skip non-GET, API sync calls, or extension requests
  if (
    event.request.method !== 'GET' ||
    url.pathname.includes('api_sync.php') ||
    url.pathname.includes('checkout.php') ||
    url.protocol.startsWith('chrome-extension')
  ) {
    return;
  }

  // Network-First for HTML and scripts so the user always sees the latest updates
  const isHtmlOrScript =
    event.request.mode === 'navigate' ||
    url.pathname.endsWith('.html') ||
    url.pathname.endsWith('.js') ||
    url.pathname.endsWith('/') ||
    url.pathname.endsWith('/pos');

  if (isHtmlOrScript) {
    event.respondWith(
      fetch(event.request)
        .then((networkResponse) => {
          if (networkResponse && networkResponse.status === 200) {
            const responseClone = networkResponse.clone();
            caches.open(CACHE_NAME).then((cache) => cache.put(event.request, responseClone));
          }
          return networkResponse;
        })
        .catch(() => caches.match(event.request))
    );
    return;
  }

  // Cache-First for static assets (images, fonts, icons)
  event.respondWith(
    caches.match(event.request).then((cachedResponse) => {
      if (cachedResponse) {
        // Background refresh
        fetch(event.request).then((networkResponse) => {
          if (networkResponse && networkResponse.status === 200) {
            caches.open(CACHE_NAME).then((cache) => cache.put(event.request, networkResponse));
          }
        }).catch(() => {});
        return cachedResponse;
      }
      return fetch(event.request);
    })
  );
});
