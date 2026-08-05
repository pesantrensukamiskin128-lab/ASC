<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { useToast } from 'vue-toastification'
import { useAuthStore } from '@/stores/auth'
import { PlusIcon, CheckCircleIcon, ExclamationTriangleIcon } from '@heroicons/vue/24/outline'
import DataTable from '@/components/ui/DataTable.vue'
import BaseModal from '@/components/ui/BaseModal.vue'
import api from '@/services/api'

const toast = useToast()
const auth = useAuthStore()
const isLecturer = auth.user?.roles?.includes('DOSEN') || auth.user?.roles?.includes('SUPER_ADMIN')

const items = ref<any[]>([])
const pagination = ref({ total: 0, currentPage: 1, lastPage: 1 })
const loading = ref(true)
const filterStatus = ref('')
const filterLevel = ref('')
const students = ref<any[]>([])

// Create modal
const modalOpen = ref(false)
const saving = ref(false)
const form = reactive({ student_id: '', level: 'RINGAN', reason: '', description: '', ipk: '', ips: '', requires_consultation: true, consultation_deadline: '' })

// Resolve modal
const resolveModal = ref(false)
const resolvingId = ref<number | null>(null)
const resolveNote = ref('')
const resolving = ref(false)

const levels = ['RINGAN', 'SEDANG', 'BERAT']
const levelColor: Record<string, string> = { RINGAN: 'bg-yellow-100 text-yellow-700', SEDANG: 'bg-orange-100 text-orange-700', BERAT: 'bg-red-100 text-red-700' }
const statusColor: Record<string, string> = { AKTIF: 'bg-red-100 text-red-700', PROSES: 'bg-yellow-100 text-yellow-700', SELESAI: 'bg-green-100 text-green-700' }

const columns = [
  { key: 'student', label: 'Mahasiswa' },
  { key: 'level', label: 'Level' },
  { key: 'reason', label: 'Alasan' },
  { key: 'ipk', label: 'IPK/IPS' },
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
    const { data } = await api.get('/guidance/warnings', { params: { status: filterStatus.value, level: filterLevel.value, page } })
    items.value = data.data
    pagination.value = { total: data.total, currentPage: data.current_page, lastPage: data.last_page }
  } finally { loading.value = false }
}

function openCreate() {
  Object.assign(form, { student_id: '', level: 'RINGAN', reason: '', description: '', ipk: '', ips: '', requires_consultation: true, consultation_deadline: '' })
  modalOpen.value = true
}

async function handleSave() {
  saving.value = true
  try {
    await api.post('/guidance/warnings', { ...form, ipk: form.ipk || null, ips: form.ips || null, consultation_deadline: form.consultation_deadline || null })
    toast.success('Peringatan akademik berhasil dibuat.')
    modalOpen.value = false; load()
  } catch (e: any) { toast.error(e?.response?.data?.message ?? 'Gagal.') }
  finally { saving.value = false }
}

function openResolve(item: any) {
  resolvingId.value = item.id
  resolveNote.value = ''
  resolveModal.value = true
}

async function handleResolve() {
  if (!resolveNote.value.trim()) { toast.error('Isi catatan resolusi.'); return }
  resolving.value = true
  try {
    await api.post(`/guidance/warnings/${resolvingId.value}/resolve`, { resolution_note: resolveNote.value })
    toast.success('Peringatan berhasil diselesaikan.')
    resolveModal.value = false; load()
  } catch (e: any) { toast.error(e?.response?.data?.message ?? 'Gagal.') }
  finally { resolving.value = false }
}
</script>

<template>
  <div class="space-y-5">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-xl font-bold text-gray-900">Peringatan Akademik</h1>
        <p class="text-sm text-gray-500 mt-0.5">Peringatan untuk mahasiswa dengan masalah akademik (IPK rendah, dll)</p>
      </div>
      <button v-if="isLecturer" class="flex items-center gap-2 px-4 py-2 bg-red-600 hover:bg-red-700 text-white text-sm font-medium rounded-lg" @click="openCreate">
        <ExclamationTriangleIcon class="w-4 h-4" /> Buat Peringatan
      </button>
    </div>

    <div class="flex flex-wrap gap-3">
      <select v-model="filterStatus" class="px-3.5 py-2 rounded-lg border border-gray-300 text-sm" @change="load()">
        <option value="">Semua Status</option>
        <option value="AKTIF">Aktif</option>
        <option value="PROSES">Proses</option>
        <option value="SELESAI">Selesai</option>
      </select>
      <select v-model="filterLevel" class="px-3.5 py-2 rounded-lg border border-gray-300 text-sm" @change="load()">
        <option value="">Semua Level</option>
        <option v-for="l in levels" :key="l" :value="l">{{ l }}</option>
      </select>
    </div>

    <DataTable :columns="columns" :rows="items" :loading="loading" :total="pagination.total" :current-page="pagination.currentPage" :last-page="pagination.lastPage" @page-change="load">
      <template #default="{ row }">
        <td class="px-4 py-3">
          <p class="font-medium text-gray-900 text-sm">{{ row.student?.name }}</p>
          <p class="text-xs text-gray-500">{{ row.student?.nim }} · {{ row.student?.study_program?.code }}</p>
        </td>
        <td class="px-4 py-3">
          <span :class="['inline-flex px-2 py-0.5 rounded-full text-xs font-bold', levelColor[row.level] ?? '']">{{ row.level }}</span>
        </td>
        <td class="px-4 py-3 text-sm text-gray-700 max-w-xs truncate">{{ row.reason }}</td>
        <td class="px-4 py-3 text-xs text-gray-600">
          <span v-if="row.ipk">IPK {{ row.ipk }}</span>
          <span v-if="row.ips" class="ml-2">IPS {{ row.ips }}</span>
          <span v-if="!row.ipk && !row.ips" class="text-gray-400">-</span>
        </td>
        <td class="px-4 py-3">
          <span :class="['inline-flex px-2 py-0.5 rounded-full text-xs font-medium', statusColor[row.status] ?? '']">{{ row.status }}</span>
        </td>
        <td class="px-4 py-3">
          <button v-if="isLecturer && row.status !== 'SELESAI'" class="p-1.5 rounded-lg text-green-600 hover:bg-green-50" title="Selesaikan" @click="openResolve(row)">
            <CheckCircleIcon class="w-4 h-4" />
          </button>
        </td>
      </template>
    </DataTable>
  </div>

  <!-- Modal Create -->
  <BaseModal :open="modalOpen" title="Buat Peringatan Akademik" size="xl" @close="modalOpen = false">
    <form class="space-y-4" @submit.prevent="handleSave">
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Mahasiswa <span class="text-red-500">*</span></label>
          <select v-model="form.student_id" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
            <option value="">-- Pilih --</option>
            <option v-for="s in students" :key="s.id" :value="s.id">{{ s.nim }} - {{ s.name }}</option>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Level <span class="text-red-500">*</span></label>
          <select v-model="form.level" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
            <option v-for="l in levels" :key="l" :value="l">{{ l }}</option>
          </select>
        </div>
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Alasan <span class="text-red-500">*</span></label>
        <input v-model="form.reason" required placeholder="IPK di bawah 2.00 / Ketidakhadiran berlebih..." class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" />
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Deskripsi</label>
        <textarea v-model="form.description" rows="2" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" />
      </div>
      <div class="grid grid-cols-3 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">IPK</label>
          <input v-model="form.ipk" type="number" step="0.01" min="0" max="4" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">IPS</label>
          <input v-model="form.ips" type="number" step="0.01" min="0" max="4" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Deadline Konsultasi</label>
          <input v-model="form.consultation_deadline" type="date" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" />
        </div>
      </div>
      <label class="flex items-center gap-2 text-sm text-gray-700">
        <input v-model="form.requires_consultation" type="checkbox" class="rounded" /> Wajib konsultasi
      </label>
    </form>
    <template #footer>
      <button class="px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded-lg" @click="modalOpen = false">Batal</button>
      <button :disabled="saving" class="px-4 py-2 bg-red-600 hover:bg-red-700 disabled:bg-red-400 text-white text-sm font-medium rounded-lg" @click="handleSave">{{ saving ? 'Menyimpan...' : 'Buat Peringatan' }}</button>
    </template>
  </BaseModal>

  <!-- Modal Resolve -->
  <BaseModal :open="resolveModal" title="Selesaikan Peringatan" @close="resolveModal = false">
    <form class="space-y-4" @submit.prevent="handleResolve">
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Catatan Resolusi <span class="text-red-500">*</span></label>
        <textarea v-model="resolveNote" required rows="3" placeholder="Jelaskan bagaimana peringatan ini diselesaikan..." class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" />
      </div>
    </form>
    <template #footer>
      <button class="px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded-lg" @click="resolveModal = false">Batal</button>
      <button :disabled="resolving" class="px-4 py-2 bg-green-600 hover:bg-green-700 disabled:bg-green-400 text-white text-sm font-medium rounded-lg" @click="handleResolve">{{ resolving ? 'Memproses...' : 'Selesaikan' }}</button>
    </template>
  </BaseModal>
</template>
