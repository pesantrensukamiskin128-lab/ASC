<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useToast } from 'vue-toastification'
import { CheckCircleIcon, XCircleIcon } from '@heroicons/vue/24/outline'
import api from '@/services/api'

const route = useRoute()
const auth = useAuthStore()
const toast = useToast()

const token = route.params.token as string
const event = ref<any>(null)
const loading = ref(true)
const submitting = ref(false)
const result = ref<'success' | 'error' | null>(null)
const resultMsg = ref('')
const isLoggedIn = computed(() => !!auth.token)

// Form tamu
const guestForm = ref({ guest_name: '', guest_phone: '', guest_institution: '', guest_position: '' })

onMounted(async () => {
  try {
    const { data } = await api.get(`/events/token/${token}`)
    event.value = data
  } catch {
    result.value = 'error'
    resultMsg.value = 'Agenda tidak ditemukan atau QR Code tidak valid.'
  } finally { loading.value = false }
})

async function attendAsUser() {
  submitting.value = true
  try {
    const { data } = await api.post(`/events/attend/${token}`)
    result.value = 'success'
    resultMsg.value = data.message
  } catch (e: any) {
    result.value = 'error'
    resultMsg.value = e?.response?.data?.message ?? 'Gagal melakukan presensi.'
  } finally { submitting.value = false }
}

async function attendAsGuest() {
  if (!guestForm.value.guest_name.trim()) { toast.error('Nama wajib diisi.'); return }
  submitting.value = true
  try {
    const { data } = await api.post(`/events/token/${token}/attend-public`, guestForm.value)
    result.value = 'success'
    resultMsg.value = data.message
  } catch (e: any) {
    result.value = 'error'
    resultMsg.value = e?.response?.data?.message ?? 'Gagal melakukan presensi.'
  } finally { submitting.value = false }
}

function formatDate(d: string) { return new Date(d).toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }) }
</script>

<template>
  <div class="min-h-screen bg-gray-50 flex items-start justify-center pt-10 px-4">
    <div class="w-full max-w-md">
      <div v-if="loading" class="text-center py-12 text-gray-400">Memuat...</div>

      <!-- Result -->
      <div v-else-if="result" class="text-center">
        <div :class="['inline-flex items-center justify-center w-16 h-16 rounded-full mb-4', result === 'success' ? 'bg-green-100' : 'bg-red-100']">
          <CheckCircleIcon v-if="result === 'success'" class="w-8 h-8 text-green-600" />
          <XCircleIcon v-else class="w-8 h-8 text-red-600" />
        </div>
        <h1 class="text-xl font-bold" :class="result === 'success' ? 'text-green-700' : 'text-red-700'">
          {{ result === 'success' ? 'Presensi Berhasil!' : 'Gagal' }}
        </h1>
        <p class="text-sm text-gray-600 mt-2">{{ resultMsg }}</p>
        <div v-if="event && result === 'success'" class="mt-4 bg-white rounded-xl border p-4 text-left text-sm">
          <p class="text-gray-500">Agenda:</p>
          <p class="font-semibold text-gray-900">{{ event.title }}</p>
          <p class="text-gray-500 mt-1">{{ formatDate(event.event_date) }}</p>
        </div>
      </div>

      <!-- Attendance Form -->
      <template v-else-if="event">
        <div class="bg-white rounded-2xl shadow-lg p-6">
          <div class="text-center mb-6">
            <h1 class="text-lg font-bold text-gray-900">Presensi Kegiatan</h1>
            <p class="text-sm text-gray-500 mt-1">{{ event.title }}</p>
            <p class="text-xs text-gray-400 mt-0.5">{{ formatDate(event.event_date) }} · {{ event.location ?? '' }}</p>
          </div>

          <div v-if="!event.is_open" class="text-center py-6">
            <p class="text-red-600 font-medium">Presensi untuk agenda ini sudah ditutup.</p>
          </div>

          <template v-else>
            <!-- User Login -->
            <div v-if="isLoggedIn" class="space-y-4">
              <div class="bg-blue-50 rounded-lg p-4 text-center">
                <p class="text-sm text-blue-700">Anda login sebagai:</p>
                <p class="font-semibold text-blue-900 mt-1">{{ auth.user?.name }}</p>
              </div>
              <button :disabled="submitting" class="w-full py-2.5 bg-green-600 hover:bg-green-700 disabled:bg-green-400 text-white text-sm font-semibold rounded-xl" @click="attendAsUser">
                {{ submitting ? 'Memproses...' : '✓ Konfirmasi Hadir' }}
              </button>
            </div>

            <!-- Guest Form -->
            <div v-else class="space-y-4">
              <p class="text-sm text-gray-600 text-center">Isi data Anda untuk presensi:</p>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Nama Lengkap <span class="text-red-500">*</span></label>
                <input v-model="guestForm.guest_name" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" placeholder="Nama lengkap" />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">No. HP</label>
                <input v-model="guestForm.guest_phone" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" placeholder="08xxxxxxxxxx" />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Instansi</label>
                <input v-model="guestForm.guest_institution" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" placeholder="Nama instansi/lembaga" />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Jabatan</label>
                <input v-model="guestForm.guest_position" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" placeholder="Jabatan" />
              </div>
              <button :disabled="submitting" class="w-full py-2.5 bg-green-600 hover:bg-green-700 disabled:bg-green-400 text-white text-sm font-semibold rounded-xl" @click="attendAsGuest">
                {{ submitting ? 'Memproses...' : '✓ Kirim Kehadiran' }}
              </button>
            </div>
          </template>
        </div>
      </template>
    </div>
  </div>
</template>
