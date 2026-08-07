<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useToast } from 'vue-toastification'
import { QrCodeIcon, UsersIcon, CheckCircleIcon } from '@heroicons/vue/24/outline'
import api from '@/services/api'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const toast = useToast()

const event = ref<any>(null)
const loading = ref(true)
const canManage = auth.hasPermission('agenda.edit')

onMounted(async () => {
  try {
    const { data } = await api.get(`/events/${route.params.id}`)
    event.value = data
  } catch { toast.error('Gagal memuat agenda.') }
  finally { loading.value = false }
})

const qrUrl = computed(() => {
  if (!event.value) return ''
  const frontendUrl = window.location.origin
  return `${frontendUrl}/presensi/${event.value.qr_token}`
})

const qrImageUrl = computed(() => `https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=${encodeURIComponent(qrUrl.value)}`)

const attendedCount = computed(() => event.value?.attendances?.length ?? 0)

async function toggleOpen() {
  try {
    const { data } = await api.post(`/events/${event.value.id}/toggle-open`)
    event.value.is_open = data.data.is_open
    toast.success(data.message)
  } catch {}
}

async function handleDelete() {
  if (!confirm(`Hapus agenda "${event.value.title}"? Data presensi juga akan dihapus.`)) return
  try {
    await api.delete(`/events/${event.value.id}`)
    toast.success('Agenda berhasil dihapus.')
    router.push('/agenda')
  } catch (e: any) { toast.error(e?.response?.data?.message ?? 'Gagal menghapus.') }
}

function formatDate(d: string) { return new Date(d).toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }) }
</script>

<template>
  <div class="space-y-5 max-w-4xl">
    <div v-if="loading" class="text-center py-12 text-gray-400">Memuat...</div>
    <template v-else-if="event">
      <div class="flex items-start justify-between">
        <div>
          <h1 class="text-xl font-bold text-gray-900">{{ event.title }}</h1>
          <p class="text-sm text-gray-500 mt-0.5">{{ event.organizer ?? '' }} · {{ event.category }}</p>
        </div>
        <div v-if="canManage" class="flex items-center gap-2">
          <button :class="['px-3 py-1.5 text-xs font-medium rounded-lg border', event.is_open ? 'border-red-200 text-red-600 hover:bg-red-50' : 'border-green-200 text-green-600 hover:bg-green-50']" @click="toggleOpen">
            {{ event.is_open ? 'Tutup Presensi' : 'Buka Presensi' }}
          </button>
          <button class="px-3 py-1.5 text-xs font-medium rounded-lg border border-blue-200 text-blue-600 hover:bg-blue-50" @click="router.push(`/agenda/${event.id}/edit`)">Edit</button>
          <button class="px-3 py-1.5 text-xs font-medium rounded-lg border border-red-200 text-red-600 hover:bg-red-50" @click="handleDelete">Hapus</button>
        </div>
      </div>

      <!-- Info -->
      <div class="bg-white rounded-xl border border-gray-200 p-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
          <div><p class="text-xs text-gray-400">Tanggal</p><p class="text-gray-800">{{ formatDate(event.event_date) }}</p></div>
          <div><p class="text-xs text-gray-400">Waktu</p><p class="text-gray-800">{{ event.start_time?.slice(0,5) ?? '-' }} — {{ event.end_time?.slice(0,5) ?? '-' }}</p></div>
          <div><p class="text-xs text-gray-400">Tempat</p><p class="text-gray-800">{{ event.location ?? '-' }}</p></div>
          <div><p class="text-xs text-gray-400">Tipe</p><p class="text-gray-800">{{ event.type }}</p></div>
          <div v-if="event.meeting_link"><p class="text-xs text-gray-400">Link Meeting</p><a :href="event.meeting_link" target="_blank" class="text-blue-600 hover:underline text-sm">Buka Link</a></div>
          <div v-if="event.description"><p class="text-xs text-gray-400">Deskripsi</p><p class="text-gray-800">{{ event.description }}</p></div>
        </div>
      </div>

      <!-- QR Code (Admin) -->
      <div v-if="canManage" class="bg-white rounded-xl border border-gray-200 p-6">
        <div class="flex items-center gap-2 mb-4">
          <QrCodeIcon class="w-5 h-5 text-blue-600" />
          <h2 class="text-sm font-semibold text-gray-800">QR Code Presensi</h2>
        </div>
        <div class="flex items-center gap-6">
          <img :src="qrImageUrl" alt="QR Presensi" class="w-40 h-40 border border-gray-200 rounded-lg" />
          <div class="text-sm space-y-2">
            <p class="text-gray-600">Tampilkan QR Code ini kepada peserta untuk presensi.</p>
            <p class="text-xs text-gray-400">URL: {{ qrUrl }}</p>
            <p :class="['text-xs font-medium', event.is_open ? 'text-green-600' : 'text-red-600']">
              Status: {{ event.is_open ? '✓ Presensi Dibuka' : '✕ Presensi Ditutup' }}
            </p>
          </div>
        </div>
      </div>

      <!-- Daftar Hadir -->
      <div class="bg-white rounded-xl border border-gray-200 p-6">
        <div class="flex items-center gap-2 mb-4">
          <UsersIcon class="w-5 h-5 text-green-600" />
          <h2 class="text-sm font-semibold text-gray-800">Daftar Hadir ({{ attendedCount }})</h2>
        </div>
        <div v-if="!event.attendances?.length" class="text-center text-gray-400 text-sm py-4">Belum ada peserta yang hadir.</div>
        <table v-else class="w-full text-sm">
          <thead>
            <tr class="text-left text-xs text-gray-500 uppercase border-b">
              <th class="pb-2">No</th>
              <th class="pb-2">Nama</th>
              <th class="pb-2">Instansi/Jabatan</th>
              <th class="pb-2">Waktu Hadir</th>
              <th class="pb-2">Metode</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(a, i) in event.attendances" :key="a.id" class="border-t border-gray-50">
              <td class="py-2 text-gray-500">{{ i + 1 }}</td>
              <td class="py-2 font-medium text-gray-800">{{ a.user?.name ?? a.guest_name ?? '-' }}</td>
              <td class="py-2 text-gray-600">{{ a.guest_institution ?? a.guest_position ?? '-' }}</td>
              <td class="py-2 text-xs text-gray-500">{{ a.attended_at }}</td>
              <td class="py-2"><span :class="['px-2 py-0.5 rounded-full text-[10px] font-medium', a.method === 'APP' ? 'bg-blue-100 text-blue-700' : 'bg-amber-100 text-amber-700']">{{ a.method }}</span></td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Peserta Diundang -->
      <div v-if="event.invitees?.length && canManage" class="bg-white rounded-xl border border-gray-200 p-6">
        <h2 class="text-sm font-semibold text-gray-800 mb-3">Peserta Diundang ({{ event.invitees.length }})</h2>
        <div class="flex flex-wrap gap-2">
          <span v-for="u in event.invitees" :key="u.id" class="flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium"
            :class="event.attendances?.some((a: any) => a.user_id === u.id) ? 'bg-green-50 text-green-700' : 'bg-gray-50 text-gray-600'">
            <CheckCircleIcon v-if="event.attendances?.some((a: any) => a.user_id === u.id)" class="w-3.5 h-3.5" />
            {{ u.name }}
          </span>
        </div>
      </div>
    </template>
  </div>
</template>
