// Minimal service worker for desktop notification support.
// This file intentionally does nothing beyond enabling
// ServiceWorkerRegistration.showNotification().

self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', (event) => event.waitUntil(self.clients.claim()));
