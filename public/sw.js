// TerraLink CRM / client portal service worker.
// Makes the app installable and keeps static files (CSS, JS, icons, fonts) on the device.
// Pages and data are NEVER cached: they always come live from the server, so nothing
// private is left on a shared tablet and nobody sees stale tickets or invoices.
const CACHE = 'tl-static-1.18.1';
const OFFLINE = '/offline.html';

self.addEventListener('install', e => {
  e.waitUntil(caches.open(CACHE).then(c => c.addAll([OFFLINE, '/assets/favicon.svg', '/assets/portal-favicon.svg'])).then(() => self.skipWaiting()));
});

self.addEventListener('activate', e => {
  e.waitUntil(caches.keys().then(keys => Promise.all(keys.filter(k => k.startsWith('tl-') && k !== CACHE).map(k => caches.delete(k)))).then(() => self.clients.claim()));
});

self.addEventListener('fetch', e => {
  const req = e.request;
  const url = new URL(req.url);
  if (url.origin !== location.origin) return;

  // Page loads (incl. form posts): always the network; show the offline page if there's no signal.
  if (req.mode === 'navigate') {
    e.respondWith(fetch(req).catch(() => caches.match(OFFLINE)));
    return;
  }

  // Versioned static files: cache first, then network (?v= changes on every release).
  if (req.method === 'GET' && url.pathname.startsWith('/assets/')) {
    e.respondWith(caches.open(CACHE).then(c => c.match(req).then(hit => hit || fetch(req).then(res => {
      if (res.ok && res.type === 'basic') c.put(req, res.clone());
      return res;
    }))));
  }
  // Everything else (files, photos, PDFs, API): straight to the network, untouched.
});
