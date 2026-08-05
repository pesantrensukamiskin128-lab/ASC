<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useToast } from 'vue-toastification'
import { PlusIcon, EyeIcon, DocumentTextIcon, ArrowsRightLeftIcon } from '@heroicons/vue/24/outline'
import DataTable from '@/components/ui/DataTable.vue'
import BaseModal from '@/components/ui/BaseModal.vue'
import api from '@/services/api'

const router = useRouter()
const toast = useToast()

const items = ref<any[]>([])
const pagination = ref({ total: 0, currentPage: 1, lastPage: 1 })
const loading = ref(true)
const filterStatus = ref('')
const search = ref('')
const dashboard = ref<any>(null)

// Create modal
const modalOpen = ref(false)
const saving = ref(false)
const students = ref<any[]>([])
const institutions = ref<any[]>([])
const searchStudent = ref('')
const studentResults = ref<any[]>([])
const selectedStudent = ref<any>(null)

const form = reactive({
  student_id: '' as any, transfer_type: 'EXTERNAL',
  source_institution_name: '', source_institution_id: '',
  source_study_program: '', source_degree: 'S1',
  source_student_number: '', source_total_credits: '',
  source_gpa: '', source_semesters: '',
})

const statuses = ['DRAFT', 'SUBMITTED', 'DOCUMENT_VERIFICATION', 'ACADEMIC_EVALUATION', 'APPROVED', 'REJECTED', 'FINALIZED']
const statusColor: Record<string, string> = {
  DRAFT: 'bg-gray-100 text-gray-600', SUBMITTED: 'bg-blue-100 text-blue-700',
  DOCUMENT_VERIFICATION: 'bg-yellow-100 text-yellow-700', ACADEMIC_EVALUATION: 'bg-purple-100 text-purple-700',
  APPROVED: 'bg-green-100 text-green-700', REJECTED: 'bg-red-100 text-red-600', FINALIZED: 'bg-emerald-100 text-emerald-700',
}

const columns = [
  { key: 'student', label: 'Mahasiswa' }, { key: 'source', label: 'PT Asal' },
  { key: 'type', label: 'Jenis' }, { key: 'credits', label: 'SKS Asal' },
  { key: 'gpa', label: 'IPK' }, { key: 'status', label: 'Status' },
  { key: 'aksi', label: '', class: 'text-right' },
]

onMounted(async () => {
  load()
  try {
    const [dRes, iRes] = await Promise.all([
      api.get('/transfer-credits/dashboard'),
      api.get('/transfer-credits-institutions'),
    ])
    dashboard.value = dRes.data
    institutions.value = iRes.data
  } catch {}
})

async function load(page = 1) {
  loading.value = true
  try {
    const { data } = await api.get('/transfer-credits', { params: { status: filterStatus.value, search: search.value, page } })
    items.value = data.data
    pagination.value = { total: data.total, currentPage: data.current_page, lastPage: data.last_page }
  } finally { loading.value = false }
}

let searchTimeout: any = null
function onSearchStudent() {
  clearTimeout(searchTimeout)
  if (searchStudent.value.length < 2) { studentResults.value = []; return }
  searchTimeout = setTimeout(async () => {
    const { data } = await api.get('/students', { params: { search: searchStudent.value, per_page: 10 } })
    studentResults.value = data.data ?? data
  }, 300)
}
function selectStudent(s: any) {
  selectedStudent.value = s; form.student_id = s.id
  searchStudent.value = `${s.nim} - ${s.name}`; studentResults.value = []
}

function openCreate() {
  Object.assign(form, { student_id: '', transfer_type: 'EXTERNAL', source_institution_name: '', source_institution_id: '', source_study_program: '', source_degree: 'S1', source_student_number: '', source_total_credits: '', source_gpa: '', source_semesters: '' })
  selectedStudent.value = null; searchStudent.value = ''
  modalOpen.value = true
}

async function handleSave() {
  if (!form.student_id) { toast.error('Pilih mahasiswa.'); return }
  saving.value = true
  try {
    const { data } = await api.post('/transfer-credits', form)
    toast.success('Aplikasi transfer berhasil dibuat.')
    modalOpen.value = false
    router.push(`/akademik/transfer/${data.data.id}`)
  } catch (e: any) { toast.error(e?.response?.data?.message ?? 'Gagal.') }
  finally { saving.value = false }
}
</script>

<template>
  <div class="space-y-5">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-xl font-bold text-gray-900">Transfer Nilai</h1>
        <p class="text-sm text-gray-500 mt-0.5">Konversi nilai mahasiswa pindahan / transfer SKS</p>
      </div>
      <button class="flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg" @click="openCreate">
        <PlusIcon class="w-4 h-4" /> Buat Aplikasi
      </button>
    </div>

    <!-- Dashboard cards -->
    <div v-if="dashboard" class="grid grid-cols-2 md:grid-cols-4 gap-3">
      <div class="rounded-lg border border-blue-200 bg-blue-50 p-3 text-center">
        <p class="text-xl font-bold text-blue-700">{{ dashboard.submitted + dashboard.document_verification + dashboard.academic_evaluation }}</p>
        <p class="text-xs text-blue-600">Dalam Proses</p>
      </div>
      <div class="rounded-lg border border-green-200 bg-green-50 p-3 text-center">
        <p class="text-xl font-bold text-green-700">{{ dashboard.approved }}</p>
        <p class="text-xs text-green-600">Disetujui</p>
      </div>
      <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-center">
        <p class="text-xl font-bold text-emerald-700">{{ dashboard.finalized }}</p>
        <p class="text-xs text-emerald-600">Finalisasi</p>
      </div>
      <div class="rounded-lg border border-gray-200 bg-gray-50 p-3 text-center">
        <p class="text-xl font-bold text-gray-700">{{ dashboard.total }}</p>
        <p class="text-xs text-gray-600">Total</p>
      </div>
    </div>

    <div class="flex flex-wrap gap-3">
      <select v-model="filterStatus" class="px-3.5 py-2 rounded-lg border border-gray-300 text-sm" @change="load()">
        <option value="">Semua Status</option>
        <option v-for="s in statuses" :key="s" :value="s">{{ s.replace(/_/g, ' ') }}</option>
      </select>
      <input v-model="search" type="text" placeholder="Cari NIM/nama..." class="px-3.5 py-2 rounded-lg border border-gray-300 text-sm w-52" @input="load()" />
    </div>

    <DataTable :columns="columns" :rows="items" :loading="loading" :total="pagination.total" :current-page="pagination.currentPage" :last-page="pagination.lastPage" @page-change="load">
      <template #default="{ row }">
        <td class="px-4 py-3"><p class="font-medium text-gray-900 text-sm">{{ row.student?.name }}</p><p class="text-xs text-gray-500">{{ row.student?.nim }}</p></td>
        <td class="px-4 py-3 text-sm text-gray-600">{{ row.source_institution?.name ?? '-' }}</td>
        <td class="px-4 py-3"><span class="text-xs px-2 py-0.5 bg-indigo-100 text-indigo-700 rounded font-medium">{{ row.transfer_type }}</span></td>
        <td class="px-4 py-3 text-sm text-gray-700 font-medium">{{ row.source_total_credits ?? '-' }}</td>
        <td class="px-4 py-3 text-sm text-gray-700">{{ row.source_gpa ?? '-' }}</td>
        <td class="px-4 py-3"><span :class="['inline-flex px-2 py-0.5 rounded-full text-xs font-medium whitespace-nowrap', statusColor[row.status]]">{{ row.status.replace(/_/g, ' ') }}</span></td>
        <td class="px-4 py-3"><button class="p-1.5 rounded-lg text-blue-600 hover:bg-blue-50" @click="router.push(`/akademik/transfer/${row.id}`)"><EyeIcon class="w-4 h-4" /></button></td>
      </template>
    </DataTable>
  </div>

  <BaseModal :open="modalOpen" title="Buat Aplikasi Transfer Nilai" size="xl" @close="modalOpen = false">
    <form class="space-y-4" @submit.prevent="handleSave">
      <div class="relative">
        <label class="block text-sm font-medium text-gray-700 mb-1">Mahasiswa <span class="text-red-500">*</span></label>
        <input v-model="searchStudent" placeholder="Ketik NIM/nama..." class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" @input="onSearchStudent" />
        <div v-if="studentResults.length" class="absolute z-10 w-full mt-1 bg-white border rounded-lg shadow-lg max-h-40 overflow-y-auto">
          <button v-for="s in studentResults" :key="s.id" type="button" class="w-full text-left px-3 py-2 hover:bg-blue-50 text-sm" @click="selectStudent(s)">
            <span class="font-mono text-xs text-gray-500">{{ s.nim }}</span> — {{ s.name }}
          </button>
        </div>
      </div>
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Jenis Transfer</label>
          <select v-model="form.transfer_type" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
            <option value="EXTERNAL">Pindahan Eksternal</option>
            <option value="INTERNAL">Pindah Prodi Internal</option>
            <option value="RPL">Rekognisi Pembelajaran Lampau</option>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Jenjang Asal</label>
          <select v-model="form.source_degree" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
            <option value="D3">D3</option><option value="S1">S1</option><option value="S2">S2</option>
          </select>
        </div>
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Perguruan Tinggi Asal</label>
        <select v-if="institutions.length" v-model="form.source_institution_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
          <option value="">-- Pilih atau ketik baru di bawah --</option>
          <option v-for="i in institutions" :key="i.id" :value="i.id">{{ i.name }} {{ i.accreditation ? `(${i.accreditation})` : '' }}</option>
        </select>
        <input v-model="form.source_institution_name" placeholder="Atau ketik nama PT baru..." class="w-full mt-2 px-3 py-2 border border-gray-300 rounded-lg text-sm" />
      </div>
      <div class="grid grid-cols-2 gap-4">
        <div><label class="block text-sm font-medium text-gray-700 mb-1">Program Studi Asal</label><input v-model="form.source_study_program" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" /></div>
        <div><label class="block text-sm font-medium text-gray-700 mb-1">NIM Asal</label><input v-model="form.source_student_number" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" /></div>
      </div>
      <div class="grid grid-cols-3 gap-4">
        <div><label class="block text-sm font-medium text-gray-700 mb-1">Total SKS</label><input v-model.number="form.source_total_credits" type="number" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" /></div>
        <div><label class="block text-sm font-medium text-gray-700 mb-1">IPK</label><input v-model="form.source_gpa" type="number" step="0.01" min="0" max="4" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" /></div>
        <div><label class="block text-sm font-medium text-gray-700 mb-1">Semester</label><input v-model.number="form.source_semesters" type="number" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" /></div>
      </div>
    </form>
    <template #footer>
      <button class="px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded-lg" @click="modalOpen = false">Batal</button>
      <button :disabled="saving" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 disabled:bg-blue-400 text-white text-sm font-medium rounded-lg" @click="handleSave">{{ saving ? 'Menyimpan...' : 'Buat Aplikasi' }}</button>
    </template>
  </BaseModal>
</template>
