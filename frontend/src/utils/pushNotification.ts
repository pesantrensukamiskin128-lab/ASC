import api from '@/services/api'

/**
 * Request permission dan subscribe ke push notification.
 * Dipanggil setelah user login.
 */
export async function subscribePushNotification(): Promise<boolean> {
  try {
    // Cek support
    if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
      console.log('Push notification tidak didukung browser ini.')
      return false
    }

    // Minta izin
    const permission = await Notification.requestPermission()
    if (permission !== 'granted') {
      console.log('Push notification ditolak oleh user.')
      return false
    }

    // Ambil VAPID public key dari backend
    const { data } = await api.get('/push/vapid-key')
    if (!data.publicKey) {
      console.log('VAPID key belum dikonfigurasi di server.')
      return false
    }

    // Get service worker registration
    const registration = await navigator.serviceWorker.ready

    // Subscribe
    const subscription = await registration.pushManager.subscribe({
      userVisibleOnly: true,
      applicationServerKey: urlBase64ToUint8Array(data.publicKey),
    })

    // Kirim subscription ke backend
    const sub = subscription.toJSON()
    await api.post('/push/subscribe', {
      endpoint: sub.endpoint,
      keys: {
        p256dh: sub.keys?.p256dh,
        auth: sub.keys?.auth,
      },
    })

    console.log('Push notification berhasil diaktifkan.')
    return true
  } catch (err) {
    console.warn('Gagal subscribe push:', err)
    return false
  }
}

/**
 * Unsubscribe dari push notification.
 */
export async function unsubscribePushNotification(): Promise<void> {
  try {
    const registration = await navigator.serviceWorker.ready
    const subscription = await registration.pushManager.getSubscription()
    if (subscription) {
      await api.post('/push/unsubscribe', { endpoint: subscription.endpoint })
      await subscription.unsubscribe()
    }
  } catch (err) {
    console.warn('Gagal unsubscribe push:', err)
  }
}

/**
 * Cek apakah sudah subscribe.
 */
export async function isPushSubscribed(): Promise<boolean> {
  try {
    if (!('serviceWorker' in navigator)) return false
    const registration = await navigator.serviceWorker.ready
    const subscription = await registration.pushManager.getSubscription()
    return !!subscription
  } catch {
    return false
  }
}

// Helper: convert base64 VAPID key ke Uint8Array
function urlBase64ToUint8Array(base64String: string): Uint8Array {
  const padding = '='.repeat((4 - (base64String.length % 4)) % 4)
  const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/')
  const rawData = window.atob(base64)
  const outputArray = new Uint8Array(rawData.length)
  for (let i = 0; i < rawData.length; ++i) {
    outputArray[i] = rawData.charCodeAt(i)
  }
  return outputArray
}
