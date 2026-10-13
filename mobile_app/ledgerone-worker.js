// Custom offline app-shell service worker for LedgerOne Mobile.
//
// Flutter's own generated flutter_service_worker.js stopped doing real
// caching (it's now just a stub that unregisters itself — see Flutter's
// deprecation of the old offline-caching service worker). Without this,
// opening the app with zero connectivity — the whole point of this app —
// fails at the browser level before Flutter ever boots (iOS Safari shows
// its native "not connected to the internet" page instead of the app).
//
// This worker caches the app shell (HTML/JS/wasm/assets) on install and
// serves it cache-first, so the app itself always opens offline. It
// deliberately never touches /server/api/** requests — those need live
// data, and the app's own ReferenceCache (see lib/core/reference_cache.dart)
// already handles graceful offline fallback for that data at the Dart
// level. This worker is only responsible for the app shell booting at all.
const CACHE_VERSION = '20261013181135';
const CACHE_NAME = 'ledgerone-shell-' + CACHE_VERSION;
const SCOPE = self.registration.scope;

// Core files needed just to boot Flutter and render a screen. Everything
// else (fonts, extra canvaskit variants, etc.) gets cached lazily as the
// app requests them, via the fetch handler below.
const CORE_ASSETS = [
  '',
  'index.html',
  'main.dart.js',
  'flutter.js',
  'flutter_bootstrap.js',
  'manifest.json',
  'favicon.png',
  'canvaskit/canvaskit.js',
  'canvaskit/canvaskit.wasm',
  'canvaskit/chromium/canvaskit.js',
  'canvaskit/chromium/canvaskit.wasm',
  'assets/AssetManifest.bin.json',
  'assets/FontManifest.json',
  'assets/fonts/MaterialIcons-Regular.otf',
];

self.addEventListener('install', (event) => {
  event.waitUntil(
    (async () => {
      const cache = await caches.open(CACHE_NAME);
      await Promise.all(
        CORE_ASSETS.map(async (path) => {
          try {
            const res = await fetch(new URL(path, SCOPE), { cache: 'reload' });
            if (res.ok) await cache.put(new URL(path, SCOPE), res);
          } catch (e) {
            // Missing/renamed asset shouldn't block install — app shell
            // still boots with whatever did cache.
          }
        })
      );
      self.skipWaiting();
    })()
  );
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    (async () => {
      const names = await caches.keys();
      await Promise.all(names.filter((n) => n !== CACHE_NAME).map((n) => caches.delete(n)));
      await self.clients.claim();
    })()
  );
});

self.addEventListener('fetch', (event) => {
  const url = new URL(event.request.url);

  // Never intercept API calls — they need live data, and must fail
  // naturally (not return a stale cached response) when offline so the
  // app's own ReferenceCache fallback logic runs instead.
  if (url.pathname.includes('/server/api/')) return;
  if (url.origin !== self.location.origin) return;
  if (event.request.method !== 'GET') return;

  event.respondWith(
    (async () => {
      const cache = await caches.open(CACHE_NAME);
      const cached = await cache.match(event.request, { ignoreSearch: true });
      if (cached) return cached;
      try {
        const res = await fetch(event.request);
        if (res.ok) cache.put(event.request, res.clone());
        return res;
      } catch (e) {
        // Offline and not cached: for a navigation request, fall back to
        // the app shell itself so Flutter still boots instead of the
        // browser showing its native offline error page.
        if (event.request.mode === 'navigate') {
          const shell = await cache.match(new URL('index.html', SCOPE));
          if (shell) return shell;
        }
        throw e;
      }
    })()
  );
});
