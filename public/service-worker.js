const CACHE_NAME = 'simsp-cache-v5';
const OFFLINE_CACHE_NAME = 'simsp-offline-v1';

// Precache critical URLs
const PRECACHE_URLS = [
  '/manifest.json',
  '/offline.html'
];

// Cache strategies
const CACHE_STRATEGIES = {
  NETWORK_FIRST: 'network-first',
  CACHE_FIRST: 'cache-first',
  STALE_WHILE_REVALIDATE: 'stale-while-revalidate',
  NETWORK_ONLY: 'network-only',
  CACHE_ONLY: 'cache-only'
};

// Install event - precache critical assets
self.addEventListener('install', (event) => {
  self.skipWaiting();
  event.waitUntil(
    Promise.all([
      caches.open(CACHE_NAME).then((cache) => cache.addAll(PRECACHE_URLS)),
      caches.open(OFFLINE_CACHE_NAME).then((cache) => cache.addAll(['/offline.html']))
    ])
  );
});

// Activate event - clean up old caches
self.addEventListener('activate', (event) => {
  event.waitUntil(
    Promise.all([
      // Delete old caches
      caches.keys().then((keys) => 
        Promise.all(keys.filter((k) => k !== CACHE_NAME && k !== OFFLINE_CACHE_NAME).map((k) => caches.delete(k)))
      ),
      // Take control of all clients immediately
      self.clients.claim(),
    ])
  );
});

// Helper functions
function isHtmlRequest(request) {
  return request.mode === 'navigate' || (request.headers.get('accept') || '').includes('text/html');
}

function isBuildAsset(url) {
  return url.pathname.startsWith('/build/');
}

function isBuildManifest(url) {
  return url.pathname === '/build/manifest.json';
}

function isApiRequest(url) {
  return url.pathname.startsWith('/api/');
}

function isImageRequest(url) {
  return /\.(jpg|jpeg|png|gif|webp|svg|ico)$/i.test(url.pathname);
}

function isAuthRoute(url) {
  return url.pathname.startsWith('/auth/');
}

function shouldBypassCache(request, url) {
  const accept = request.headers.get('accept') || '';
  const isInertia = (request.headers.get('x-inertia') || '').toLowerCase() === 'true';
  const isJson = accept.includes('application/json');
  return isInertia || isJson;
}

// Network-first strategy (for HTML, build manifest)
function networkFirst(request) {
  return fetch(request)
    .then((response) => {
      if (!response || response.status !== 200 || response.type !== 'basic') {
        throw new Error('Network response not ok');
      }
      const copy = response.clone();
      caches.open(CACHE_NAME).then((cache) => cache.put(request, copy)).catch(() => {});
      return response;
    })
    .catch(() => caches.match(request))
    .then((cached) => cached || caches.match('/offline.html'))
    .catch(() => caches.match('/offline.html'));
}

// Cache-first strategy (for build assets)
function cacheFirst(request) {
  return caches.match(request).then((cached) => {
    if (cached) return cached;
    return fetch(request).then((response) => {
      if (!response || response.status !== 200) {
        throw new Error('Network response not ok');
      }
      const copy = response.clone();
      caches.open(CACHE_NAME).then((cache) => cache.put(request, copy)).catch(() => {});
      return response;
    });
  });
}

// Stale-while-revalidate strategy (for images, static assets)
function staleWhileRevalidate(request) {
  return caches.match(request).then((cached) => {
    const fetchPromise = fetch(request)
      .then((response) => {
        if (response && response.status === 200) {
          const copy = response.clone();
          caches.open(CACHE_NAME).then((cache) => cache.put(request, copy)).catch(() => {});
        }
        return response;
      })
      .catch(() => cached);
    return cached || fetchPromise;
  });
}

// Network-only strategy (for API with special handling)
function networkOnly(request) {
  return fetch(request)
    .catch(() => {
      // Return cached version if available (fallback)
      return caches.match(request).then((cached) => {
        if (cached) {
          // Return cached with a warning header
          const headers = new Headers(cached.headers);
          headers.append('X-SW-Cache', 'FALLBACK');
          return new Response(cached.body, {
            status: 200,
            statusText: 'OK (Cached)',
            headers: headers
          });
        }
        // Return offline response for API
        return new Response(JSON.stringify({ error: 'Offline', message: 'No network connection' }), {
          status: 503,
          headers: { 'Content-Type': 'application/json' }
        });
      });
    });
}

// Main fetch event handler
self.addEventListener('fetch', (event) => {
  const { request } = event;
  if (request.method !== 'GET') return;

  const url = new URL(request.url);
  
  // Ignore cross-origin requests
  if (url.origin !== self.location.origin) return;

  // Bypass cache for Inertia/JSON requests
  if (shouldBypassCache(request, url)) return;

  // Bypass service worker for OAuth/auth routes to allow proper redirects
  if (isAuthRoute(url)) return;

  // HTML navigation requests - Network First
  if (isHtmlRequest(request)) {
    event.respondWith(networkFirst(request));
    return;
  }

  // Build manifest - Network First (avoid stale mappings)
  if (isBuildManifest(url)) {
    event.respondWith(networkFirst(request));
    return;
  }

  // Build assets - Cache First (safe to cache, hashed names)
  if (isBuildAsset(url)) {
    event.respondWith(cacheFirst(request));
    return;
  }

  // Image requests - Stale While Revalidate
  if (isImageRequest(url)) {
    event.respondWith(staleWhileRevalidate(request));
    return;
  }

  // API requests - Network Only with fallback
  if (isApiRequest(url)) {
    event.respondWith(networkOnly(request));
    return;
  }

  // Default: Stale While Revalidate
  event.respondWith(staleWhileRevalidate(request));
});

// Message event for cache management
self.addEventListener('message', (event) => {
  if (event.data && event.data.type === 'SKIP_WAITING') {
    self.skipWaiting();
  }
  
  if (event.data && event.data.type === 'CACHE_URLS') {
    event.waitUntil(
      caches.open(CACHE_NAME).then((cache) => cache.addAll(event.data.urls))
    );
  }
  
  if (event.data && event.data.type === 'CLEAR_CACHE') {
    event.waitUntil(
      caches.keys().then((keys) => 
        Promise.all(keys.map((k) => caches.delete(k)))
      )
    );
  }
});
