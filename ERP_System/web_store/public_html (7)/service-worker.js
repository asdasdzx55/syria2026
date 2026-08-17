const CACHE_NAME = 'hudue-pwa-cache-v1';
const ASSETS_TO_CACHE = [
  'index.php',
  'manifest.json',
  'uploads/logo.jpg',
  'uploads/logo_180.png',
  'uploads/logo_192.png',
  'uploads/logo_512.png'
];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => {
      return cache.addAll(ASSETS_TO_CACHE);
    })
  );
});

self.addEventListener('fetch', (event) => {
  event.respondWith(
    caches.match(event.request).then((cachedResponse) => {
      if (cachedResponse) {
        return cachedResponse;
      }
      return fetch(event.request);
    })
  );
});
