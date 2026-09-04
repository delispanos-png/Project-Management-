/* CloudOn Projects — service worker: PWA + Web Push (VAPID, no-payload).
   Το push είναι «κενό» (ξυπνά μόνο)· εδώ τραβάμε τι να δείξουμε από τον server. */
self.addEventListener('install', e => self.skipWaiting());
self.addEventListener('activate', e => e.waitUntil(self.clients.claim()));
self.addEventListener('fetch', e => { /* network passthrough — καμία cache παγίδα */ });

self.addEventListener('push', e => {
  e.waitUntil((async () => {
    let title = 'CloudOn Projects', body = 'Νέα ειδοποίηση', url = '/project/', tag = 'cnp';
    try {
      const r = await fetch('/project/api.php?a=push_latest', {credentials: 'same-origin', cache: 'no-store'});
      if (r.ok) {
        const d = await r.json();
        if (d && d.title) { title = d.title; body = d.body || ''; url = d.url || '/project/'; tag = 'cnp-' + (d.id || Date.now()); }
      }
    } catch (err) { /* δείξε γενική ειδοποίηση */ }
    await self.registration.showNotification(title, {
      body, tag, renotify: true, timestamp: Date.now(),
      icon: '/project/icon-180.png', badge: '/project/icon-180.png',
      data: {url}, vibrate: [80, 40, 80]
    });
  })());
});

self.addEventListener('notificationclick', e => {
  e.notification.close();
  const url = (e.notification.data && e.notification.data.url) || '/project/';
  e.waitUntil((async () => {
    const all = await self.clients.matchAll({type: 'window', includeUncontrolled: true});
    for (const c of all) {
      if (c.url.includes('/project') && 'focus' in c) { await c.focus(); c.postMessage({type: 'cnp-nav', url}); return; }
    }
    if (self.clients.openWindow) { return self.clients.openWindow(url); }
  })());
});
