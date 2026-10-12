// Service worker: shows offline.html when a page can't be loaded without a
// connection. It makes JalaOps an installable web app, which the Android app
// (a Trusted Web Activity) needs. Only page loads are handled; API calls,
// scripts and map tiles always go to the network.
const CACHE = 'jalaops-offline-v2'
const OFFLINE_PAGE = '/offline.html'

self.addEventListener('install', (event) => {
  event.waitUntil(caches.open(CACHE).then((cache) => cache.add(OFFLINE_PAGE)))
  self.skipWaiting()
})

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) => Promise.all(keys.filter((key) => key !== CACHE).map((key) => caches.delete(key)))),
  )
  self.clients.claim()
})

self.addEventListener('fetch', (event) => {
  if (event.request.mode === 'navigate') {
    event.respondWith(fetch(event.request).catch(() => caches.match(OFFLINE_PAGE)))
  }
})
