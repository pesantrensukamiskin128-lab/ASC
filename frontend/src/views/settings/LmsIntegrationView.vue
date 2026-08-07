<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { useToast } from 'vue-toastification'
import api from '@/services/api'

const toast = useToast()
const loading = ref(true)
const saving = ref(false)
const testing = ref(false)
const syncing = ref(false)
const syncType = ref('')

const config = reactive({ base_url: '', api_token: '', is_active: true })
const currentConfig = ref<any>(null)
const logs = ref<any[]>([])
const syncResults = ref<any[]>([])

onMounted(async () => {
  try {
    const [cfgRes, logRes] = await Promise.all([
      api.get('/lms/config'),
      api.get('/lms/logs', { params: { per_page: 10 } }),
    ])
    currentConfig.value = cfgRes.data.config
    if (currentConfig.value) {
      config.base_url = currentConfig.value.base_url
      config.is_active = currentConfig.value.is_active
    }
    logs.value = logRes.data.data ?? []
  } catch {} finally { loading.value = false }
})

async function saveConfig() {
  if (!config.base_url || !config.api_token) {
    toast.error('URL dan API Token wajib diisi.'); return
  }
  saving.value = true
  try {
    await api.post('/lms/config', config)
    toast.success('Konfigurasi LMS berhasil disimpan.')
    currentConfig.value = { base_url: config.base_url, is_active: config.is_active, has_token: true }
  } catch (e: any) { toast.error(e?.response?.data?.message ?? 'Gagal.') }
  finally { saving.value = false }
}

async function testConnection() {
  testing.value = true
  try {
    const { data } = await api.post('/lms/test-connection')
    toast.success(data.message)
  } catch (e: any) { toast.error(e?.response?.data?.message ?? 'Gagal terhubung.') }
  finally { testing.value = false }
}

async function handleSyncAll() {
  if (!confirm('Sinkronkan semua data (users, courses, classes, enrollments) ke LMS?')) return
  syncing.value = true
  syncType.value = 'all'
  syncResults.value = []
  try {
    const { data } = await api.post('/lms/sync-all')
    toast.success(data.message)
    syncResults.value = data.results ?? []
    // Refresh logs
    const { data: logData } = await api.get('/lms/logs', { params: { per_page: 10 } })
    logs.value = logData.data ?? []
  } catch (e: any) { toast.error(e?.response?.data?.message ?? 'Gagal.') }
  finally { syncing.value = false; syncType.value = '' }
}

async function handleSyncType(type: string) {
  syncing.value = true
  syncType.value = type
  try {
    const { data } = await api.post(`/lms/sync/${type}`)
    toast.success(data.message)
    syncResults.value = [data.result]
    const { data: logData } = await api.get('/lms/logs', { params: { per_page: 10 } })
    logs.value = logData.data ?? []
  } catch (e: any) { toast.error(e?.response?.data?.message ?? 'Gagal.') }
  finally { syncing.value = false; syncType.value = '' }
}

const statusColor: Record<string, string> = {
  success: 'bg-green-100 text-green-700',
  partial: 'bg-yellow-100 text-yellow-700',
  failed: 'bg-red-100 text-red-700',
}
const typeLabel: Record<string, string> = {
  users: 'Pengguna', courses: 'Mata Kuliah', classes: 'Kelas', enrollments: 'KRS/Enrollment',
}
</script>

<template>
  <div class="space-y-6 max-w-4xl">
    <div>
      <h1 class="text-xl font-bold text-gray-900">Integrasi LMS</h1>
      <p class="text-sm text-gray-500 mt-0.5">Sinkronisasi data akademik dari ASC ke Learning Management System</p>
    </div>

    <div v-if="loading" class="text-center py-12 text-gray-400">Memuat...</div>
    <template v-else>

      <!-- Config -->
      <div class="bg-white rounded-xl border border-gray-200 p-6 space-y-4">
        <h2 class="text-sm font-semibold text-gray-800 uppercase tracking-wide">Konfigurasi Koneksi</h2>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Base URL API LMS <span class="text-red-500">*</span></label>
          <input v-model="config.base_url" type="url" placeholder="https://lms.stai-aljawami.ac.id/api" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">API Token <span class="text-red-500">*</span></label>
          <input v-model="config.api_token" type="password" :placeholder="currentConfig?.has_token ? '••••••• (sudah tersimpan, isi ulang untuk ganti)' : 'stai_xxxxxxxxx'" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" />
          <p class="text-xs text-gray-400 mt-1">Token dibuat dari menu Integrasi API di LMS</p>
        </div>
        <div class="flex items-center gap-2">
          <input v-model="config.is_active" type="checkbox" id="lms_active" class="rounded" />
          <label for="lms_active" class="text-sm text-gray-700">Aktifkan integrasi</label>
        </div>
        <div class="flex gap-3">
          <button :disabled="saving" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 disabled:bg-blue-400 text-white text-sm font-medium rounded-lg" @click="saveConfig">
            {{ saving ? 'Menyimpan...' : 'Simpan Konfigurasi' }}
          </button>
          <button v-if="currentConfig?.has_token" :disabled="testing" class="px-4 py-2 border border-gray-300 text-gray-700 hover:bg-gray-50 text-sm font-medium rounded-lg" @click="testConnection">
            {{ testing ? 'Testing...' : '🔌 Test Koneksi' }}
          </button>
        </div>
        <div v-if="currentConfig?.last_sync_at" class="text-xs text-gray-400">
          Sinkronisasi terakhir: {{ currentConfig.last_sync_at }}
        </div>
      </div>

      <!-- Sync Actions -->
      <div v-if="currentConfig?.has_token" class="bg-white rounded-xl border border-gray-200 p-6 space-y-4">
        <h2 class="text-sm font-semibold text-gray-800 uppercase tracking-wide">Sinkronisasi Data</h2>
        <p class="text-xs text-gray-500">Push data dari ASC ke LMS. Data yang dikirim: pengguna (mahasiswa & dosen), mata kuliah, kelas semester aktif, dan KRS yang sudah disetujui.</p>

        <div class="flex flex-wrap gap-3">
          <button :disabled="syncing" class="px-4 py-2 bg-green-600 hover:bg-green-700 disabled:bg-green-400 text-white text-sm font-medium rounded-lg" @click="handleSyncAll">
            {{ syncing && syncType === 'all' ? '⏳ Memproses...' : '🔄 Sync Semua' }}
          </button>
          <button :disabled="syncing" class="px-3 py-2 border border-blue-300 text-blue-700 hover:bg-blue-50 text-xs font-medium rounded-lg" @click="handleSyncType('users')">
            {{ syncing && syncType === 'users' ? '⏳...' : '👤 Sync Users' }}
          </button>
          <button :disabled="syncing" class="px-3 py-2 border border-blue-300 text-blue-700 hover:bg-blue-50 text-xs font-medium rounded-lg" @click="handleSyncType('courses')">
            {{ syncing && syncType === 'courses' ? '⏳...' : '📚 Sync MK' }}
          </button>
          <button :disabled="syncing" class="px-3 py-2 border border-blue-300 text-blue-700 hover:bg-blue-50 text-xs font-medium rounded-lg" @click="handleSyncType('classes')">
            {{ syncing && syncType === 'classes' ? '⏳...' : '🏫 Sync Kelas' }}
          </button>
          <button :disabled="syncing" class="px-3 py-2 border border-blue-300 text-blue-700 hover:bg-blue-50 text-xs font-medium rounded-lg" @click="handleSyncType('enrollments')">
            {{ syncing && syncType === 'enrollments' ? '⏳...' : '📝 Sync KRS' }}
          </button>
        </div>

        <!-- Sync Results -->
        <div v-if="syncResults.length" class="space-y-2 mt-4">
          <h3 class="text-xs font-semibold text-gray-600">Hasil Sinkronisasi:</h3>
          <div v-for="r in syncResults" :key="r.type" class="flex items-center gap-3 p-3 rounded-lg border border-gray-200">
            <span :class="['px-2 py-0.5 rounded-full text-[10px] font-semibold', statusColor[r.status]]">{{ r.status }}</span>
            <span class="text-sm font-medium text-gray-800">{{ typeLabel[r.type] ?? r.type }}</span>
            <span class="text-xs text-gray-500">{{ r.synced }}/{{ r.total }} berhasil</span>
            <span class="text-xs text-gray-400">({{ r.duration_ms }}ms)</span>
          </div>
        </div>
      </div>

      <!-- Logs -->
      <div class="bg-white rounded-xl border border-gray-200 p-6 space-y-4">
        <h2 class="text-sm font-semibold text-gray-800 uppercase tracking-wide">Riwayat Sinkronisasi</h2>
        <div v-if="!logs.length" class="text-center text-gray-400 text-sm py-4">Belum ada riwayat.</div>
        <table v-else class="w-full text-sm">
          <thead>
            <tr class="text-left text-xs text-gray-500 uppercase border-b">
              <th class="pb-2">Waktu</th><th class="pb-2">Tipe</th><th class="pb-2">Status</th><th class="pb-2">Data</th><th class="pb-2">Durasi</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="log in logs" :key="log.id" class="border-t border-gray-50">
              <td class="py-2 text-xs text-gray-500">{{ log.created_at }}</td>
              <td class="py-2">{{ typeLabel[log.sync_type] ?? log.sync_type }}</td>
              <td class="py-2"><span :class="['px-2 py-0.5 rounded-full text-[10px] font-semibold', statusColor[log.status]]">{{ log.status }}</span></td>
              <td class="py-2 text-xs text-gray-600">{{ log.synced_items }}/{{ log.total_items }}</td>
              <td class="py-2 text-xs text-gray-400">{{ log.duration_ms }}ms</td>
            </tr>
          </tbody>
        </table>
      </div>

    </template>
  </div>
</template>
