/* Affreso service worker: installable app shell, offline page and push notifications.
   Private pages are never cached — only static assets and the offline page. */
const CACHE = 'affreso-v3';
const SCOPE = self.registration.scope;
const OFFLINE = SCOPE + 'index.php?r=%2Foffline';
const ASSETS = ['assets/css/app.css', 'assets/js/app.js', 'assets/icons/icon-192.png'].map(p => SCOPE + p);

self.addEventListener('install', e => {
  e.waitUntil(caches.open(CACHE).then(c => c.addAll(ASSETS.concat([OFFLINE]))).then(() => self.skipWaiting()));
});
self.addEventListener('activate', e => {
  e.waitUntil(caches.keys().then(keys => Promise.all(keys.filter(k => k !== CACHE).map(k => caches.delete(k)))).then(() => self.clients.claim()));
});
self.addEventListener('fetch', e => {
  const req = e.request;
  if (req.method !== 'GET') return;
  const url = new URL(req.url);
  if (url.pathname.includes('/assets/')) {
    e.respondWith(caches.match(req, { ignoreSearch: true }).then(hit => hit || fetch(req)));
    return;
  }
  if (req.mode === 'navigate') {
    e.respondWith(fetch(req).catch(() => caches.match(OFFLINE)));
  }
});
function show(title, body, tag, link) {
  return self.registration.showNotification(title || 'Affreso', {
    body: body || 'You have a new notification',
    icon: SCOPE + 'assets/icons/icon-192.png',
    badge: SCOPE + 'assets/icons/icon-192.png',
    tag: tag || 'affreso',
    data: { url: link ? new URL(link, SCOPE).href : SCOPE + 'index.php?r=%2Fnotifications' }
  });
}
self.addEventListener('push', e => {
  let msg = null;
  try { msg = e.data ? e.data.json() : null; } catch (err) { msg = null; }
  if (msg && msg.body) {
    e.waitUntil(show(msg.title, msg.body, 'n' + msg.id, msg.url));
    return;
  }
  e.waitUntil(
    fetch(SCOPE + 'index.php?r=%2Fnotifications%2Ffeed', { credentials: 'include', headers: { Accept: 'application/json' } })
      .then(r => (r.headers.get('content-type') || '').includes('json') ? r.json() : null)
      .catch(() => null)
      .then(data => {
        const item = data && data.items && data.items[0];
        return show(data && data.church, item && item.title, item && 'n' + item.id, item && item.link);
      })
  );
});
self.addEventListener('notificationclick', e => {
  e.notification.close();
  e.waitUntil(clients.openWindow(e.notification.data.url));
});
