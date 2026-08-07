// Push event handler — dipanggil saat push notification diterima
self.addEventListener('push', function (event) {
  const data = event.data ? event.data.json() : {}

  const title = data.title || 'Al-Jawami Smart Campus'
  const options = {
    body: data.body || '',
    icon: data.icon || '/icons/pwa-192x192.png',
    badge: data.badge || '/icons/pwa-192x192.png',
    data: { url: data.url || '/' },
    vibrate: [200, 100, 200],
    tag: 'asc-notification',
    renotify: true,
  }

  event.waitUntil(self.registration.showNotification(title, options))
})

// Notification click — buka halaman terkait
self.addEventListener('notificationclick', function (event) {
  event.notification.close()

  const url = event.notification.data?.url || '/'

  event.waitUntil(
    clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (clientList) {
      // Cek apakah ada window yang sudah terbuka
      for (const client of clientList) {
        if (client.url.includes(url) && 'focus' in client) {
          return client.focus()
        }
      }
      // Buka window baru
      if (clients.openWindow) {
        return clients.openWindow(url)
      }
    })
  )
})
