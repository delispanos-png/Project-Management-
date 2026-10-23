/* CloudOn Projects — service worker: PWA + Web Push (VAPID, no-payload).
   Το push είναι «κενό» (ξυπνά μόνο)· εδώ τραβάμε τι να δείξουμε από τον server.
   sw build: 2026-10-23a — ειδοποιήσεις Εισερχομένων: δεύτερη γραμμή, tag ανά εργασία, κουμπί «Άνοιγμα» */
self.addEventListener('install', e => self.skipWaiting());
self.addEventListener('activate', e => e.waitUntil((async () => {
  await self.clients.claim();
  /* Καθάρισε τυχόν παλιές caches από προηγούμενες εκδόσεις SW — δεν θέλουμε καμία.
     (Ο έλεγχος νέας έκδοσης & το reload γίνονται app-side, γραφικά, χωρίς loop.) */
  try { const ks = await caches.keys(); await Promise.all(ks.map(k => caches.delete(k))); } catch (x) {}
})()));
self.addEventListener('fetch', e => { /* network passthrough — καμία cache παγίδα */ });

self.addEventListener('push', e => {
  e.waitUntil((async () => {
    let title = 'CloudOn Projects', body = 'Νέα ειδοποίηση', url = '/project/', tag = 'cnp', kind = '';
    try {
      const r = await fetch('/project/api.php?a=push_latest', {credentials: 'same-origin', cache: 'no-store'});
      if (r.ok) {
        const d = await r.json();
        if (d && d.title) { title = d.title; body = d.body || ''; url = d.url || '/project/'; tag = d.tag || ('cnp-' + (d.id || Date.now())); kind = d.kind || ''; }
      }
    } catch (err) { /* δείξε γενική ειδοποίηση */ }
    /* Ίδια εργασία = ίδιο tag: η νέα ειδοποίηση ΑΝΤΙΚΑΘΙΣΤΑ την παλιά (renotify: ξαναχτυπά). */
    await self.registration.showNotification(title, {
      body, tag, renotify: true, timestamp: Date.now(),
      icon: '/project/icon-192.png', badge: '/project/icon-192.png',
      actions: kind === 'inbox' ? [{action: 'open', title: 'Άνοιγμα εργασίας'}, {action: 'feed', title: 'Εισερχόμενα'}] : [],
      data: {url}, vibrate: [80, 40, 80]
    });
  })());
});

self.addEventListener('notificationclick', e => {
  e.notification.close();
  const url = e.action === 'feed' ? '/project/#/feed' : ((e.notification.data && e.notification.data.url) || '/project/');
  e.waitUntil((async () => {
    const all = await self.clients.matchAll({type: 'window', includeUncontrolled: true});
    for (const c of all) {
      if (c.url.includes('/project') && 'focus' in c) { await c.focus(); c.postMessage({type: 'cnp-nav', url}); return; }
    }
    if (self.clients.openWindow) { return self.clients.openWindow(url); }
  })());
});
