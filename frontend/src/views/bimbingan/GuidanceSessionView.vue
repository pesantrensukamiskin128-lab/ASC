<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useToast } from 'vue-toastification'
import { useAuthStore } from '@/stores/auth'
import { PlusIcon, EyeIcon, ChatBubbleLeftRightIcon } from '@heroicons/vue/24/outline'
import DataTable from '@/components/ui/DataTable.vue'
import BaseModal from '@/components/ui/BaseModal.vue'
import api from '@/services/api'

const router = useRouter()
const route = useRoute()
const toast = useToast()
const auth = useAuthStore()

const items = ref<any[]>([])
const pagination = ref({ total: 0, currentPage: 1, lastPage: 1 })
const loading = ref(true)
const filterStatus = ref((route.query.status as string) || '')
const isLecturer = auth.user?.roles?.includes('DOSEN') || auth.user?.roles?.includes('SUPER_ADMIN')

// Modal buat sesi
const modalOpen = ref(route.query.action === 'new')
const saving = ref(false)
const students = ref<any[]>([])

const form = reactive({
  student_id: '', topic: '', description: '', type: 'KONSULTASI',
  mode: 'TATAP_MUKA', scheduled_date: '', scheduled_time: '', location: '',
})

const types = ['KONSULTASI', 'PERWALIAN', 'PERINGATAN', 'BIMBINGAN_TA', 'LAINNYA']
const modes = [{ value: 'TATAP_MUKA', label: 'Tatap Muka' }, { value: 'ONLINE', label: 'Online' }, { value: 'CHAT', label: 'Chat' }]
const statuses = ['DIAJUKAN', 'DIJADWALKAN', 'BERLANGSUNG', 'SELESAI', 'DIBATALKAN']
const statusColor: Record<string, string> = {
  DIAJUKAN: 'bg-yellow-100 text-yellow-700', DIJADWALKAN: 'bg-blue-100 text-blue-700',
  BERLANGSUNG: 'bg-purple-100 text-purple-700', SELESAI: 'bg-green-100 text-green-700',
  DIBATALKAN: 'bg-gray-100 text-gray-500',
}

const columns = [
  { key: 'topic', label: 'Topik' },
  { key: 'student', label: isLecturer ? 'Mahasiswa' : 'Dosen Wali' },
  { key: 'type', label: 'Jenis' },
  { key: 'schedule', label: 'Jadwal' },
  { key: 'status', label: 'Status' },
  { key: 'aksi', label: '', class: 'text-right' },
]

onMounted(async () => {
  load()
  if (isLecturer) {
    const { data } = await api.get('/guidance/my-students')
    students.value = data
  }
})

async function load(page = 1) {
  loading.value = true
  try {
    const { data } = await api.get('/guidance/sessions', { params: { status: filterStatus.value, page } })
    items.value = data.data
    pagination.value = { total: data.total, currentPage: data.current_page, lastPage: data.last_page }
  } finally { loading.value = false }
}

function openCreate() {
  Object.assign(form, { student_id: '', topic: '', description: '', type: 'KONSULTASI', mode: 'TATAP_MUKA', scheduled_date: '', scheduled_time: '', location: '' })
  modalOpen.value = true
}

async function handleSave() {
  saving.value = true
  try {
    if (isLecturer) {
      await api.post('/guidance/sessions', form)
    } else {
      await api.post('/guidance/sessions/request', form)
    }
    toast.success('Sesi bimbingan berhasil dibuat.')
    modalOpen.value = false; load()
  } catch (e: any) { toast.error(e?.response?.data?.message ?? 'Gagal.') }
  finally { saving.value = false }
}

function formatDate(d: string) { return d ? new Date(d).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' }) : '-' }
</script>

<template>
  <div class="space-y-5">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-xl font-bold text-gray-900">Sesi Bimbingan</h1>
        <p class="text-sm text-gray-500 mt-0.5">Riwayat dan pengajuan sesi bimbingan akademik</p>
      </div>
      <button class="flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg" @click="openCreate">
        <PlusIcon class="w-4 h-4" /> {{ isLecturer ? 'Buat Sesi' : 'Ajukan Bimbingan' }}
      </button>
    </div>

    <div class="flex flex-wrap gap-3">
      <select v-model="filterStatus" class="px-3.5 py-2 rounded-lg border border-gray-300 text-sm" @change="load()">
        <option value="">Semua Status</option>
        <option v-for="s in statuses" :key="s" :value="s">{{ s.replace(/_/g, ' ') }}</option>
      </select>
    </div>

    <DataTable :columns="columns" :rows="items" :loading="loading" :total="pagination.total" :current-page="pagination.currentPage" :last-page="pagination.lastPage" @page-change="load">
      <template #default="{ row }">
        <td class="px-4 py-3">
          <p class="font-medium text-gray-900 text-sm">{{ row.topic }}</p>
          <p v-if="row.description" class="text-xs text-gray-500 truncate max-w-xs">{{ row.description }}</p>
        </td>
        <td class="px-4 py-3 text-sm text-gray-600">
          <template v-if="isLecturer">{{ row.student?.name }} <span class="text-xs text-gray-400">({{ row.student?.nim }})</span></template>
          <template v-else>{{ row.advisor?.full_name ?? row.advisor?.name }}</template>
        </td>
        <td class="px-4 py-3">
          <span class="text-xs px-2 py-0.5 bg-indigo-100 text-indigo-700 rounded font-medium">{{ row.type }}</span>
        </td>
        <td class="px-4 py-3 text-xs text-gray-500">
          <span v-if="row.scheduled_date">{{ formatDate(row.scheduled_date) }} {{ row.scheduled_time ?? '' }}</span>
          <span v-else class="text-gray-400">Belum dijadwalkan</span>
        </td>
        <td class="px-4 py-3">
          <span :class="['inline-flex px-2 py-0.5 rounded-full text-xs font-medium', statusColor[row.status] ?? '']">{{ row.status.replace(/_/g, ' ') }}</span>
        </td>
        <td class="px-4 py-3">
          <button class="p-1.5 rounded-lg text-blue-600 hover:bg-blue-50" @click="router.push(`/bimbingan/sessions/${row.id}`)">
            <EyeIcon class="w-4 h-4" />
          </button>
        </td>
      </template>
    </DataTable>
  </div>

  <BaseModal :open="modalOpen" :title="isLecturer ? 'Buat Sesi Bimbingan' : 'Ajukan Bimbingan'" size="xl" @close="modalOpen = false">
    <form class="space-y-4" @submit.prevent="handleSave">
      <div v-if="isLecturer">
        <label class="block text-sm font-medium text-gray-700 mb-1">Mahasiswa <span class="text-red-500">*</span></label>
        <select v-model="form.student_id" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
          <option value="">-- Pilih Mahasiswa --</option>
          <option v-for="s in students" :key="s.id" :value="s.id">{{ s.nim }} - {{ s.name }}</option>
        </select>
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Topik <span class="text-red-500">*</span></label>
        <input v-model="form.topic" required placeholder="Konsultasi KRS / Masalah akademik..." class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" />
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Deskripsi</label>
        <textarea v-model="form.description" rows="2" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" />
      </div>
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Jenis</label>
          <select v-model="form.type" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
            <option v-for="t in types" :key="t" :value="t">{{ t.replace(/_/g, ' ') }}</option>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Mode</label>
          <select v-model="form.mode" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
            <option v-for="m in modes" :key="m.value" :value="m.value">{{ m.label }}</option>
          </select>
        </div>
      </div>
      <div class="grid grid-cols-3 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Tanggal</label>
          <input v-model="form.scheduled_date" type="date" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Jam</label>
          <input v-model="form.scheduled_time" type="time" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Lokasi</label>
          <input v-model="form.location" placeholder="Ruang 101" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" />
        </div>
      </div>
    </form>
    <template #footer>
      <button class="px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded-lg" @click="modalOpen = false">Batal</button>
      <button :disabled="saving" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 disabled:bg-blue-400 text-white text-sm font-medium rounded-lg" @click="handleSave">{{ saving ? 'Menyimpan...' : 'Simpan' }}</button>
    </template>
  </BaseModal>
</template>
