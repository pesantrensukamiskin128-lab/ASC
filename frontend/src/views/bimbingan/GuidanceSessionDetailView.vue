<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useToast } from 'vue-toastification'
import { useAuthStore } from '@/stores/auth'
import { ArrowLeftIcon, PaperAirplaneIcon } from '@heroicons/vue/24/outline'
import api from '@/services/api'

const route = useRoute()
const router = useRouter()
const toast = useToast()
const auth = useAuthStore()
const loading = ref(true)
const session = ref<any>(null)
const notes = ref<any[]>([])
const isLecturer = auth.user?.roles?.includes('DOSEN') || auth.user?.roles?.includes('SUPER_ADMIN')

// Note form
const noteContent = ref('')
const notePrivate = ref(false)
const sendingNote = ref(false)

// Status update
const updatingStatus = ref(false)

const statusColor: Record<string, string> = {
  DIAJUKAN: 'bg-yellow-100 text-yellow-700', DIJADWALKAN: 'bg-blue-100 text-blue-700',
  BERLANGSUNG: 'bg-purple-100 text-purple-700', SELESAI: 'bg-green-100 text-green-700',
  DIBATALKAN: 'bg-gray-100 text-gray-500',
}

onMounted(async () => {
  try {
    const { data } = await api.get(`/guidance/sessions/${route.params.id}`)
    session.value = data.session
    notes.value = data.notes
  } finally { loading.value = false }
})

async function sendNote() {
  if (!noteContent.value.trim()) return
  sendingNote.value = true
  try {
    const { data } = await api.post(`/guidance/sessions/${session.value.id}/notes`, {
      content: noteContent.value,
      is_private: notePrivate.value,
    })
    notes.value.push(data.data)
    noteContent.value = ''
    notePrivate.value = false
  } catch (e: any) { toast.error(e?.response?.data?.message ?? 'Gagal.') }
  finally { sendingNote.value = false }
}

async function updateStatus(status: string) {
  updatingStatus.value = true
  try {
    await api.put(`/guidance/sessions/${session.value.id}/status`, { status })
    session.value.status = status
    toast.success('Status berhasil diupdate.')
  } catch (e: any) { toast.error(e?.response?.data?.message ?? 'Gagal.') }
  finally { updatingStatus.value = false }
}

function formatDate(d: string) { return d ? new Date(d).toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' }) : '-' }
function formatTime(d: string) { return d ? new Date(d).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }) : '' }
</script>

<template>
  <div v-if="loading" class="flex items-center justify-center h-64"><p class="text-gray-400">Memuat...</p></div>
  <div v-else-if="session" class="space-y-6 max-w-3xl mx-auto">
    <!-- Header -->
    <div class="flex items-start gap-3">
      <button class="p-2 rounded-lg hover:bg-gray-100" @click="router.back()"><ArrowLeftIcon class="w-5 h-5 text-gray-500" /></button>
      <div class="flex-1">
        <h1 class="text-xl font-bold text-gray-900">{{ session.topic }}</h1>
        <div class="flex items-center gap-2 mt-1">
          <span class="text-xs px-2 py-0.5 bg-indigo-100 text-indigo-700 rounded font-medium">{{ session.type }}</span>
          <span :class="['px-2 py-0.5 rounded-full text-xs font-medium', statusColor[session.status]]">{{ session.status.replace(/_/g, ' ') }}</span>
          <span class="text-xs text-gray-400">{{ session.mode.replace(/_/g, ' ') }}</span>
        </div>
      </div>
    </div>

    <!-- Info -->
    <div class="bg-white rounded-xl border border-gray-200 p-5 grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
      <div><span class="text-xs text-gray-400">Mahasiswa</span><p class="text-gray-800 font-medium">{{ session.student?.name }}</p><p class="text-xs text-gray-500">{{ session.student?.nim }}</p></div>
      <div><span class="text-xs text-gray-400">Dosen Wali</span><p class="text-gray-800 font-medium">{{ session.advisor?.full_name ?? session.advisor?.name }}</p></div>
      <div><span class="text-xs text-gray-400">Jadwal</span><p class="text-gray-800">{{ formatDate(session.scheduled_date) }}</p><p v-if="session.scheduled_time" class="text-xs text-gray-500">{{ session.scheduled_time }}</p></div>
      <div><span class="text-xs text-gray-400">Lokasi</span><p class="text-gray-800">{{ session.location ?? '-' }}</p></div>
    </div>

    <div v-if="session.description" class="bg-white rounded-xl border border-gray-200 p-5">
      <p class="text-xs text-gray-400 mb-1">Deskripsi</p>
      <p class="text-sm text-gray-700 whitespace-pre-line">{{ session.description }}</p>
    </div>

    <!-- Status actions (dosen) -->
    <div v-if="isLecturer && !['SELESAI', 'DIBATALKAN'].includes(session.status)" class="flex items-center gap-2">
      <button v-if="session.status === 'DIAJUKAN'" :disabled="updatingStatus" class="px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-medium rounded-lg" @click="updateStatus('DIJADWALKAN')">Jadwalkan</button>
      <button v-if="session.status === 'DIJADWALKAN'" :disabled="updatingStatus" class="px-3 py-1.5 bg-purple-600 hover:bg-purple-700 text-white text-xs font-medium rounded-lg" @click="updateStatus('BERLANGSUNG')">Mulai</button>
      <button v-if="['DIJADWALKAN','BERLANGSUNG'].includes(session.status)" :disabled="updatingStatus" class="px-3 py-1.5 bg-green-600 hover:bg-green-700 text-white text-xs font-medium rounded-lg" @click="updateStatus('SELESAI')">Selesai</button>
      <button v-if="session.status !== 'SELESAI'" :disabled="updatingStatus" class="px-3 py-1.5 bg-gray-500 hover:bg-gray-600 text-white text-xs font-medium rounded-lg" @click="updateStatus('DIBATALKAN')">Batalkan</button>
    </div>

    <!-- Chat / Notes -->
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
      <div class="px-5 py-3 border-b border-gray-100">
        <h2 class="text-sm font-semibold text-gray-800">Catatan Bimbingan</h2>
      </div>

      <div class="p-5 space-y-4 max-h-96 overflow-y-auto">
        <div v-if="!notes.length" class="text-center py-6 text-gray-400 text-sm">Belum ada catatan.</div>
        <div v-for="n in notes" :key="n.id" :class="['flex gap-3', n.user_id === auth.user?.id ? 'flex-row-reverse' : '']">
          <div class="w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center text-blue-700 text-xs font-bold shrink-0">
            {{ n.user?.name?.charAt(0) ?? '?' }}
          </div>
          <div :class="['max-w-[70%] rounded-xl px-4 py-2.5', n.user_id === auth.user?.id ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-800']">
            <p class="text-xs font-medium opacity-75 mb-0.5">{{ n.user?.name }}</p>
            <p class="text-sm whitespace-pre-line">{{ n.content }}</p>
            <p class="text-xs opacity-50 mt-1">{{ new Date(n.created_at).toLocaleString('id-ID', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' }) }}</p>
            <span v-if="n.is_private && isLecturer" class="text-xs opacity-50 italic">(privat)</span>
          </div>
        </div>
      </div>

      <!-- Input catatan -->
      <div v-if="!['SELESAI', 'DIBATALKAN'].includes(session.status)" class="border-t border-gray-100 p-4">
        <div class="flex items-end gap-2">
          <div class="flex-1">
            <textarea v-model="noteContent" rows="2" placeholder="Tulis catatan..." class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm resize-none focus:outline-none focus:ring-2 focus:ring-blue-500" @keydown.ctrl.enter="sendNote" />
            <label v-if="isLecturer" class="flex items-center gap-1.5 mt-1 text-xs text-gray-500">
              <input v-model="notePrivate" type="checkbox" class="rounded" /> Privat (hanya dosen)
            </label>
          </div>
          <button :disabled="sendingNote || !noteContent.trim()" class="p-2.5 bg-blue-600 hover:bg-blue-700 disabled:bg-blue-400 text-white rounded-lg" @click="sendNote">
            <PaperAirplaneIcon class="w-4 h-4" />
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
