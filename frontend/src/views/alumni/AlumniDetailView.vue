<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useToast } from 'vue-toastification'
import {
  ArrowLeftIcon, PlusIcon, TrashIcon, BriefcaseIcon,
  AcademicCapIcon, DocumentCheckIcon,
} from '@heroicons/vue/24/outline'
import BaseModal from '@/components/ui/BaseModal.vue'
import api from '@/services/api'

const route = useRoute()
const router = useRouter()
const toast = useToast()
const loading = ref(true)
const data = ref<any>(null)

// Employment modal
const empModal = ref(false)
const savingEmp = ref(false)
const empForm = reactive({
  company_name: '', position: '', industry: '', city: '',
  start_date: '', end_date: '', is_current: false, salary_range: '', description: '',
})

// Further Study modal
const fsModal = ref(false)
const savingFs = ref(false)
const fsForm = reactive({
  institution: '', program: '', degree: 'S2', entry_year: '', graduation_year: '', is_current: false,
})

// Tracer Study modal
const tsModal = ref(false)
const savingTs = ref(false)
const tsForm = reactive({
  period_id: '',
  employment_status: 'BEKERJA',
  months_to_first_job: '',
  first_job_relevance: '',
  first_salary: '',
  current_salary: '',
  competency_feedback: '',
  curriculum_feedback: '',
  suggestion: '',
  satisfaction_score: 4,
})

const empStatuses = [
  { value: 'BEKERJA', label: 'Bekerja' },
  { value: 'WIRAUSAHA', label: 'Wirausaha' },
  { value: 'MELANJUTKAN_STUDI', label: 'Melanjutkan Studi' },
  { value: 'BELUM_BEKERJA', label: 'Belum Bekerja' },
  { value: 'LAINNYA', label: 'Lainnya' },
]

onMounted(async () => {
  try {
    const { data: res } = await api.get(`/alumni/${route.params.id}`)
    data.value = res
  } finally { loading.value = false }
})

async function reload() {
  const { data: res } = await api.get(`/alumni/${route.params.id}`)
  data.value = res
}

// Employment
function openAddEmp() {
  Object.assign(empForm, { company_name: '', position: '', industry: '', city: '', start_date: '', end_date: '', is_current: false, salary_range: '', description: '' })
  empModal.value = true
}
async function saveEmp() {
  savingEmp.value = true
  try {
    await api.post(`/alumni/${data.value.id}/employments`, empForm)
    toast.success('Riwayat pekerjaan ditambahkan.')
    empModal.value = false; reload()
  } catch (e: any) { toast.error(e?.response?.data?.message ?? 'Gagal.') }
  finally { savingEmp.value = false }
}
async function deleteEmp(empId: number) {
  if (!confirm('Hapus riwayat pekerjaan ini?')) return
  await api.delete(`/alumni/${data.value.id}/employments/${empId}`)
  toast.success('Dihapus.'); reload()
}

// Further Study
function openAddFs() {
  Object.assign(fsForm, { institution: '', program: '', degree: 'S2', entry_year: '', graduation_year: '', is_current: false })
  fsModal.value = true
}
async function saveFs() {
  savingFs.value = true
  try {
    await api.post(`/alumni/${data.value.id}/further-studies`, fsForm)
    toast.success('Pendidikan lanjut ditambahkan.')
    fsModal.value = false; reload()
  } catch (e: any) { toast.error(e?.response?.data?.message ?? 'Gagal.') }
  finally { savingFs.value = false }
}
async function deleteFs(fsId: number) {
  if (!confirm('Hapus data pendidikan lanjut ini?')) return
  await api.delete(`/alumni/${data.value.id}/further-studies/${fsId}`)
  toast.success('Dihapus.'); reload()
}

// Tracer Study
function openAddTs() {
  Object.assign(tsForm, { period_id: '', employment_status: 'BEKERJA', months_to_first_job: '', first_job_relevance: '', first_salary: '', current_salary: '', competency_feedback: '', curriculum_feedback: '', suggestion: '', satisfaction_score: 4 })
  tsModal.value = true
}
async function saveTs() {
  savingTs.value = true
  try {
    await api.post(`/alumni/${data.value.id}/tracer-study`, tsForm)
    toast.success('Tracer study berhasil disubmit.')
    tsModal.value = false; reload()
  } catch (e: any) { toast.error(e?.response?.data?.message ?? 'Gagal.') }
  finally { savingTs.value = false }
}

function formatDate(d: string) { return d ? new Date(d).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' }) : '-' }
function formatCurrency(n: number) { return n ? new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(n) : '-' }
</script>

<template>
  <div v-if="loading" class="flex items-center justify-center h-64"><p class="text-gray-400">Memuat...</p></div>

  <div v-else-if="data" class="space-y-6 max-w-4xl mx-auto">
    <!-- Header -->
    <div class="flex items-start gap-3">
      <button class="p-2 rounded-lg hover:bg-gray-100" @click="router.back()"><ArrowLeftIcon class="w-5 h-5 text-gray-500" /></button>
      <div class="flex-1">
        <h1 class="text-xl font-bold text-gray-900">{{ data.name }}</h1>
        <p class="text-sm text-gray-500">{{ data.nim }} — {{ data.study_program?.name }}</p>
      </div>
      <span class="inline-flex px-3 py-1 rounded-full text-xs font-medium" :class="data.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'">
        {{ data.is_active ? 'Aktif' : 'Nonaktif' }}
      </span>
    </div>

    <!-- Info Dasar -->
    <div class="bg-white rounded-xl border border-gray-200 p-5">
      <h2 class="text-sm font-semibold text-gray-800 uppercase tracking-wide mb-3">Informasi Alumni</h2>
      <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
        <div><span class="text-gray-400 text-xs">NIM</span><p class="text-gray-800 font-mono">{{ data.nim }}</p></div>
        <div><span class="text-gray-400 text-xs">Program Studi</span><p class="text-gray-800">{{ data.study_program?.name ?? '-' }}</p></div>
        <div><span class="text-gray-400 text-xs">Angkatan</span><p class="text-gray-800">{{ data.entry_year }}</p></div>
        <div><span class="text-gray-400 text-xs">Tahun Lulus</span><p class="text-gray-800 font-bold">{{ data.graduation_year }}</p></div>
        <div><span class="text-gray-400 text-xs">IPK</span><p class="text-gray-800 font-bold">{{ data.gpa ? Number(data.gpa).toFixed(2) : '-' }}</p></div>
        <div><span class="text-gray-400 text-xs">Predikat</span><p class="text-gray-800">{{ data.predicate ?? '-' }}</p></div>
        <div><span class="text-gray-400 text-xs">Email</span><p class="text-gray-800">{{ data.email ?? '-' }}</p></div>
        <div><span class="text-gray-400 text-xs">Telepon</span><p class="text-gray-800">{{ data.phone ?? '-' }}</p></div>
      </div>
      <div v-if="data.thesis_title" class="col-span-2 md:col-span-4 pt-2 border-t border-gray-100">
        <span class="text-gray-400 text-xs">Judul Skripsi/Tesis</span>
        <p class="text-gray-800 text-sm mt-0.5">{{ data.thesis_title }}</p>
      </div>
      <div v-if="data.address" class="col-span-2 md:col-span-4">
        <span class="text-gray-400 text-xs">Alamat</span>
        <p class="text-gray-800 text-sm">{{ [data.address, data.city, data.province].filter(Boolean).join(', ') }}</p>
      </div>
    </div>

    <!-- Riwayat Pekerjaan -->
    <div class="bg-white rounded-xl border border-gray-200 p-5">
      <div class="flex items-center justify-between mb-4">
        <h2 class="text-sm font-semibold text-gray-800 uppercase tracking-wide flex items-center gap-2">
          <BriefcaseIcon class="w-4 h-4" /> Riwayat Pekerjaan
        </h2>
        <button class="flex items-center gap-1 px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-medium rounded-lg" @click="openAddEmp">
          <PlusIcon class="w-3.5 h-3.5" /> Tambah
        </button>
      </div>
      <div v-if="!data.employments?.length" class="text-center text-gray-400 py-6 text-sm">Belum ada data pekerjaan.</div>
      <div v-else class="space-y-3">
        <div v-for="emp in data.employments" :key="emp.id" class="flex items-start justify-between p-3 border border-gray-100 rounded-lg">
          <div>
            <div class="flex items-center gap-2">
              <p class="text-sm font-medium text-gray-900">{{ emp.position }}</p>
              <span v-if="emp.is_current" class="px-1.5 py-0.5 bg-green-100 text-green-700 text-xs rounded font-medium">Saat ini</span>
            </div>
            <p class="text-xs text-gray-600">{{ emp.company_name }}</p>
            <p class="text-xs text-gray-400 mt-0.5">
              {{ emp.industry ? emp.industry + ' · ' : '' }}{{ emp.city ?? '' }}
              {{ emp.start_date ? ' · ' + formatDate(emp.start_date) : '' }}{{ emp.end_date ? ' — ' + formatDate(emp.end_date) : emp.is_current ? ' — Sekarang' : '' }}
            </p>
          </div>
          <button class="p-1.5 rounded text-red-500 hover:bg-red-50 shrink-0" @click="deleteEmp(emp.id)"><TrashIcon class="w-4 h-4" /></button>
        </div>
      </div>
    </div>

    <!-- Pendidikan Lanjut -->
    <div class="bg-white rounded-xl border border-gray-200 p-5">
      <div class="flex items-center justify-between mb-4">
        <h2 class="text-sm font-semibold text-gray-800 uppercase tracking-wide flex items-center gap-2">
          <AcademicCapIcon class="w-4 h-4" /> Pendidikan Lanjut
        </h2>
        <button class="flex items-center gap-1 px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-medium rounded-lg" @click="openAddFs">
          <PlusIcon class="w-3.5 h-3.5" /> Tambah
        </button>
      </div>
      <div v-if="!data.further_studies?.length" class="text-center text-gray-400 py-6 text-sm">Belum ada data pendidikan lanjut.</div>
      <div v-else class="space-y-3">
        <div v-for="fs in data.further_studies" :key="fs.id" class="flex items-start justify-between p-3 border border-gray-100 rounded-lg">
          <div>
            <div class="flex items-center gap-2">
              <span class="px-1.5 py-0.5 bg-purple-100 text-purple-700 text-xs font-bold rounded">{{ fs.degree }}</span>
              <p class="text-sm font-medium text-gray-900">{{ fs.program }}</p>
              <span v-if="fs.is_current" class="px-1.5 py-0.5 bg-green-100 text-green-700 text-xs rounded font-medium">Aktif</span>
            </div>
            <p class="text-xs text-gray-600 mt-0.5">{{ fs.institution }}</p>
            <p class="text-xs text-gray-400">{{ fs.entry_year ?? '-' }}{{ fs.graduation_year ? ' — ' + fs.graduation_year : fs.is_current ? ' — Sekarang' : '' }}</p>
          </div>
          <button class="p-1.5 rounded text-red-500 hover:bg-red-50 shrink-0" @click="deleteFs(fs.id)"><TrashIcon class="w-4 h-4" /></button>
        </div>
      </div>
    </div>

    <!-- Tracer Study -->
    <div class="bg-white rounded-xl border border-gray-200 p-5">
      <div class="flex items-center justify-between mb-4">
        <h2 class="text-sm font-semibold text-gray-800 uppercase tracking-wide flex items-center gap-2">
          <DocumentCheckIcon class="w-4 h-4" /> Tracer Study
        </h2>
        <button class="flex items-center gap-1 px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-medium rounded-lg" @click="openAddTs">
          <PlusIcon class="w-3.5 h-3.5" /> Isi Tracer Study
        </button>
      </div>
      <div v-if="!data.tracer_studies?.length" class="text-center text-gray-400 py-6 text-sm">Belum ada data tracer study.</div>
      <div v-else class="space-y-3">
        <div v-for="ts in data.tracer_studies" :key="ts.id" class="p-3 border border-gray-100 rounded-lg">
          <div class="flex items-center justify-between mb-2">
            <span class="text-xs text-gray-500">Periode: {{ ts.period?.name ?? '-' }}</span>
            <span :class="['px-2 py-0.5 rounded-full text-xs font-medium', ts.is_completed ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700']">
              {{ ts.is_completed ? 'Selesai' : 'Draft' }}
            </span>
          </div>
          <div class="grid grid-cols-2 md:grid-cols-4 gap-3 text-xs">
            <div><span class="text-gray-400">Status Kerja</span><p class="text-gray-800 font-medium">{{ ts.employment_status?.replace(/_/g, ' ') ?? '-' }}</p></div>
            <div><span class="text-gray-400">Waktu Dapat Kerja</span><p class="text-gray-800">{{ ts.months_to_first_job ? ts.months_to_first_job + ' bulan' : '-' }}</p></div>
            <div><span class="text-gray-400">Gaji Pertama</span><p class="text-gray-800">{{ formatCurrency(ts.first_salary) }}</p></div>
            <div><span class="text-gray-400">Kepuasan</span><p class="text-gray-800">{{ ts.satisfaction_score ? '⭐'.repeat(ts.satisfaction_score) : '-' }}</p></div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Modal: Riwayat Pekerjaan -->
  <BaseModal :open="empModal" title="Tambah Riwayat Pekerjaan" size="xl" @close="empModal = false">
    <form class="space-y-4" @submit.prevent="saveEmp">
      <div class="grid grid-cols-2 gap-4">
        <div><label class="block text-sm font-medium text-gray-700 mb-1">Perusahaan <span class="text-red-500">*</span></label><input v-model="empForm.company_name" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" /></div>
        <div><label class="block text-sm font-medium text-gray-700 mb-1">Posisi/Jabatan <span class="text-red-500">*</span></label><input v-model="empForm.position" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" /></div>
      </div>
      <div class="grid grid-cols-2 gap-4">
        <div><label class="block text-sm font-medium text-gray-700 mb-1">Industri</label><input v-model="empForm.industry" placeholder="Teknologi, Keuangan, dll" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" /></div>
        <div><label class="block text-sm font-medium text-gray-700 mb-1">Kota</label><input v-model="empForm.city" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" /></div>
      </div>
      <div class="grid grid-cols-2 gap-4">
        <div><label class="block text-sm font-medium text-gray-700 mb-1">Tanggal Mulai</label><input v-model="empForm.start_date" type="date" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" /></div>
        <div><label class="block text-sm font-medium text-gray-700 mb-1">Tanggal Selesai</label><input v-model="empForm.end_date" type="date" :disabled="empForm.is_current" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm disabled:bg-gray-50" /></div>
      </div>
      <div class="flex items-center gap-4">
        <label class="flex items-center gap-2 text-sm text-gray-700"><input v-model="empForm.is_current" type="checkbox" class="rounded" /> Pekerjaan saat ini</label>
        <div class="flex-1"><label class="block text-sm font-medium text-gray-700 mb-1">Range Gaji (Rp)</label><input v-model.number="empForm.salary_range" type="number" placeholder="5000000" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" /></div>
      </div>
      <div><label class="block text-sm font-medium text-gray-700 mb-1">Deskripsi Pekerjaan</label><textarea v-model="empForm.description" rows="2" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" /></div>
    </form>
    <template #footer>
      <button class="px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded-lg" @click="empModal = false">Batal</button>
      <button :disabled="savingEmp" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 disabled:bg-blue-400 text-white text-sm font-medium rounded-lg" @click="saveEmp">{{ savingEmp ? 'Menyimpan...' : 'Simpan' }}</button>
    </template>
  </BaseModal>

  <!-- Modal: Pendidikan Lanjut -->
  <BaseModal :open="fsModal" title="Tambah Pendidikan Lanjut" @close="fsModal = false">
    <form class="space-y-4" @submit.prevent="saveFs">
      <div><label class="block text-sm font-medium text-gray-700 mb-1">Institusi <span class="text-red-500">*</span></label><input v-model="fsForm.institution" required placeholder="Universitas Gadjah Mada" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" /></div>
      <div><label class="block text-sm font-medium text-gray-700 mb-1">Program Studi <span class="text-red-500">*</span></label><input v-model="fsForm.program" required placeholder="Magister Hukum" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" /></div>
      <div class="grid grid-cols-3 gap-4">
        <div><label class="block text-sm font-medium text-gray-700 mb-1">Jenjang <span class="text-red-500">*</span></label><select v-model="fsForm.degree" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm"><option value="S2">S2</option><option value="S3">S3</option><option value="Profesi">Profesi</option><option value="Spesialis">Spesialis</option></select></div>
        <div><label class="block text-sm font-medium text-gray-700 mb-1">Tahun Masuk</label><input v-model.number="fsForm.entry_year" type="number" min="2000" max="2040" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" /></div>
        <div><label class="block text-sm font-medium text-gray-700 mb-1">Tahun Lulus</label><input v-model.number="fsForm.graduation_year" type="number" min="2000" max="2040" :disabled="fsForm.is_current" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm disabled:bg-gray-50" /></div>
      </div>
      <label class="flex items-center gap-2 text-sm text-gray-700"><input v-model="fsForm.is_current" type="checkbox" class="rounded" /> Masih aktif kuliah</label>
    </form>
    <template #footer>
      <button class="px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded-lg" @click="fsModal = false">Batal</button>
      <button :disabled="savingFs" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 disabled:bg-blue-400 text-white text-sm font-medium rounded-lg" @click="saveFs">{{ savingFs ? 'Menyimpan...' : 'Simpan' }}</button>
    </template>
  </BaseModal>

  <!-- Modal: Tracer Study -->
  <BaseModal :open="tsModal" title="Isi Tracer Study" size="xl" @close="tsModal = false">
    <form class="space-y-4" @submit.prevent="saveTs">
      <div class="grid grid-cols-2 gap-4">
        <div><label class="block text-sm font-medium text-gray-700 mb-1">Status Pekerjaan <span class="text-red-500">*</span></label>
          <select v-model="tsForm.employment_status" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
            <option v-for="s in empStatuses" :key="s.value" :value="s.value">{{ s.label }}</option>
          </select>
        </div>
        <div><label class="block text-sm font-medium text-gray-700 mb-1">Waktu Dapat Kerja Pertama (bulan)</label><input v-model.number="tsForm.months_to_first_job" type="number" min="0" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" /></div>
      </div>
      <div class="grid grid-cols-2 gap-4">
        <div><label class="block text-sm font-medium text-gray-700 mb-1">Relevansi Kerja Pertama</label>
          <select v-model="tsForm.first_job_relevance" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
            <option value="">-- Pilih --</option>
            <option value="Sangat Relevan">Sangat Relevan</option>
            <option value="Relevan">Relevan</option>
            <option value="Cukup Relevan">Cukup Relevan</option>
            <option value="Tidak Relevan">Tidak Relevan</option>
          </select>
        </div>
        <div><label class="block text-sm font-medium text-gray-700 mb-1">Kepuasan (1-5)</label>
          <div class="flex items-center gap-1 mt-1">
            <button v-for="i in 5" :key="i" type="button" class="text-2xl" :class="i <= tsForm.satisfaction_score ? 'text-yellow-400' : 'text-gray-300'" @click="tsForm.satisfaction_score = i">★</button>
          </div>
        </div>
      </div>
      <div class="grid grid-cols-2 gap-4">
        <div><label class="block text-sm font-medium text-gray-700 mb-1">Gaji Pertama (Rp)</label><input v-model.number="tsForm.first_salary" type="number" placeholder="3000000" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" /></div>
        <div><label class="block text-sm font-medium text-gray-700 mb-1">Gaji Saat Ini (Rp)</label><input v-model.number="tsForm.current_salary" type="number" placeholder="7000000" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" /></div>
      </div>
      <div><label class="block text-sm font-medium text-gray-700 mb-1">Feedback Kompetensi</label><textarea v-model="tsForm.competency_feedback" rows="2" placeholder="Kompetensi apa yang paling berguna?" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" /></div>
      <div><label class="block text-sm font-medium text-gray-700 mb-1">Feedback Kurikulum</label><textarea v-model="tsForm.curriculum_feedback" rows="2" placeholder="Saran untuk kurikulum?" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" /></div>
      <div><label class="block text-sm font-medium text-gray-700 mb-1">Saran Umum</label><textarea v-model="tsForm.suggestion" rows="2" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" /></div>
    </form>
    <template #footer>
      <button class="px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded-lg" @click="tsModal = false">Batal</button>
      <button :disabled="savingTs" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 disabled:bg-blue-400 text-white text-sm font-medium rounded-lg" @click="saveTs">{{ savingTs ? 'Menyimpan...' : 'Submit' }}</button>
    </template>
  </BaseModal>
</template>
