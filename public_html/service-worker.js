/**
 * Service Worker for IrmaJosh.com
 * Implements caching strategies and offline support
 */

const CACHE_VERSION = 'v4-20251025-1200';
const CACHE_NAME = `irmajosh-${CACHE_VERSION}`;

// Assets to cache immediately on install
const PRECACHE_ASSETS = [
    '/',
    '/views/offline.html',
    '/assets/css/style.css',
    '/assets/js/app.js',
    '/assets/js/vendor/htmx.min.js',
    '/manifest.json'
];

// Routes that should always fetch from network (exact path or prefix match)
const NETWORK_ONLY_ROUTES = [
    '/auth/',
    '/api/',
    '/csp-report'
];

// Routes that should try network first, then cache (no overlap with network-only)
const NETWORK_FIRST_ROUTES = [
    '/dashboard',
    '/calendar',
    '/tasks',
    '/schedule',
    '/health'
];

// Maximum age for cached responses (7 days)
const MAX_CACHE_AGE = 7 * 24 * 60 * 60 * 1000;

/**
 * Install event - precache essential assets
 */
self.addEventListener('install', event => {
    console.log('[ServiceWorker] Installing...');
    
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then(cache => {
                console.log('[ServiceWorker] Precaching assets');
                return cache.addAll(PRECACHE_ASSETS);
            })
            .then(() => self.skipWaiting())
    );
});

/**
 * Activate event - clean up old caches
 */
self.addEventListener('activate', event => {
    console.log('[ServiceWorker] Activating...');
    
    event.waitUntil(
        caches.keys()
            .then(cacheNames => {
                return Promise.all(
                    cacheNames
                        .filter(name => name !== CACHE_NAME)
                        .map(name => {
                            console.log('[ServiceWorker] Deleting old cache:', name);
                            return caches.delete(name);
                        })
                );
            })
            .then(() => self.clients.claim())
    );
});

/**
 * Fetch event - implement caching strategies
 */
self.addEventListener('fetch', event => {
    const { request } = event;
    const url = new URL(request.url);
    
    // Skip non-GET requests
    if (request.method !== 'GET') {
        return;
    }
    
    // Skip cross-origin requests
    if (url.origin !== location.origin) {
        return;
    }
    
    // Determine strategy based on route
    if (shouldUseNetworkOnly(url.pathname)) {
        event.respondWith(networkOnly(request));
    } else if (shouldUseNetworkFirst(url.pathname)) {
        event.respondWith(networkFirst(request));
    } else {
        event.respondWith(cacheFirst(request));
    }
});

/**
 * Check if route should use network-only strategy
 */
function shouldUseNetworkOnly(pathname) {
    return NETWORK_ONLY_ROUTES.some(route => pathname.startsWith(route));
}

/**
 * Check if route should use network-first strategy
 */
function shouldUseNetworkFirst(pathname) {
    return NETWORK_FIRST_ROUTES.some(route => pathname.startsWith(route));
}

/**
 * Network-only strategy
 */
async function networkOnly(request) {
    try {
        return await fetch(request);
    } catch (error) {
        console.error('[ServiceWorker] Network-only fetch failed:', error);
        throw error;
    }
}

/**
 * Network-first strategy
 * Try network first, fall back to cache if offline
 */
async function networkFirst(request) {
    try {
        const response = await fetch(request);
        
        // Cache successful responses
        if (response.ok) {
            const cache = await caches.open(CACHE_NAME);
            cache.put(request, response.clone());
        }
        
        return response;
    } catch (error) {
        console.log('[ServiceWorker] Network failed, trying cache:', request.url);
        
        const cached = await caches.match(request);
        if (cached) {
            return cached;
        }
        
        // Return offline page for navigation requests
        if (request.mode === 'navigate') {
            const offlinePage = await caches.match('/views/offline.html');
            if (offlinePage) {
                return offlinePage;
            }
        }
        
        throw error;
    }
}

/**
 * Cache-first strategy
 * Try cache first, fall back to network
 */
async function cacheFirst(request) {
    const cached = await caches.match(request);
    
    if (cached) {
        // Check cache age
        const cacheDate = cached.headers.get('sw-cache-date');
        if (cacheDate) {
            const age = Date.now() - parseInt(cacheDate);
            if (age > MAX_CACHE_AGE) {
                console.log('[ServiceWorker] Cache expired, fetching:', request.url);
                return fetchAndCache(request);
            }
        }
        
        // Optionally update cache in background
        fetchAndCache(request).catch(() => {});
        
        return cached;
    }
    
    return fetchAndCache(request);
}

/**
 * Fetch from network and cache the response
 */
async function fetchAndCache(request) {
    try {
        const response = await fetch(request);
        
        // Only cache successful GET requests
        if (response.ok && request.method === 'GET') {
            const cache = await caches.open(CACHE_NAME);
            
            // Add cache date header
            const responseWithDate = new Response(response.body, {
                status: response.status,
                statusText: response.statusText,
                headers: new Headers({
                    ...Object.fromEntries(response.headers.entries()),
                    'sw-cache-date': Date.now().toString()
                })
            });
            
            cache.put(request, responseWithDate.clone());
            return responseWithDate;
        }
        
        return response;
    } catch (error) {
        console.error('[ServiceWorker] Fetch and cache failed:', error.message || error);
        
        // Try to return cached version if available
        const cached = await caches.match(request);
        if (cached) {
            console.log('[ServiceWorker] Returning stale cache after error:', request.url);
            return cached;
        }
        
        // Return offline page for navigation requests
        if (request.mode === 'navigate') {
            const offlinePage = await caches.match('/views/offline.html');
            if (offlinePage) {
                console.log('[ServiceWorker] Returning offline page');
                return offlinePage;
            }
        }
        
        // Re-throw if we can't handle it
        throw error;
    }
}

/**
 * Message event - handle commands from clients
 */
self.addEventListener('message', event => {
    if (event.data && event.data.type === 'SKIP_WAITING') {
        self.skipWaiting();
    }
    
    if (event.data && event.data.type === 'CLEAR_CACHE') {
        event.waitUntil(
            caches.keys()
                .then(names => Promise.all(names.map(name => caches.delete(name))))
                .then(() => {
                    event.ports[0].postMessage({ success: true });
                })
        );
    }
});

console.log('[ServiceWorker] Loaded');
