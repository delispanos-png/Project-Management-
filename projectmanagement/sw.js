/* CloudOn Projects — minimal service worker (installability, network-first) */
self.addEventListener('install', e => self.skipWaiting());
self.addEventListener('activate', e => e.waitUntil(clients.claim()));
self.addEventListener('fetch', e => { /* network passthrough — καμία cache παγίδα */ });
