<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useToast } from 'vue-toastification'
import { useAuthStore } from '@/stores/auth'
import { PlusIcon, EyeIcon, XCircleIcon } from '@heroicons/vue/24/outline'
import DataTable from '@/components/ui/DataTable.vue'
import BaseModal from '@/components/ui/BaseModal.vue'
import api from '@/services/api'

const router = useRouter()
const toast = useToast()
const auth = useAuthStore()
const isMahasiswa = auth.user?.roles?.includes('MAHASISWA')

const items = ref<any[]>([])
const pagination = ref({ total: 0, currentPage: 1, lastPage: 1 })
const loading = ref(true)
const filterStatus = ref('')
const search = ref('')

// Create modal
const modalOpen = ref(false)
const saving = ref(false)
const semesters = ref<any[]>([])
const form = reactive({ semester_id: '', type: 'CUTI', reason: '', leave_semester_count: 1 })

// History info
const historyInfo = ref<any>(null)

const statuses = ['DIAJUKAN', 'DOSEN_WALI_APPROVED', 'KAPRODI_APPROVED', 'APPROVED', 'AKTIF', 'SELESAI', 'DIBATALKAN', 'DOSEN_WALI_REJECTED', 'KAPRODI_REJECTED', 'REJECTED']
const statusColor: Record<string, string> = {
  DRAFT: 'bg-gray-100 text-gray-600', DIAJUKAN: 'bg-blue-100 text-blue-700',
  DOSEN_WALI_APPROVED: 'bg-cyan-100 text-cyan-700', DOSEN_WALI_REJECTED: 'bg-red-100 text-red-600',
  KAPRODI_APPROVED: 'bg-indigo-100 text-indigo-700', KAPRODI_REJECTED: 'bg-red-100 text-red-600',
  APPROVED: 'bg-green-100 text-green-700', REJECTED: 'bg-red-100 text-red-600',
  AKTIF: 'bg-purple-100 text-purple-700', SELESAI: 'bg-green-100 text-green-700',
  DIBATALKAN: 'bg-gray-100 text-gray-500',
}
const statusLabel: Record<string, string> = {
  DIAJUKAN: 'Diajukan', DOSEN_WALI_APPROVED: 'PA Setuju', DOSEN_WALI_REJECTED: 'PA Tolak',
  KAPRODI_APPROVED: 'Kaprodi Setuju', KAPRODI_REJECTED: 'Kaprodi Tolak',
  APPROVED: 'Disetujui', REJECTED: 'Ditolak', AKTIF: 'Cuti Aktif',
  SELESAI: 'Selesai', DIBATALKAN: 'Dibatalkan',
}

const columns = [
  { key: 'student', label: 'Mahasiswa' },
  { key: 'semester', label: 'Semester' },
  { key: 'type', label: 'Jenis' },
  { key: 'reason', label: 'Alasan' },
  { key: 'status', label: 'Status' },
  { key: 'aksi', label: '', class: 'text-right' },
]

onMounted(async () => {
  load()
  try {
    const { data } = await api.get('/semesters', { params: { per_page: 50 } })
    semesters.value = data.data ?? data
  } catch {}
  // Load history info
  try {
    const { data } = await api.get('/academic-leaves/history')
    historyInfo.value = data
  } catch {}
})

async function load(page = 1) {
  loading.value = true
  try {
    const { data } = await api.get('/academic-leaves', { params: { status: filterStatus.value, search: search.value, page } })
    items.value = data.data
    pagination.value = { total: data.total, currentPage: data.current_page, lastPage: data.last_page }
  } finally { loading.value = false }
}

function openCreate() {
  Object.assign(form, { semester_id: '', type: 'CUTI', reason: '', leave_semester_count: 1 })
  modalOpen.value = true
}

async function handleSave() {
  saving.value = true
  try {
    await api.post('/academic-leaves', form)
    toast.success('Pengajuan cuti berhasil disubmit.')
    modalOpen.value = false; load()
    // Refresh history
    const { data } = await api.get('/academic-leaves/history')
    historyInfo.value = data
  } catch (e: any) { toast.error(e?.response?.data?.message ?? 'Gagal.') }
  finally { saving.value = false }
}

async function handleCancel(item: any) {
  if (!confirm('Batalkan pengajuan cuti ini?')) return
  try {
    await api.post(`/academic-leaves/${item.id}/cancel`)
    toast.success('Pengajuan dibatalkan.'); load()
  } catch (e: any) { toast.error(e?.response?.data?.message ?? 'Gagal.') }
}

function formatDate(d: string) { return d ? new Date(d).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' }) : '-' }
</script>

<template>
  <div class="space-y-5">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-xl font-bold text-gray-900">Cuti Akademik</h1>
        <p class="text-sm text-gray-500 mt-0.5">Pengajuan dan pengelolaan cuti akademik mahasiswa</p>
      </div>
      <button v-if="isMahasiswa" class="flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg" @click="openCreate">
        <PlusIcon class="w-4 h-4" /> Ajukan Cuti
      </button>
    </div>

    <!-- Quota info (mahasiswa) -->
    <div v-if="historyInfo && isMahasiswa" class="bg-white rounded-xl border border-gray-200 p-4 flex items-center gap-6">
      <div class="text-center">
        <p class="text-2xl font-bold text-blue-700">{{ historyInfo.total_semesters }}</p>
        <p class="text-xs text-gray-500">Sudah Cuti</p>
      </div>
      <div class="h-8 w-px bg-gray-200" />
      <div class="text-center">
        <p class="text-2xl font-bold text-green-600">{{ historyInfo.remaining }}</p>
        <p class="text-xs text-gray-500">Sisa Kuota</p>
      </div>
      <div class="h-8 w-px bg-gray-200" />
      <div class="text-center">
        <p class="text-2xl font-bold text-gray-600">{{ historyInfo.max_semesters }}</p>
        <p class="text-xs text-gray-500">Maks Semester</p>
      </div>
    </div>

    <div class="flex flex-wrap gap-3">
      <select v-model="filterStatus" class="px-3.5 py-2 rounded-lg border border-gray-300 text-sm" @change="load()">
        <option value="">Semua Status</option>
        <option v-for="s in statuses" :key="s" :value="s">{{ statusLabel[s] ?? s }}</option>
      </select>
      <input v-if="!isMahasiswa" v-model="search" type="text" placeholder="Cari NIM/nama..." class="px-3.5 py-2 rounded-lg border border-gray-300 text-sm w-52" @input="load()" />
    </div>

    <DataTable :columns="columns" :rows="items" :loading="loading" :total="pagination.total" :current-page="pagination.currentPage" :last-page="pagination.lastPage" @page-change="load">
      <template #default="{ row }">
        <td class="px-4 py-3">
          <p class="font-medium text-gray-900 text-sm">{{ row.student?.name }}</p>
          <p class="text-xs text-gray-500">{{ row.student?.nim }} · {{ row.student?.study_program?.code }}</p>
        </td>
        <td class="px-4 py-3 text-sm text-gray-600">{{ row.semester?.name ?? '-' }}</td>
        <td class="px-4 py-3">
          <span class="text-xs px-2 py-0.5 rounded bg-gray-100 text-gray-700 font-medium">{{ row.type }}</span>
          <span class="text-xs text-gray-400 ml-1">({{ row.leave_semester_count }} sem)</span>
        </td>
        <td class="px-4 py-3 text-sm text-gray-700 max-w-xs truncate">{{ row.reason }}</td>
        <td class="px-4 py-3">
          <span :class="['inline-flex px-2 py-0.5 rounded-full text-xs font-medium whitespace-nowrap', statusColor[row.status] ?? 'bg-gray-100']">{{ statusLabel[row.status] ?? row.status }}</span>
        </td>
        <td class="px-4 py-3">
          <div class="flex items-center justify-end gap-1">
            <button class="p-1.5 rounded-lg text-blue-600 hover:bg-blue-50" @click="router.push(`/akademik/cuti/${row.id}`)"><EyeIcon class="w-4 h-4" /></button>
            <button v-if="isMahasiswa && ['DRAFT','DIAJUKAN'].includes(row.status)" class="p-1.5 rounded-lg text-red-500 hover:bg-red-50" @click="handleCancel(row)"><XCircleIcon class="w-4 h-4" /></button>
          </div>
        </td>
      </template>
    </DataTable>
  </div>

  <BaseModal :open="modalOpen" title="Ajukan Cuti Akademik" size="xl" @close="modalOpen = false">
    <form class="space-y-4" @submit.prevent="handleSave">
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Semester <span class="text-red-500">*</span></label>
          <select v-model="form.semester_id" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
            <option value="">-- Pilih --</option>
            <option v-for="s in semesters" :key="s.id" :value="s.id">{{ s.name }}</option>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Jenis</label>
          <select v-model="form.type" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
            <option value="CUTI">Cuti Baru</option>
            <option value="PERPANJANGAN">Perpanjangan</option>
          </select>
        </div>
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Jumlah Semester Cuti</label>
        <select v-model.number="form.leave_semester_count" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
          <option :value="1">1 Semester</option>
          <option :value="2">2 Semester</option>
        </select>
        <p v-if="historyInfo" class="text-xs text-gray-400 mt-1">Sisa kuota: {{ historyInfo.remaining }} semester</p>
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Alasan Cuti <span class="text-red-500">*</span></label>
        <textarea v-model="form.reason" required rows="3" placeholder="Jelaskan alasan pengajuan cuti akademik..." class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" />
      </div>
      <p class="text-xs text-gray-400">Dokumen pendukung dapat diupload setelah pengajuan dibuat.</p>
    </form>
    <template #footer>
      <button class="px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded-lg" @click="modalOpen = false">Batal</button>
      <button :disabled="saving" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 disabled:bg-blue-400 text-white text-sm font-medium rounded-lg" @click="handleSave">{{ saving ? 'Mengirim...' : 'Ajukan Cuti' }}</button>
    </template>
  </BaseModal>
</template>
