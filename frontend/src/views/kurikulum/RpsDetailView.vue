<script setup lang="ts">
import { ref, reactive, onMounted, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ArrowLeftIcon, CheckIcon, XMarkIcon, LockClosedIcon, PlusIcon, TrashIcon, PencilIcon } from '@heroicons/vue/24/outline'
import { useToast } from 'vue-toastification'
import { useAuthStore } from '@/stores/auth'
import BaseModal from '@/components/ui/BaseModal.vue'
import api from '@/services/api'

const route  = useRoute()
const router = useRouter()
const toast  = useToast()
const auth   = useAuthStore()

const rpkps   = ref<any>(null)
const loading = ref(true)
const saving  = ref(false)
const activeTab = ref('cpl')

const rejectModalOpen = ref(false)
const rejectionNote   = ref('')
const downloadingPdf  = ref(false)

async function downloadPdf() {
  downloadingPdf.value = true
  try {
    const res = await api.get(`/rpkps/${rpkps.value.id}/pdf`, { responseType: 'blob' })
    const url = URL.createObjectURL(new Blob([res.data], { type: 'application/pdf' }))
    const link = document.createElement('a')
    link.href = url
    link.download = `RPKPS-${rpkps.value.course?.code ?? ''}-${rpkps.value.code}.pdf`
    document.body.appendChild(link); link.click(); link.remove()
    URL.revokeObjectURL(url)
  } catch { toast.error('Gagal download PDF.') }
  finally { downloadingPdf.value = false }
}

// Dropdown data
const availableCpls = ref<any[]>([])
const curriculumCourses = ref<any[]>([])  // MK dalam kurikulum untuk dropdown prasyarat
const tabs = [
  { key: 'cpl', label: 'CPL' },
  { key: 'cpmk', label: 'CPMK' },
  { key: 'deskripsi', label: 'Deskripsi & Bahan Kajian' },
  { key: 'mingguan', label: 'Rencana Mingguan' },
  { key: 'asesmen', label: 'Asesmen' },
  { key: 'referensi', label: 'Referensi' },
  { key: 'riwayat', label: 'Riwayat' },
]

const statusColor: Record<string, string> = {
  DRAFT: 'bg-gray-100 text-gray-600', DIAJUKAN: 'bg-yellow-100 text-yellow-700',
  DALAM_PEMERIKSAAN: 'bg-blue-100 text-blue-700', REVISI: 'bg-orange-100 text-orange-700',
  DISETUJUI: 'bg-green-100 text-green-700', DIKUNCI: 'bg-indigo-100 text-indigo-700',
}

const isEditable = computed(() => ['DRAFT', 'REVISI'].includes(rpkps.value?.status))

/** CPL yang terpetakan ke mata kuliah ini dari kurikulum (via cpl_course_mappings) */
const mappedCpls = computed(() => {
  if (!rpkps.value?.course_id || !availableCpls.value.length) return []
  // Jika backend sudah return cpls di rpkps, gunakan itu
  // Kalau tidak, filter dari available CPLs yang punya mapping ke course ini
  if (rpkps.value.cpls?.length) return rpkps.value.cpls
  return []
})

onMounted(load)

async function load() {
  loading.value = true
  try {
    const { data } = await api.get(`/rpkps/${route.params.id}`)
    rpkps.value = data
    // Load CPL dan MK dari kurikulum
    if (data.curriculum_id) {
      const { data: cur } = await api.get(`/curriculums/${data.curriculum_id}`)
      availableCpls.value = cur.learning_outcomes ?? []
      curriculumCourses.value = (cur.curriculum_courses ?? []).map((cc: any) => cc.course).filter(Boolean)
    }
  } catch { toast.error('Gagal memuat RPKPS.') }
  finally { loading.value = false }
}

// === SAVE FUNCTIONS ===

async function saveDeskripsi() {
  saving.value = true
  try {
    await api.put(`/rpkps/${rpkps.value.id}`, {
      course_description: rpkps.value.course_description,
      course_scope: rpkps.value.course_scope,
      prerequisites: rpkps.value.prerequisites,
    })
    toast.success('Deskripsi berhasil disimpan.')
  } catch { toast.error('Gagal menyimpan.') }
  finally { saving.value = false }
}

async function syncCpls() {
  const ids = rpkps.value.cpls?.map((c: any) => c.id) ?? []
  saving.value = true
  try {
    await api.post(`/rpkps/${rpkps.value.id}/cpls`, { cpl_ids: ids })
    toast.success('CPL berhasil disimpan.')
  } catch { toast.error('Gagal menyimpan CPL.') }
  finally { saving.value = false }
}

function toggleCpl(cpl: any) {
  const idx = rpkps.value.cpls.findIndex((c: any) => c.id === cpl.id)
  if (idx >= 0) rpkps.value.cpls.splice(idx, 1)
  else rpkps.value.cpls.push(cpl)
}

function isCplSelected(id: number) {
  return rpkps.value?.cpls?.some((c: any) => c.id === id) ?? false
}

// CPMK
const cpmkForm = reactive({ code: '', description: '' })
const cpmkModalOpen = ref(false)
const editingCpmkId = ref<number | null>(null)

function openAddCpmk() {
  editingCpmkId.value = null
  Object.assign(cpmkForm, { code: '', description: '' })
  cpmkModalOpen.value = true
}

function openEditCpmk(cpmk: any) {
  editingCpmkId.value = cpmk.id
  Object.assign(cpmkForm, { code: cpmk.code, description: cpmk.description })
  cpmkModalOpen.value = true
}

async function saveCpmk() {
  saving.value = true
  try {
    if (editingCpmkId.value) {
      await api.put(`/rpkps/${rpkps.value.id}/cpmks/${editingCpmkId.value}`, cpmkForm)
      toast.success('CPMK berhasil diupdate.')
    } else {
      await api.post(`/rpkps/${rpkps.value.id}/cpmks`, cpmkForm)
      toast.success('CPMK berhasil ditambahkan.')
    }
    cpmkModalOpen.value = false
    Object.assign(cpmkForm, { code: '', description: '' })
    editingCpmkId.value = null
    load()
  } catch (e: any) { toast.error(e?.response?.data?.message ?? 'Gagal.') }
  finally { saving.value = false }
}

async function deleteCpmk(id: number) {
  if (!confirm('Hapus CPMK ini?')) return
  await api.delete(`/rpkps/${rpkps.value.id}/cpmks/${id}`)
  toast.success('CPMK dihapus.')
  load()
}

// Weekly Plans
function initWeeklyPlans() {
  if (!rpkps.value.weekly_plans?.length) {
    rpkps.value.weekly_plans = Array.from({ length: 16 }, (_, i) => ({
      week_number: i + 1, sub_cpmk: '', learning_material: '',
      methods: [], student_activity: '', assessment_form: '', weight: 0,
    }))
  }
}

async function saveWeeklyPlans() {
  saving.value = true
  try {
    // Convert methods dari string ke array (pisah dengan koma)
    const plans = rpkps.value.weekly_plans.map((w: any) => ({
      ...w,
      methods: typeof w.methods === 'string'
        ? w.methods.split(',').map((m: string) => m.trim()).filter(Boolean)
        : (w.methods ?? []),
    }))
    await api.post(`/rpkps/${rpkps.value.id}/weekly-plans`, { plans })
    toast.success('Rencana mingguan berhasil disimpan.')
  } catch (e: any) { toast.error(e?.response?.data?.message ?? 'Gagal.') }
  finally { saving.value = false }
}

// Assessments
function initAssessments() {
  if (!rpkps.value.assessments?.length) {
    rpkps.value.assessments = [
      { name: 'Kehadiran & Partisipasi', weight: 10, description: '' },
      { name: 'Tugas', weight: 20, description: '' },
      { name: 'UTS', weight: 30, description: '' },
      { name: 'UAS', weight: 40, description: '' },
    ]
  }
}

function addAssessment() {
  rpkps.value.assessments.push({ name: '', weight: 0, description: '' })
}

function removeAssessment(idx: number) {
  rpkps.value.assessments.splice(idx, 1)
}

const totalWeight = computed(() =>
  (rpkps.value?.assessments ?? []).reduce((s: number, a: any) => s + (Number(a.weight) || 0), 0)
)

async function saveAssessments() {
  saving.value = true
  try {
    await api.post(`/rpkps/${rpkps.value.id}/assessments`, {
      assessments: rpkps.value.assessments,
    })
    toast.success('Komponen asesmen berhasil disimpan.')
  } catch (e: any) { toast.error(e?.response?.data?.message ?? 'Gagal.') }
  finally { saving.value = false }
}

// References
function initReferences() {
  if (!rpkps.value.references?.length) {
    rpkps.value.references = []
  }
}

function addReference() {
  rpkps.value.references.push({
    type: 'Utama', category: 'Buku', title: '',
    author: '', year: '', publisher: '', isbn_doi: '', url: '',
  })
}

function removeReference(idx: number) {
  rpkps.value.references.splice(idx, 1)
}

async function saveReferences() {
  saving.value = true
  try {
    await api.post(`/rpkps/${rpkps.value.id}/references`, {
      references: rpkps.value.references,
    })
    toast.success('Referensi berhasil disimpan.')
  } catch (e: any) { toast.error(e?.response?.data?.message ?? 'Gagal.') }
  finally { saving.value = false }
}

// Workflow
async function handleSubmit() {
  if (!confirm('Ajukan RPKPS untuk validasi Kaprodi?')) return
  try {
    await api.post(`/rpkps/${rpkps.value.id}/submit`)
    toast.success('RPKPS berhasil diajukan.')
    load()
  } catch (e: any) { toast.error(e?.response?.data?.message ?? 'Gagal.') }
}

async function handleApprove() {
  if (!confirm('Setujui RPKPS ini?')) return
  try {
    await api.post(`/rpkps/${rpkps.value.id}/approve`)
    toast.success('RPKPS disetujui.')
    load()
  } catch (e: any) { toast.error(e?.response?.data?.message ?? 'Gagal.') }
}

async function handleRevise() {
  if (!rejectionNote.value.trim()) { toast.warning('Catatan wajib diisi.'); return }
  try {
    await api.post(`/rpkps/${rpkps.value.id}/revise`, { note: rejectionNote.value })
    toast.success('RPKPS dikembalikan untuk revisi.')
    rejectModalOpen.value = false
    rejectionNote.value = ''
    load()
  } catch (e: any) { toast.error(e?.response?.data?.message ?? 'Gagal.') }
}

async function handleLock() {
  if (!confirm('Kunci RPKPS? Tidak bisa diubah lagi setelah dikunci.')) return
  try {
    await api.post(`/rpkps/${rpkps.value.id}/lock`)
    toast.success('RPKPS dikunci.')
    load()
  } catch (e: any) { toast.error(e?.response?.data?.message ?? 'Gagal.') }
}

const methodOptions = [
  'Ceramah','Diskusi','Studi Kasus','Problem-Based Learning',
  'Project-Based Learning','Small Group Discussion','Cooperative Learning',
  'Role Play','Simulasi','Seminar','Praktikum','Penelitian',
]
</script>

<template>
  <div v-if="loading" class="flex items-center justify-center h-64"><p class="text-gray-400">Memuat...</p></div>

  <div v-else-if="rpkps" class="space-y-5 max-w-5xl mx-auto">
    <!-- Header -->
    <div class="flex items-start gap-3">
      <button class="p-1.5 rounded-lg text-gray-500 hover:bg-gray-100" @click="router.back()">
        <ArrowLeftIcon class="w-5 h-5" />
      </button>
      <div class="flex-1 min-w-0">
        <div class="flex items-center gap-2 flex-wrap">
          <h1 class="text-lg font-bold text-gray-900 truncate">{{ rpkps.course?.name }}</h1>
          <span :class="['px-2 py-0.5 rounded-full text-xs font-medium', statusColor[rpkps.status]]">
            {{ rpkps.status?.replace(/_/g, ' ') }}
          </span>
        </div>
        <p class="text-xs text-gray-500 mt-0.5">
          {{ rpkps.code }} · {{ rpkps.lecturer?.full_name ?? rpkps.lecturer?.name }} · {{ rpkps.academic_year?.name }}
        </p>
      </div>
      <!-- Actions -->
      <div class="flex gap-2 shrink-0">
        <button class="px-3 py-1.5 bg-red-50 hover:bg-red-100 text-red-700 border border-red-200 text-xs font-medium rounded-lg" @click="downloadPdf">
          PDF
        </button>
        <button v-if="isEditable" class="px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-medium rounded-lg" @click="handleSubmit">
          Ajukan Validasi
        </button>
        <button v-if="rpkps.status === 'DIAJUKAN' && auth.hasPermission('rps.approve')" class="px-3 py-1.5 bg-green-600 hover:bg-green-700 text-white text-xs font-medium rounded-lg" @click="handleApprove">
          <CheckIcon class="w-3.5 h-3.5 inline mr-0.5" /> Setujui
        </button>
        <button v-if="rpkps.status === 'DIAJUKAN' && auth.hasPermission('rps.approve')" class="px-3 py-1.5 bg-orange-600 hover:bg-orange-700 text-white text-xs font-medium rounded-lg" @click="rejectModalOpen = true">
          Revisi
        </button>
        <button v-if="rpkps.status === 'DISETUJUI' && auth.hasPermission('rps.approve')" class="px-3 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-medium rounded-lg" @click="handleLock">
          <LockClosedIcon class="w-3.5 h-3.5 inline mr-0.5" /> Kunci
        </button>
      </div>
    </div>

    <!-- Revision note -->
    <div v-if="rpkps.revision_note" class="p-3 bg-orange-50 border border-orange-200 rounded-lg text-sm">
      <p class="font-semibold text-orange-700 text-xs">Catatan Revisi:</p>
      <p class="text-orange-800 mt-1">{{ rpkps.revision_note }}</p>
    </div>

    <!-- Tabs -->
    <div class="flex gap-1 border-b border-gray-200 overflow-x-auto">
      <button v-for="t in tabs" :key="t.key"
        :class="['px-3 py-2 text-xs font-medium border-b-2 -mb-px transition-colors whitespace-nowrap',
          activeTab === t.key ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700']"
        @click="activeTab = t.key">
        {{ t.label }}
      </button>
    </div>

    <!-- TAB: Deskripsi & Bahan Kajian -->
    <div v-if="activeTab === 'deskripsi'" class="bg-white rounded-xl border border-gray-200 p-5 space-y-4">
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Deskripsi Mata Kuliah</label>
        <textarea v-model="rpkps.course_description" :disabled="!isEditable" rows="4" placeholder="Tuliskan deskripsi singkat mata kuliah..." class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:bg-gray-50" />
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Bahan Kajian</label>
        <textarea v-model="rpkps.course_scope" :disabled="!isEditable" rows="4" placeholder="Daftar bahan kajian / topik utama yang dibahas dalam mata kuliah ini..." class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:bg-gray-50" />
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Mata Kuliah Syarat</label>
        <select v-model="rpkps.prerequisites" :disabled="!isEditable" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:bg-gray-50">
          <option value="">— Tidak ada prasyarat —</option>
          <option v-for="c in curriculumCourses" :key="c.id" :value="c.code + ' - ' + c.name">
            {{ c.code }} – {{ c.name }}
          </option>
        </select>
        <p class="text-xs text-gray-400 mt-1">Pilih mata kuliah yang harus ditempuh sebelum mengambil mata kuliah ini.</p>
      </div>
      <button v-if="isEditable" :disabled="saving" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 disabled:bg-blue-400 text-white text-sm font-medium rounded-lg" @click="saveDeskripsi">
        {{ saving ? 'Menyimpan...' : 'Simpan' }}
      </button>
    </div>

    <!-- TAB: CPL -->
    <div v-if="activeTab === 'cpl'" class="bg-white rounded-xl border border-gray-200 p-5 space-y-4">
      <p class="text-sm text-gray-500">CPL yang didukung oleh mata kuliah ini (diambil otomatis dari pemetaan kurikulum).</p>

      <div v-if="mappedCpls.length" class="space-y-2">
        <div v-for="cpl in mappedCpls" :key="cpl.id" class="flex items-start gap-3 p-3 rounded-lg bg-gray-50">
          <span class="inline-flex px-2 py-0.5 rounded text-xs font-mono font-bold bg-blue-100 text-blue-700 shrink-0 mt-0.5">
            {{ cpl.code }}
          </span>
          <p class="text-sm text-gray-700">{{ cpl.description }}</p>
        </div>
      </div>

      <div v-else class="text-center py-6">
        <p class="text-sm text-gray-400">Belum ada CPL yang terpetakan untuk mata kuliah ini.</p>
        <p class="text-xs text-gray-400 mt-1">Atur pemetaan CPL–Mata Kuliah di halaman detail Kurikulum.</p>
      </div>
    </div>

    <!-- TAB: CPMK -->
    <div v-if="activeTab === 'cpmk'" class="bg-white rounded-xl border border-gray-200 p-5 space-y-4">
      <div class="flex items-center justify-between">
        <p class="text-sm text-gray-500">Capaian Pembelajaran Mata Kuliah</p>
        <button v-if="isEditable" class="flex items-center gap-1 px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-medium rounded-lg" @click="openAddCpmk">
          <PlusIcon class="w-3.5 h-3.5" /> Tambah CPMK
        </button>
      </div>
      <div v-if="rpkps.cpmks?.length" class="space-y-3">
        <div v-for="cpmk in rpkps.cpmks" :key="cpmk.id" class="p-3 bg-gray-50 rounded-lg group">
          <div class="flex items-start justify-between">
            <div>
              <span class="font-mono text-xs font-bold text-purple-700">{{ cpmk.code }}</span>
              <p class="text-sm text-gray-700 mt-0.5">{{ cpmk.description }}</p>
            </div>
            <div v-if="isEditable" class="flex items-center gap-1 opacity-0 group-hover:opacity-100">
              <button class="p-1 text-blue-500 hover:text-blue-700" title="Edit" @click="openEditCpmk(cpmk)">
                <PencilIcon class="w-4 h-4" />
              </button>
              <button class="p-1 text-red-400 hover:text-red-600" title="Hapus" @click="deleteCpmk(cpmk.id)">
                <TrashIcon class="w-4 h-4" />
              </button>
            </div>
          </div>
          <div v-if="cpmk.sub_cpmks?.length" class="mt-2 ml-4 space-y-1">
            <p v-for="sub in cpmk.sub_cpmks" :key="sub.id" class="text-xs text-gray-500">
              <span class="font-mono text-gray-400">{{ sub.code }}</span> {{ sub.description }}
            </p>
          </div>
        </div>
      </div>
      <p v-else class="text-center text-gray-400 text-sm py-4">Belum ada CPMK.</p>
    </div>

    <!-- TAB: Rencana Mingguan -->
    <div v-if="activeTab === 'mingguan'" class="bg-white rounded-xl border border-gray-200 p-5 space-y-4">
      <p class="text-sm text-gray-500">Rencana pembelajaran 16 minggu.</p>
      <button v-if="!rpkps.weekly_plans?.length && isEditable" class="px-3 py-1.5 bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs rounded-lg" @click="initWeeklyPlans">
        + Inisialisasi 16 Minggu
      </button>
      <div v-if="rpkps.weekly_plans?.length" class="overflow-x-auto">
        <table class="w-full text-xs border-collapse">
          <thead>
            <tr class="bg-gray-50">
              <th class="border px-2 py-1.5 w-10">Mg</th>
              <th class="border px-2 py-1.5">Sub-CPMK</th>
              <th class="border px-2 py-1.5">Bahan Kajian (Materi Ajar)</th>
              <th class="border px-2 py-1.5 w-28">Metode Pembelajaran</th>
              <th class="border px-2 py-1.5 w-20">Alokasi Waktu</th>
              <th class="border px-2 py-1.5">Pengalaman Belajar</th>
              <th class="border px-2 py-1.5 w-28">Kriteria/Jenis Penilaian</th>
              <th class="border px-2 py-1.5 w-14">Bobot</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="w in rpkps.weekly_plans" :key="w.week_number" :class="[w.week_number === 8 || w.week_number === 16 ? 'bg-orange-50' : '']">
              <td class="border px-2 py-1 text-center font-bold">{{ w.week_number }}</td>
              <td class="border px-1 py-1"><input v-model="w.sub_cpmk" :disabled="!isEditable" class="w-full px-1 py-0.5 text-xs border-0 focus:ring-1 focus:ring-blue-400 rounded" /></td>
              <td class="border px-1 py-1"><input v-model="w.learning_material" :disabled="!isEditable" class="w-full px-1 py-0.5 text-xs border-0 focus:ring-1 focus:ring-blue-400 rounded" /></td>
              <td class="border px-1 py-1"><input v-model="w.methods" :disabled="!isEditable" placeholder="Ceramah, Diskusi" class="w-full px-1 py-0.5 text-xs border-0 focus:ring-1 focus:ring-blue-400 rounded" /></td>
              <td class="border px-1 py-1"><input v-model="w.duration" :disabled="!isEditable" placeholder="2x50'" class="w-full px-1 py-0.5 text-xs border-0 focus:ring-1 focus:ring-blue-400 rounded text-center" /></td>
              <td class="border px-1 py-1"><input v-model="w.student_activity" :disabled="!isEditable" class="w-full px-1 py-0.5 text-xs border-0 focus:ring-1 focus:ring-blue-400 rounded" /></td>
              <td class="border px-1 py-1"><input v-model="w.assessment_form" :disabled="!isEditable" class="w-full px-1 py-0.5 text-xs border-0 focus:ring-1 focus:ring-blue-400 rounded" /></td>
              <td class="border px-1 py-1"><input v-model.number="w.weight" :disabled="!isEditable" type="number" min="0" class="w-full px-1 py-0.5 text-xs border-0 focus:ring-1 focus:ring-blue-400 rounded text-center" /></td>
            </tr>
          </tbody>
        </table>
      </div>
      <button v-if="rpkps.weekly_plans?.length && isEditable" :disabled="saving" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 disabled:bg-blue-400 text-white text-sm font-medium rounded-lg" @click="saveWeeklyPlans">
        {{ saving ? 'Menyimpan...' : 'Simpan Rencana Mingguan' }}
      </button>
    </div>

    <!-- TAB: Asesmen -->
    <div v-if="activeTab === 'asesmen'" class="bg-white rounded-xl border border-gray-200 p-5 space-y-4">
      <div class="flex items-center justify-between">
        <p class="text-sm text-gray-500">Komponen penilaian (total harus 100%)</p>
        <span :class="['text-xs font-bold', totalWeight === 100 ? 'text-green-600' : 'text-red-500']">
          Total: {{ totalWeight }}%
        </span>
      </div>
      <button v-if="!rpkps.assessments?.length && isEditable" class="px-3 py-1.5 bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs rounded-lg" @click="initAssessments">
        + Gunakan Template Default
      </button>
      <div v-if="rpkps.assessments?.length" class="space-y-2">
        <div v-for="(a, i) in rpkps.assessments" :key="i" class="flex items-center gap-3">
          <input v-model="a.name" :disabled="!isEditable" placeholder="Nama komponen" class="flex-1 px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:bg-gray-50" />
          <div class="flex items-center gap-1 w-24">
            <input v-model.number="a.weight" :disabled="!isEditable" type="number" min="0" max="100" class="w-16 px-2 py-2 border border-gray-300 rounded-lg text-sm text-center focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:bg-gray-50" />
            <span class="text-xs text-gray-500">%</span>
          </div>
          <button v-if="isEditable" class="p-1.5 text-red-400 hover:text-red-600" @click="removeAssessment(i)">
            <TrashIcon class="w-4 h-4" />
          </button>
        </div>
        <button v-if="isEditable" class="text-xs text-blue-600 hover:text-blue-700 font-medium" @click="addAssessment">
          + Tambah Komponen
        </button>
      </div>
      <button v-if="rpkps.assessments?.length && isEditable" :disabled="saving || totalWeight !== 100" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 disabled:bg-blue-400 text-white text-sm font-medium rounded-lg" @click="saveAssessments">
        {{ saving ? 'Menyimpan...' : 'Simpan Asesmen' }}
      </button>
      <p v-if="totalWeight !== 100 && rpkps.assessments?.length" class="text-xs text-red-500">
        Total bobot harus tepat 100%. Saat ini {{ totalWeight }}%.
      </p>
    </div>

    <!-- TAB: Referensi -->
    <div v-if="activeTab === 'referensi'" class="bg-white rounded-xl border border-gray-200 p-5 space-y-4">
      <div class="flex items-center justify-between">
        <p class="text-sm text-gray-500">Daftar pustaka</p>
        <button v-if="isEditable" class="text-xs text-blue-600 hover:text-blue-700 font-medium" @click="addReference">+ Tambah Referensi</button>
      </div>
      <div v-if="rpkps.references?.length" class="space-y-3">
        <div v-for="(r, i) in rpkps.references" :key="i" class="p-3 border border-gray-200 rounded-lg space-y-2">
          <div class="flex items-center gap-2">
            <select v-model="r.type" :disabled="!isEditable" class="px-2 py-1 border border-gray-300 rounded text-xs">
              <option value="Utama">Utama</option><option value="Pendukung">Pendukung</option>
            </select>
            <select v-model="r.category" :disabled="!isEditable" class="px-2 py-1 border border-gray-300 rounded text-xs">
              <option v-for="c in ['Buku','Jurnal','Peraturan','Fatwa','Putusan','Website','Artikel','Modul','Video','E-book','Lainnya']" :key="c" :value="c">{{ c }}</option>
            </select>
            <button v-if="isEditable" class="ml-auto p-1 text-red-400 hover:text-red-600" @click="removeReference(i)"><TrashIcon class="w-4 h-4" /></button>
          </div>
          <input v-model="r.title" :disabled="!isEditable" placeholder="Judul *" class="w-full px-2 py-1.5 border border-gray-300 rounded text-sm focus:ring-1 focus:ring-blue-500 disabled:bg-gray-50" />
          <div class="grid grid-cols-2 md:grid-cols-4 gap-2">
            <input v-model="r.author" :disabled="!isEditable" placeholder="Penulis" class="px-2 py-1.5 border border-gray-300 rounded text-xs disabled:bg-gray-50" />
            <input v-model="r.year" :disabled="!isEditable" placeholder="Tahun" class="px-2 py-1.5 border border-gray-300 rounded text-xs disabled:bg-gray-50" />
            <input v-model="r.publisher" :disabled="!isEditable" placeholder="Penerbit" class="px-2 py-1.5 border border-gray-300 rounded text-xs disabled:bg-gray-50" />
            <input v-model="r.isbn_doi" :disabled="!isEditable" placeholder="ISBN/DOI" class="px-2 py-1.5 border border-gray-300 rounded text-xs disabled:bg-gray-50" />
          </div>
          <input v-model="r.url" :disabled="!isEditable" placeholder="URL (opsional)" class="w-full px-2 py-1.5 border border-gray-300 rounded text-xs disabled:bg-gray-50" />
        </div>
      </div>
      <p v-else class="text-center text-gray-400 text-sm py-4">Belum ada referensi.</p>
      <button v-if="rpkps.references?.length && isEditable" :disabled="saving" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 disabled:bg-blue-400 text-white text-sm font-medium rounded-lg" @click="saveReferences">
        {{ saving ? 'Menyimpan...' : 'Simpan Referensi' }}
      </button>
    </div>

    <!-- TAB: Riwayat -->
    <div v-if="activeTab === 'riwayat'" class="bg-white rounded-xl border border-gray-200 p-5 space-y-3">
      <h2 class="text-sm font-semibold text-gray-800">Riwayat Approval</h2>
      <div v-if="rpkps.approvals?.length" class="space-y-2">
        <div v-for="a in rpkps.approvals" :key="a.id" class="flex items-center gap-3 text-xs text-gray-500 p-2 bg-gray-50 rounded">
          <span class="w-20 shrink-0 font-mono">{{ new Date(a.created_at).toLocaleDateString('id-ID') }}</span>
          <span class="font-medium text-gray-700">{{ a.user?.name }}</span>
          <span :class="['px-2 py-0.5 rounded text-xs font-medium', statusColor[a.action] ?? 'bg-gray-100']">{{ a.action?.replace(/_/g, ' ') }}</span>
          <span v-if="a.note" class="text-gray-400 truncate flex-1">{{ a.note }}</span>
        </div>
      </div>
      <p v-else class="text-sm text-gray-400">Belum ada riwayat.</p>
    </div>
  </div>

  <!-- Modal CPMK -->
  <BaseModal :open="cpmkModalOpen" :title="editingCpmkId ? 'Edit CPMK' : 'Tambah CPMK'" @close="cpmkModalOpen = false">
    <form class="space-y-4" @submit.prevent="saveCpmk">
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Kode <span class="text-red-500">*</span></label>
        <input v-model="cpmkForm.code" required placeholder="CPMK-01" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Deskripsi <span class="text-red-500">*</span></label>
        <textarea v-model="cpmkForm.description" required rows="3" placeholder="Mahasiswa mampu..." class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
      </div>
    </form>
    <template #footer>
      <button class="px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded-lg" @click="cpmkModalOpen = false">Batal</button>
      <button :disabled="saving" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 disabled:bg-blue-400 text-white text-sm font-medium rounded-lg" @click="saveCpmk">
        {{ saving ? 'Menyimpan...' : 'Simpan' }}
      </button>
    </template>
  </BaseModal>

  <!-- Modal Revisi -->
  <BaseModal :open="rejectModalOpen" title="Minta Revisi" @close="rejectModalOpen = false">
    <div>
      <label class="block text-sm font-medium text-gray-700 mb-1">Catatan Revisi <span class="text-red-500">*</span></label>
      <textarea v-model="rejectionNote" rows="4" placeholder="Bagian yang perlu diperbaiki..." class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
    </div>
    <template #footer>
      <button class="px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded-lg" @click="rejectModalOpen = false">Batal</button>
      <button class="px-4 py-2 bg-orange-600 hover:bg-orange-700 text-white text-sm font-medium rounded-lg" @click="handleRevise">Kirim</button>
    </template>
  </BaseModal>
</template>
