/* CloudOn Projects — service worker: PWA + Web Push (VAPID, no-payload).
   Το push είναι «κενό» (ξυπνά μόνο)· εδώ τραβάμε τι να δείξουμε από τον server.
   sw build: 2026-10-26a — ένα ξύπνημα = μόνο ό,τι δεν έχει δείξει η συσκευή; 2026-10-23b — κλικ σε ειδοποίηση εργασίας = νέα καρτέλα κατευθείαν στην εργασία; — ειδοποιήσεις Εισερχομένων: δεύτερη γραμμή, tag ανά εργασία, κουμπί «Άνοιγμα» */
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
    /* (26/10/2026) Στέλνουμε το endpoint μας: ο server δίνει ΜΟΝΟ όσα δεν έχει δείξει ΑΥΤΗ η συσκευή.
       Στο άνοιγμα του PC έφταναν δεκάδες ξυπνήματα και το καθένα έδειχνε ξανά την τελευταία. */
    let items = null, generic = true;
    try {
      const sub = await self.registration.pushManager.getSubscription();
      const ep = sub ? '&ep=' + encodeURIComponent(sub.endpoint) : '';
      const r = await fetch('/project/api.php?a=push_latest' + ep, {credentials: 'same-origin', cache: 'no-store'});
      if (r.ok) {
        const d = await r.json();
        generic = false;
        items = Array.isArray(d.items) ? d.items : (d && d.title ? [d] : []);
      }
    } catch (err) { /* δείξε γενική ειδοποίηση */ }
    if (generic) { items = [{title: 'CloudOn Projects', body: 'Νέα ειδοποίηση', url: '/project/', tag: 'cnp'}]; }
    /* Τίποτα καινούργιο για αυτή τη συσκευή (διπλό ξύπνημα / το είδες ήδη στην εφαρμογή) → σιωπή. */
    for (const d of items) {
      const kind = d.kind || '';
      /* Ίδια εργασία = ίδιο tag: η νέα ειδοποίηση ΑΝΤΙΚΑΘΙΣΤΑ την παλιά (renotify: ξαναχτυπά). */
      await self.registration.showNotification(d.title, {
        body: d.body || '', tag: d.tag || ('cnp-' + (d.id || Date.now())), renotify: true, timestamp: Date.now(),
        icon: '/project/icon-192.png', badge: '/project/icon-192.png',
        actions: kind === 'inbox' ? [{action: 'open', title: 'Άνοιγμα εργασίας'}, {action: 'feed', title: 'Εισερχόμενα'}] : [],
        data: {url: d.url || '/project/', kind}, vibrate: [80, 40, 80]
      });
    }
  })());
});

self.addEventListener('notificationclick', e => {
  e.notification.close();
  const data = e.notification.data || {};
  const url = e.action === 'feed' ? '/project/#/feed' : (data.url || '/project/');
  /* Ειδοποίηση ΕΡΓΑΣΙΑΣ (Εισερχόμενα): ΝΕΑ καρτέλα κατευθείαν στην εργασία. Το «?n=1» λέει στην
     εφαρμογή ότι ήρθες από ειδοποίηση — ανοίγει μόνο την εργασία, χωρίς τα αναδυόμενα της αρχικής. */
  if (data.kind === 'inbox' || /#\/task\/\d+/.test(url)) {
    e.waitUntil(self.clients.openWindow(url.replace('/project/#', '/project/?n=1#')));
    return;
  }
  e.waitUntil((async () => {
    const all = await self.clients.matchAll({type: 'window', includeUncontrolled: true});
    for (const c of all) {
      if (c.url.includes('/project') && 'focus' in c) { await c.focus(); c.postMessage({type: 'cnp-nav', url}); return; }
    }
    if (self.clients.openWindow) { return self.clients.openWindow(url); }
  })());
});
