<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useInstitutionStore } from '@/stores/institution'
import { useToast } from 'vue-toastification'
import { QrCodeIcon, UsersIcon, CheckCircleIcon, MapPinIcon, CalendarDaysIcon, ClockIcon } from '@heroicons/vue/24/outline'
import api from '@/services/api'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const institutionStore = useInstitutionStore()
const toast = useToast()

const event = ref<any>(null)
const loading = ref(true)
const canManage = auth.hasPermission('agenda.edit')

const qrUrl = computed(() => {
  if (!event.value) return ''
  return `${window.location.origin}/presensi/${event.value.qr_token}`
})
const qrImageUrl = ref('')
const posterLoading = ref(false)

const attendedCount = computed(() => event.value?.attendances?.length ?? 0)
const hadirApp = computed(() => event.value?.attendances?.filter((a: any) => a.method === 'APP').length ?? 0)
const hadirForm = computed(() => event.value?.attendances?.filter((a: any) => a.method === 'FORM').length ?? 0)

async function loadQrImage() {
  if (!event.value) return
  try {
    const { data } = await api.get(`/events/${event.value.id}/qr-code`)
    qrImageUrl.value = data.qr_image
  } catch {
    qrImageUrl.value = `https://api.qrserver.com/v1/create-qr-code/?size=300x300&color=1e3a8a&data=${encodeURIComponent(qrUrl.value)}&ecc=H`
  }
}

onMounted(async () => {
  try {
    const { data } = await api.get(`/events/${route.params.id}`)
    event.value = data
    loadQrImage()
  } catch { toast.error('Gagal memuat agenda.') }
  finally { loading.value = false }
})

async function toggleOpen() {
  try {
    const { data } = await api.post(`/events/${event.value.id}/toggle-open`)
    event.value.is_open = data.data.is_open
    toast.success(data.message)
  } catch {}
}

async function handleDelete() {
  if (!confirm(`Hapus agenda "${event.value.title}"?`)) return
  try {
    await api.delete(`/events/${event.value.id}`)
    toast.success('Agenda berhasil dihapus.')
    router.push('/agenda')
  } catch (e: any) { toast.error(e?.response?.data?.message ?? 'Gagal.') }
}

async function downloadPoster() {
  if (!event.value || !qrImageUrl.value) return
  posterLoading.value = true
  try {
    const canvas = document.createElement('canvas')
    const W = 800, H = 1100
    canvas.width = W; canvas.height = H
    const ctx = canvas.getContext('2d')!

    // Gradient background
    const grad = ctx.createLinearGradient(0, 0, W, H)
    grad.addColorStop(0, '#1e3a8a')
    grad.addColorStop(0.5, '#1d4ed8')
    grad.addColorStop(1, '#0f766e')
    ctx.fillStyle = grad
    ctx.fillRect(0, 0, W, H)

    // Decorative circles
    ctx.globalAlpha = 0.06
    ctx.fillStyle = '#fff'
    ctx.beginPath(); ctx.arc(W - 60, -60, 200, 0, Math.PI * 2); ctx.fill()
    ctx.beginPath(); ctx.arc(60, H - 60, 160, 0, Math.PI * 2); ctx.fill()
    ctx.globalAlpha = 1

    // Logo institusi resmi (besar seperti kop surat)
    let logoY = 25
    let logoLoaded = false
    try {
      // Fetch logo via API endpoint (avoids CORS issues)
      const apiBase = (api.defaults.baseURL || '').replace(/\/+$/, '')
      const logoRes = await fetch(`${apiBase}/institution/logo`)
      if (logoRes.ok) {
        const logoBlob = await logoRes.blob()
        const logoBlobUrl = URL.createObjectURL(logoBlob)
        const logoImg = new Image()
        await new Promise<void>(r => {
          logoImg.onload = () => {
            const maxH = 110, maxW = 320
            let lW = logoImg.naturalWidth, lH = logoImg.naturalHeight
            const scale = Math.min(maxW / lW, maxH / lH, 1)
            lW *= scale; lH *= scale
            ctx.drawImage(logoImg, (W - lW) / 2, logoY, lW, lH)
            logoY += lH + 12
            logoLoaded = true
            URL.revokeObjectURL(logoBlobUrl)
            r()
          }
          logoImg.onerror = () => { URL.revokeObjectURL(logoBlobUrl); r() }
          logoImg.src = logoBlobUrl
        })
      }
    } catch {}
    // Fallback: PWA icon jika logo institusi gagal
    if (!logoLoaded) {
      try {
        const logoImg = new Image()
        await new Promise<void>(r => {
          logoImg.onload = () => {
            const logoSize = 90
            ctx.drawImage(logoImg, (W - logoSize) / 2, logoY, logoSize, logoSize)
            logoY += logoSize + 12
            r()
          }
          logoImg.onerror = () => { logoY += 10; r() }
          logoImg.src = '/icons/pwa-192x192.png'
        })
      } catch { logoY += 10 }
    }

    // Nama institusi
    ctx.fillStyle = '#fff'
    ctx.font = 'bold 22px Arial'
    ctx.textAlign = 'center'
    ctx.fillText('STAI YAPATA AL-JAWAMI BANDUNG', W / 2, logoY + 10)

    // Scan Disini
    ctx.font = 'bold 42px Arial'
    ctx.fillText('Scan Disini', W / 2, logoY + 65)
    ctx.font = '17px Arial'
    ctx.fillStyle = 'rgba(255,255,255,0.75)'
    ctx.fillText('Scan QR berikut untuk melakukan presensi', W / 2, logoY + 95)

    // === QR block centered vertically & horizontally ===
    // Calculate remaining space between header and footer
    const headerBottom = logoY + 110 // after institution name + "Scan Disini" + subtitle
    const footerTop = H - 60 // footer area
    const availableH = footerTop - headerBottom
    
    // Content below header: QR(336) + gap(50) + title(~36) + gap(35) + badge(36) + gap(86) + steps(~114)
    // Estimate total content height
    const qrBoxH = 336 // qrSize(300) + padding(36)
    const contentAfterQr = 50 + 36 + 35 + 36 + 86 + 114 // title + gaps + badge + gap + steps
    const totalContentH = qrBoxH + contentAfterQr
    
    // Center offset
    const startY = headerBottom + Math.max(0, (availableH - totalContentH) / 2)

    // QR box (centered)
    const qrSize = 300, qrX = (W - qrSize) / 2, qrY = startY
    ctx.fillStyle = '#fff'
    ctx.beginPath(); ctx.roundRect(qrX - 18, qrY - 18, qrSize + 36, qrSize + 36, 16); ctx.fill()

    const qrImg = new Image()
    qrImg.crossOrigin = 'anonymous'
    await new Promise<void>(r => { qrImg.onload = () => { ctx.drawImage(qrImg, qrX, qrY, qrSize, qrSize); r() }; qrImg.onerror = () => r(); qrImg.src = qrImageUrl.value })

    // Title agenda
    let lineY = qrY + qrSize + 60
    ctx.fillStyle = '#fff'
    ctx.font = 'bold 28px Arial'
    ctx.textAlign = 'center'
    const words = event.value.title.toUpperCase().split(' ')
    let line = ''
    for (const w of words) {
      if (ctx.measureText(line + w + ' ').width > W - 80 && line) { ctx.fillText(line.trim(), W / 2, lineY); line = w + ' '; lineY += 36 } else { line += w + ' ' }
    }
    ctx.fillText(line.trim(), W / 2, lineY)

    // Badge tanggal
    const dateStr = new Date(event.value.event_date).toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' })
    const timeStr = event.value.start_time ? `${event.value.start_time.slice(0,5)} - ${event.value.end_time?.slice(0,5) ?? 'Selesai'} WIB` : ''
    const badge = `${dateStr}${timeStr ? ', ' + timeStr : ''}`
    ctx.font = '17px Arial'
    const bW = ctx.measureText(badge).width + 44
    const bY = lineY + 35
    ctx.fillStyle = 'rgba(255,255,255,0.15)'
    ctx.beginPath(); ctx.roundRect((W - bW) / 2, bY, bW, 36, 18); ctx.fill()
    ctx.fillStyle = '#fff'; ctx.fillText(badge, W / 2, bY + 24)

    // Langkah presensi (ukuran lebih besar) — tambah jarak 12pt (16px) dari badge
    const sY = bY + 36 + 16 + 20
    ctx.fillStyle = '#fff'
    ctx.font = 'bold 20px Arial'
    ctx.textAlign = 'center'
    ctx.fillText('Cara Presensi via Aplikasi:', W / 2, sY)

    ctx.font = '19px Arial'
    ctx.fillStyle = 'rgba(255,255,255,0.9)'
    ctx.fillText('① Buka Aplikasi ASC   ② Tap Scan QR   ③ Konfirmasi Hadir', W / 2, sY + 34)

    ctx.fillStyle = '#fff'
    ctx.font = 'bold 20px Arial'
    ctx.fillText('Cara Presensi tanpa Login:', W / 2, sY + 80)

    ctx.font = '19px Arial'
    ctx.fillStyle = 'rgba(255,255,255,0.9)'
    ctx.fillText('① Scan QR dengan Kamera   ② Isi Form   ③ Kirim Kehadiran', W / 2, sY + 114)

    // Footer with app logo
    ctx.fillStyle = 'rgba(0,0,0,0.3)'; ctx.fillRect(0, H - 60, W, 60)

    // App icon kecil di footer
    try {
      const appIcon = new Image()
      await new Promise<void>(r => {
        appIcon.onload = () => {
          const iconSize = 28
          ctx.drawImage(appIcon, W / 2 - 150, H - 45, iconSize, iconSize)
          ctx.fillStyle = '#fff'; ctx.font = 'bold 13px Arial'; ctx.textAlign = 'left'
          ctx.fillText('Al-Jawami Smart Campus', W / 2 - 115, H - 35)
          ctx.font = '11px Arial'; ctx.fillStyle = 'rgba(255,255,255,0.7)'
          ctx.fillText('Sistem Informasi Akademik Terpadu', W / 2 - 115, H - 20)
          r()
        }
        appIcon.onerror = () => {
          ctx.fillStyle = '#fff'; ctx.font = 'bold 13px Arial'; ctx.textAlign = 'center'
          ctx.fillText('Al-Jawami Smart Campus — Sistem Informasi Akademik Terpadu', W / 2, H - 28)
          r()
        }
        appIcon.src = '/icons/pwa-192x192.png'
      })
    } catch {
      ctx.fillStyle = '#fff'; ctx.font = 'bold 13px Arial'; ctx.textAlign = 'center'
      ctx.fillText('Al-Jawami Smart Campus — Sistem Informasi Akademik Terpadu', W / 2, H - 28)
    }

    const link = document.createElement('a')
    link.download = `QR-${event.value.title.replace(/[^a-zA-Z0-9]/g, '-')}.png`
    link.href = canvas.toDataURL('image/png')
    document.body.appendChild(link); link.click(); document.body.removeChild(link)
    toast.success('Poster QR berhasil didownload.')
  } catch { toast.error('Gagal membuat poster.') }
  finally { posterLoading.value = false }
}

function formatDate(d: string) { return new Date(d).toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }) }
function formatTime(t: string | null) { return t ? t.slice(0, 5) : '-' }

async function downloadAttendance(format: 'excel' | 'pdf') {
  try {
    const url = `/events/${event.value.id}/export-${format}`
    const res = await api.get(url, { responseType: 'blob' })
    const blob = new Blob([res.data], { type: format === 'excel' ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' : 'application/pdf' })
    const link = document.createElement('a')
    link.href = URL.createObjectURL(blob)
    link.download = `Daftar-Hadir-${event.value.title.replace(/[^a-zA-Z0-9]/g, '-')}.${format === 'excel' ? 'xlsx' : 'pdf'}`
    document.body.appendChild(link); link.click(); document.body.removeChild(link)
    URL.revokeObjectURL(link.href)
    toast.success(`Daftar hadir berhasil didownload (${format.toUpperCase()}).`)
  } catch { toast.error('Gagal mengunduh daftar hadir.') }
}
</script>

<template>
  <div class="space-y-6 max-w-6xl mx-auto">
    <div v-if="loading" class="text-center py-12 text-gray-400">Memuat...</div>
    <template v-else-if="event">

      <!-- Hero Header -->
      <div class="relative rounded-2xl overflow-hidden bg-gradient-to-br from-blue-800 via-blue-700 to-indigo-900 text-white">
        <div class="absolute inset-0 opacity-[0.06]">
          <div class="absolute top-0 right-0 w-64 h-64 bg-white rounded-full -translate-y-32 translate-x-32"></div>
          <div class="absolute bottom-0 left-0 w-48 h-48 bg-white rounded-full translate-y-24 -translate-x-24"></div>
        </div>
        <div class="relative p-6 md:p-8">
          <!-- Actions -->
          <div v-if="canManage" class="flex flex-wrap gap-2 mb-4">
            <button :class="['text-xs font-medium px-3 py-1 rounded-full border', event.is_open ? 'border-red-300 text-red-200 hover:bg-red-500/20' : 'border-green-300 text-green-200 hover:bg-green-500/20']" @click="toggleOpen">
              {{ event.is_open ? '🔒 Tutup Presensi' : '🔓 Buka Presensi' }}
            </button>
            <button class="text-xs font-medium px-3 py-1 rounded-full border border-white/30 text-white/80 hover:bg-white/10" @click="router.push(`/agenda/${event.id}/edit`)">✏️ Edit</button>
            <button class="text-xs font-medium px-3 py-1 rounded-full border border-red-300/50 text-red-200 hover:bg-red-500/20" @click="handleDelete">🗑️ Hapus</button>
          </div>

          <!-- Badges -->
          <div class="flex flex-wrap gap-2 mb-3">
            <span class="text-xs font-medium bg-white/15 backdrop-blur-sm px-3 py-1 rounded-full border border-white/20">{{ event.category }}</span>
            <span class="text-xs font-medium bg-white/15 backdrop-blur-sm px-3 py-1 rounded-full border border-white/20">{{ event.type }}</span>
            <span :class="['text-xs font-medium px-3 py-1 rounded-full', event.is_open ? 'bg-green-500/30 text-green-200' : 'bg-red-500/30 text-red-200']">
              {{ event.is_open ? '✓ Presensi Dibuka' : '✕ Presensi Ditutup' }}
            </span>
          </div>

          <h1 class="text-2xl md:text-3xl font-bold mb-6 leading-tight">{{ event.title }}</h1>

          <!-- Info cards -->
          <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
            <div class="bg-white/10 backdrop-blur-sm rounded-xl p-3">
              <p class="text-blue-200 text-[10px] mb-0.5 flex items-center gap-1"><CalendarDaysIcon class="w-3 h-3" />Tanggal</p>
              <p class="font-semibold text-sm">{{ formatDate(event.event_date) }}</p>
            </div>
            <div class="bg-white/10 backdrop-blur-sm rounded-xl p-3">
              <p class="text-blue-200 text-[10px] mb-0.5 flex items-center gap-1"><ClockIcon class="w-3 h-3" />Waktu</p>
              <p class="font-semibold text-sm">{{ formatTime(event.start_time) }} – {{ formatTime(event.end_time) }}</p>
            </div>
            <div class="bg-white/10 backdrop-blur-sm rounded-xl p-3">
              <p class="text-blue-200 text-[10px] mb-0.5 flex items-center gap-1"><MapPinIcon class="w-3 h-3" />Tempat</p>
              <p class="font-semibold text-sm">{{ event.location ?? '-' }}</p>
            </div>
            <div class="bg-white/10 backdrop-blur-sm rounded-xl p-3">
              <p class="text-blue-200 text-[10px] mb-0.5 flex items-center gap-1"><UsersIcon class="w-3 h-3" />Penyelenggara</p>
              <p class="font-semibold text-sm">{{ event.organizer ?? '-' }}</p>
            </div>
          </div>

          <p v-if="event.description" class="mt-4 text-blue-100 text-sm bg-white/10 rounded-xl p-3">{{ event.description }}</p>
        </div>
      </div>

      <!-- Content: 2 kolom di desktop -->
      <div class="grid lg:grid-cols-5 gap-6">

        <!-- LEFT: Stat + Daftar Hadir (3/5) -->
        <div class="lg:col-span-3 space-y-4">
          <!-- Stat cards -->
          <div class="grid grid-cols-3 gap-3">
            <div class="bg-white rounded-2xl border border-gray-100 p-4 text-center">
              <p class="text-3xl font-bold text-gray-900">{{ attendedCount }}</p>
              <p class="text-xs text-gray-500 mt-1">Total Hadir</p>
            </div>
            <div class="bg-emerald-50 rounded-2xl border border-emerald-100 p-4 text-center">
              <p class="text-3xl font-bold text-emerald-600">{{ hadirApp }}</p>
              <p class="text-xs text-emerald-600 mt-1">Via Aplikasi</p>
            </div>
            <div class="bg-blue-50 rounded-2xl border border-blue-100 p-4 text-center">
              <p class="text-3xl font-bold text-blue-600">{{ hadirForm }}</p>
              <p class="text-xs text-blue-600 mt-1">Via Form</p>
            </div>
          </div>

          <!-- Tabel kehadiran -->
          <div class="bg-white rounded-2xl border border-gray-100 overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-50 flex items-center justify-between">
              <h2 class="font-semibold text-gray-900 flex items-center gap-2">
                <UsersIcon class="w-5 h-5 text-blue-600" /> Daftar Hadir
              </h2>
              <div v-if="canManage && event.attendances?.length" class="flex items-center gap-2">
                <button class="inline-flex items-center gap-1 px-2.5 py-1.5 text-xs font-medium text-green-700 bg-green-50 hover:bg-green-100 border border-green-200 rounded-lg" @click="downloadAttendance('excel')">📊 Excel</button>
                <button class="inline-flex items-center gap-1 px-2.5 py-1.5 text-xs font-medium text-red-700 bg-red-50 hover:bg-red-100 border border-red-200 rounded-lg" @click="downloadAttendance('pdf')">📄 PDF</button>
              </div>
            </div>
            <div v-if="!event.attendances?.length" class="py-12 text-center">
              <UsersIcon class="w-10 h-10 text-gray-200 mx-auto mb-3" />
              <p class="text-sm text-gray-400">Belum ada peserta yang hadir</p>
            </div>
            <table v-else class="w-full text-sm">
              <thead><tr class="bg-gray-50 text-xs text-gray-500 uppercase tracking-wide">
                <th class="text-left px-5 py-3">No</th><th class="text-left px-5 py-3">Nama</th><th class="text-left px-5 py-3 hidden sm:table-cell">Instansi</th><th class="text-left px-5 py-3">Waktu</th><th class="text-left px-5 py-3">Metode</th>
              </tr></thead>
              <tbody>
                <tr v-for="(a, i) in event.attendances" :key="a.id" class="border-t border-gray-50 hover:bg-gray-50">
                  <td class="px-5 py-2.5 text-gray-400">{{ i + 1 }}</td>
                  <td class="px-5 py-2.5 font-medium text-gray-800">{{ a.user?.name ?? a.guest_name ?? '-' }}</td>
                  <td class="px-5 py-2.5 text-gray-500 hidden sm:table-cell">{{ a.guest_institution ?? a.guest_position ?? '-' }}</td>
                  <td class="px-5 py-2.5 text-xs text-gray-500 font-mono">{{ a.attended_at?.split('T')[1]?.slice(0,5) ?? '-' }}</td>
                  <td class="px-5 py-2.5"><span :class="['px-2 py-0.5 rounded-full text-[10px] font-semibold', a.method === 'APP' ? 'bg-emerald-100 text-emerald-700 border border-emerald-200' : 'bg-blue-100 text-blue-700 border border-blue-200']">{{ a.method }}</span></td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- RIGHT: QR + Peserta diundang (2/5) -->
        <div v-if="canManage" class="lg:col-span-2 space-y-4">
          <!-- QR Card -->
          <div class="bg-white rounded-2xl border border-gray-100 p-5">
            <h2 class="font-semibold text-gray-900 mb-1">Scan Disini</h2>
            <p class="text-xs text-gray-400 mb-4">Scan QR berikut untuk melakukan presensi</p>
            <div class="flex justify-center mb-4">
              <div class="p-3 border-2 border-gray-100 rounded-2xl bg-white shadow-sm">
                <img v-if="qrImageUrl" :src="qrImageUrl" alt="QR" class="w-48 h-48" />
                <div v-else class="w-48 h-48 bg-gray-50 rounded-xl flex items-center justify-center"><QrCodeIcon class="w-12 h-12 text-gray-300" /></div>
              </div>
            </div>
            <button :disabled="posterLoading" class="w-full flex items-center justify-center gap-2 bg-blue-900 hover:bg-blue-800 text-white text-sm font-medium py-2.5 rounded-xl" @click="downloadPoster">
              {{ posterLoading ? '⏳ Membuat...' : '📥 Download Poster QR' }}
            </button>
            <div class="mt-4 space-y-2">
              <div v-for="(step, i) in ['Buka Aplikasi ASC', 'Tap menu Scan QR', 'Arahkan kamera ke QR']" :key="i" class="flex items-start gap-2.5 text-xs text-gray-500">
                <span class="w-5 h-5 bg-blue-600 text-white rounded-full flex items-center justify-center text-[10px] font-bold shrink-0">{{ i + 1 }}</span>
                <span>{{ step }}</span>
              </div>
            </div>
          </div>

          <!-- Peserta diundang -->
          <div v-if="event.invitees?.length" class="bg-white rounded-2xl border border-gray-100 p-5">
            <h2 class="font-semibold text-gray-900 mb-3 flex items-center gap-2">
              <UsersIcon class="w-4 h-4 text-blue-600" /> Peserta Diundang
              <span class="text-xs bg-gray-100 text-gray-500 px-2 py-0.5 rounded-full ml-auto">{{ event.invitees.length }}</span>
            </h2>
            <div class="space-y-1.5 max-h-64 overflow-y-auto">
              <div v-for="u in event.invitees" :key="u.id" class="flex items-center gap-2.5 p-2 rounded-xl hover:bg-gray-50">
                <div class="w-7 h-7 rounded-full bg-blue-100 flex items-center justify-center text-xs font-semibold text-blue-700 shrink-0">{{ u.name?.charAt(0) }}</div>
                <span class="text-sm text-gray-800 flex-1 truncate">{{ u.name }}</span>
                <CheckCircleIcon v-if="event.attendances?.some((a: any) => a.user_id === u.id)" class="w-4 h-4 text-emerald-500 shrink-0" />
              </div>
            </div>
          </div>
        </div>

      </div>
    </template>
  </div>
</template>
