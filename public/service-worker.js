const CACHE_NAME = 'simsp-cache-v2';
const PRECACHE_URLS = ['/manifest.json', '/build/manifest.json'];

self.addEventListener('install', (event) => {
  self.skipWaiting();
  event.waitUntil(caches.open(CACHE_NAME).then((cache) => cache.addAll(PRECACHE_URLS)));
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    Promise.all([
      caches.keys().then((keys) => Promise.all(keys.filter((k) => k !== CACHE_NAME).map((k) => caches.delete(k)))),
      self.clients.claim(),
    ])
  );
});

function isHtmlRequest(request) {
  return request.mode === 'navigate' || (request.headers.get('accept') || '').includes('text/html');
}

function isBuildAsset(url) {
  return url.pathname.startsWith('/build/');
}

function shouldBypassCache(request, url) {
  const accept = request.headers.get('accept') || '';
  const isApi = url.pathname.startsWith('/api/');
  const isInertia = (request.headers.get('x-inertia') || '').toLowerCase() === 'true';
  const isJson = accept.includes('application/json');
  return isApi || isInertia || isJson;
}

self.addEventListener('fetch', (event) => {
  const { request } = event;
  if (request.method !== 'GET') return;

  const url = new URL(request.url);
  if (url.origin !== self.location.origin) return;
  if (shouldBypassCache(request, url)) return;

  // Network-first for HTML navigations to avoid stale UI (Inertia/Vite version mismatch)
  if (isHtmlRequest(request)) {
    event.respondWith(
      fetch(request)
        .then((response) => {
          const copy = response.clone();
          caches.open(CACHE_NAME).then((cache) => cache.put(request, copy)).catch(() => {});
          return response;
        })
        .catch(() => caches.match(request))
    );
    return;
  }

  // Cache-first for hashed build assets (safe to cache)
  if (isBuildAsset(url)) {
    event.respondWith(
      caches.match(request).then((cached) => {
        if (cached) return cached;
        return fetch(request).then((response) => {
          const copy = response.clone();
          caches.open(CACHE_NAME).then((cache) => cache.put(request, copy)).catch(() => {});
          return response;
        });
      })
    );
    return;
  }

  // Default: stale-while-revalidate for static GETs (images/fonts), but avoid caching routes.
  event.respondWith(
    caches.match(request).then((cached) => {
      const fetchPromise = fetch(request)
        .then((response) => {
          const copy = response.clone();
          caches.open(CACHE_NAME).then((cache) => cache.put(request, copy)).catch(() => {});
          return response;
        })
        .catch(() => cached);
      return cached || fetchPromise;
    })
  );
});
