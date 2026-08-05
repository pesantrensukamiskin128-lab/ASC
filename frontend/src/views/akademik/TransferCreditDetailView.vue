<script setup lang="ts">
import { ref, reactive, onMounted, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useToast } from 'vue-toastification'
import { ArrowLeftIcon, PlusIcon, TrashIcon, CheckCircleIcon, XCircleIcon, ArrowsRightLeftIcon } from '@heroicons/vue/24/outline'
import BaseModal from '@/components/ui/BaseModal.vue'
import api from '@/services/api'

const route = useRoute()
const router = useRouter()
const toast = useToast()
const loading = ref(true)
const data = ref<any>(null)
const courses = ref<any[]>([])

onMounted(async () => {
  try {
    const [appRes, courseRes] = await Promise.all([
      api.get(`/transfer-credits/${route.params.id}`),
      api.get('/courses/all'),
    ])
    data.value = appRes.data
    courses.value = courseRes.data
  } finally { loading.value = false }
})

async function reload() {
  const { data: res } = await api.get(`/transfer-credits/${route.params.id}`)
  data.value = res
}

const statusColor: Record<string, string> = {
  DRAFT: 'bg-gray-100 text-gray-600', SUBMITTED: 'bg-blue-100 text-blue-700',
  DOCUMENT_VERIFICATION: 'bg-yellow-100 text-yellow-700', ACADEMIC_EVALUATION: 'bg-purple-100 text-purple-700',
  APPROVED: 'bg-green-100 text-green-700', REJECTED: 'bg-red-100 text-red-600', FINALIZED: 'bg-emerald-100 text-emerald-700',
}

const totalSourceCredits = computed(() => data.value?.source_courses?.reduce((s: number, c: any) => s + Number(c.credits), 0) ?? 0)
const totalRecognized = computed(() => data.value?.conversions?.filter((c: any) => c.conversion_type !== 'REJECTED')?.reduce((s: number, c: any) => s + Number(c.recognized_credits), 0) ?? 0)

// === Source Course Modal ===
const scModal = ref(false); const scSaving = ref(false)
const scForm = reactive({ course_code: '', course_name: '', credits: 3, grade_letter: '', grade_numeric: '', semester_taken: '', year_taken: '' })
const importFileInput = ref<HTMLInputElement | null>(null)
const importing = ref(false)

function openAddSource() { Object.assign(scForm, { course_code: '', course_name: '', credits: 3, grade_letter: '', grade_numeric: '', semester_taken: '', year_taken: '' }); scModal.value = true }
async function saveSourceCourse() {
  scSaving.value = true
  try { await api.post(`/transfer-credits/${data.value.id}/source-courses`, scForm); toast.success('Ditambahkan.'); scModal.value = false; reload() }
  catch (e: any) { toast.error(e?.response?.data?.message ?? 'Gagal.') }
  finally { scSaving.value = false }
}
async function removeSource(id: number) {
  if (!confirm('Hapus?')) return
  await api.delete(`/transfer-credits/${data.value.id}/source-courses/${id}`); reload()
}

async function downloadTemplate() {
  try {
    const res = await api.get('/transfer-credits/template-source-courses', { responseType: 'blob' })
    const url = URL.createObjectURL(new Blob([res.data]))
    const link = document.createElement('a'); link.href = url; link.download = 'template-transfer-matakuliah.xlsx'
    document.body.appendChild(link); link.click(); link.remove(); URL.revokeObjectURL(url)
  } catch { toast.error('Gagal download template.') }
}

async function onImportFile(e: Event) {
  const file = (e.target as HTMLInputElement).files?.[0]
  if (!file) return
  importing.value = true
  try {
    const fd = new FormData(); fd.append('file', file)
    const { data: res } = await api.post(`/transfer-credits/${data.value.id}/source-courses/import`, fd, { headers: { 'Content-Type': 'multipart/form-data' } })
    toast.success(res.message)
    reload()
  } catch (e: any) {
    const msg = e?.response?.data?.errors ? e.response.data.errors.join(', ') : (e?.response?.data?.message ?? 'Gagal import.')
    toast.error(msg)
  } finally { importing.value = false; if (importFileInput.value) importFileInput.value.value = '' }
}

// === Conversion Modal ===
const convModal = ref(false); const convSaving = ref(false)
const convForm = reactive({ source_course_id: '', target_course_id: '', recognized_credits: 0, converted_grade: '', converted_grade_point: '', conversion_type: 'DIRECT', notes: '' })

function openMapConversion(sc: any) {
  Object.assign(convForm, { source_course_id: sc.id, target_course_id: '', recognized_credits: sc.credits, converted_grade: sc.grade_letter, converted_grade_point: sc.grade_numeric ?? '', conversion_type: 'DIRECT', notes: '' })
  convModal.value = true
}
async function saveConversion() {
  convSaving.value = true
  try { await api.post(`/transfer-credits/${data.value.id}/conversions`, convForm); toast.success('Pemetaan berhasil.'); convModal.value = false; reload() }
  catch (e: any) { toast.error(e?.response?.data?.message ?? 'Gagal.') }
  finally { convSaving.value = false }
}

// === Actions ===
async function submitApp() {
  if (!confirm('Submit aplikasi?')) return
  try { await api.post(`/transfer-credits/${data.value.id}/submit`); toast.success('Berhasil disubmit.'); reload() }
  catch (e: any) { toast.error(e?.response?.data?.message ?? 'Gagal.') }
}

async function handleApprove(role: string, action: string) {
  const notes = action === 'reject' ? prompt('Alasan penolakan:') : ''
  if (action === 'reject' && !notes) return
  try { await api.post(`/transfer-credits/${data.value.id}/approve`, { approval_role: role, action, notes }); toast.success('Berhasil.'); reload() }
  catch (e: any) { toast.error(e?.response?.data?.message ?? 'Gagal.') }
}

async function handleFinalize() {
  if (!confirm('Finalisasi? Nilai transfer akan masuk transkrip mahasiswa.')) return
  try { await api.post(`/transfer-credits/${data.value.id}/finalize`); toast.success('Berhasil difinalisasi!'); reload() }
  catch (e: any) { toast.error(e?.response?.data?.message ?? 'Gagal.') }
}
</script>

<template>
  <div v-if="loading" class="flex items-center justify-center h-64"><p class="text-gray-400">Memuat...</p></div>
  <div v-else-if="data" class="space-y-6 max-w-5xl mx-auto">
    <!-- Header -->
    <div class="flex items-start gap-3">
      <button class="p-2 rounded-lg hover:bg-gray-100" @click="router.back()"><ArrowLeftIcon class="w-5 h-5 text-gray-500" /></button>
      <div class="flex-1">
        <h1 class="text-xl font-bold text-gray-900">Transfer Nilai — {{ data.student?.name }}</h1>
        <p class="text-sm text-gray-500">{{ data.student?.nim }} · {{ data.source_institution?.name ?? 'Internal' }}</p>
      </div>
      <span :class="['px-3 py-1 rounded-full text-sm font-medium', statusColor[data.status]]">{{ data.status.replace(/_/g, ' ') }}</span>
    </div>

    <!-- Info Asal -->
    <div class="bg-white rounded-xl border border-gray-200 p-5 grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
      <div><span class="text-xs text-gray-400">PT Asal</span><p class="text-gray-800 font-medium">{{ data.source_institution?.name ?? '-' }}</p></div>
      <div><span class="text-xs text-gray-400">Prodi Asal</span><p class="text-gray-800">{{ data.source_study_program ?? '-' }}</p></div>
      <div><span class="text-xs text-gray-400">SKS Asal</span><p class="text-gray-800 font-bold">{{ data.source_total_credits ?? '-' }}</p></div>
      <div><span class="text-xs text-gray-400">IPK Asal</span><p class="text-gray-800 font-bold">{{ data.source_gpa ?? '-' }}</p></div>
    </div>

    <!-- Summary -->
    <div class="grid grid-cols-3 gap-4">
      <div class="bg-blue-50 border border-blue-200 rounded-lg p-3 text-center">
        <p class="text-xl font-bold text-blue-700">{{ totalSourceCredits }}</p><p class="text-xs text-blue-600">SKS Asal (Input)</p>
      </div>
      <div class="bg-green-50 border border-green-200 rounded-lg p-3 text-center">
        <p class="text-xl font-bold text-green-700">{{ totalRecognized }}</p><p class="text-xs text-green-600">SKS Diakui</p>
      </div>
      <div class="bg-red-50 border border-red-200 rounded-lg p-3 text-center">
        <p class="text-xl font-bold text-red-700">{{ totalSourceCredits - totalRecognized }}</p><p class="text-xs text-red-600">SKS Tidak Diakui</p>
      </div>
    </div>

    <!-- Mata Kuliah Asal -->
    <div class="bg-white rounded-xl border border-gray-200 p-5">
      <div class="flex items-center justify-between mb-3">
        <h2 class="text-sm font-semibold text-gray-800">Mata Kuliah Asal</h2>
        <div v-if="data.status === 'DRAFT'" class="flex items-center gap-2">
          <button class="text-xs text-gray-500 hover:text-blue-600 underline" @click="downloadTemplate">Download Template</button>
          <input ref="importFileInput" type="file" accept=".xlsx,.xls,.csv" class="hidden" @change="onImportFile" />
          <button :disabled="importing" class="px-3 py-1.5 bg-green-600 hover:bg-green-700 disabled:bg-green-400 text-white text-xs font-medium rounded-lg" @click="importFileInput?.click()">
            {{ importing ? 'Importing...' : 'Import Excel' }}
          </button>
          <button class="flex items-center gap-1 px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-medium rounded-lg" @click="openAddSource"><PlusIcon class="w-3.5 h-3.5" /> Tambah Manual</button>
        </div>
      </div>
      <table class="w-full text-sm">
        <thead><tr class="text-left text-xs text-gray-400 border-b"><th class="pb-2">Kode</th><th class="pb-2">Mata Kuliah</th><th class="pb-2 text-center">SKS</th><th class="pb-2 text-center">Nilai</th><th class="pb-2">Konversi</th><th class="pb-2 text-right">Aksi</th></tr></thead>
        <tbody>
          <tr v-for="sc in data.source_courses" :key="sc.id" class="border-b border-gray-50">
            <td class="py-2 font-mono text-xs text-gray-500">{{ sc.course_code ?? '-' }}</td>
            <td class="py-2 text-gray-800">{{ sc.course_name }}</td>
            <td class="py-2 text-center font-medium">{{ sc.credits }}</td>
            <td class="py-2 text-center"><span class="font-bold">{{ sc.grade_letter ?? '-' }}</span></td>
            <td class="py-2">
              <span v-if="sc.conversion" class="text-xs px-2 py-0.5 rounded" :class="sc.conversion.conversion_type === 'REJECTED' ? 'bg-red-100 text-red-600' : 'bg-green-100 text-green-700'">
                → {{ sc.conversion.target_course?.name ?? '?' }} ({{ sc.conversion.converted_grade }})
              </span>
              <button v-else-if="!['FINALIZED','REJECTED'].includes(data.status)" class="text-xs text-blue-600 hover:text-blue-700 font-medium" @click="openMapConversion(sc)">Petakan →</button>
              <span v-else class="text-xs text-gray-400">-</span>
            </td>
            <td class="py-2 text-right">
              <button v-if="data.status === 'DRAFT'" class="p-1 text-red-500 hover:bg-red-50 rounded" @click="removeSource(sc.id)"><TrashIcon class="w-3.5 h-3.5" /></button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Approval Timeline -->
    <div v-if="data.approvals?.length" class="bg-white rounded-xl border border-gray-200 p-5">
      <h2 class="text-sm font-semibold text-gray-800 mb-3">Persetujuan</h2>
      <div class="space-y-3">
        <div v-for="a in data.approvals" :key="a.id" class="flex items-center gap-3">
          <div :class="['w-7 h-7 rounded-full flex items-center justify-center text-xs shrink-0', a.status === 'APPROVED' ? 'bg-green-100 text-green-600' : a.status === 'REJECTED' ? 'bg-red-100 text-red-600' : 'bg-gray-100 text-gray-400']">
            <CheckCircleIcon v-if="a.status === 'APPROVED'" class="w-4 h-4" />
            <XCircleIcon v-else-if="a.status === 'REJECTED'" class="w-4 h-4" />
            <span v-else class="font-bold">{{ a.approval_level }}</span>
          </div>
          <div class="flex-1">
            <span class="text-sm font-medium text-gray-800">{{ a.approval_role.replace(/_/g, ' ') }}</span>
            <span :class="['ml-2 text-xs px-2 py-0.5 rounded-full', a.status === 'APPROVED' ? 'bg-green-100 text-green-700' : a.status === 'REJECTED' ? 'bg-red-100 text-red-600' : 'bg-gray-100 text-gray-500']">{{ a.status }}</span>
          </div>
          <div v-if="a.status === 'PENDING' && !['FINALIZED','REJECTED'].includes(data.status)" class="flex items-center gap-1">
            <button class="px-2 py-1 text-xs bg-green-600 hover:bg-green-700 text-white rounded" @click="handleApprove(a.approval_role, 'approve')">Setujui</button>
            <button class="px-2 py-1 text-xs bg-red-600 hover:bg-red-700 text-white rounded" @click="handleApprove(a.approval_role, 'reject')">Tolak</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Placement -->
    <div v-if="data.placement" class="bg-white rounded-xl border border-gray-200 p-5">
      <h2 class="text-sm font-semibold text-gray-800 mb-2">Penempatan Semester</h2>
      <p class="text-sm text-gray-600">Rekomendasi: <strong>Semester {{ data.placement.recommended_semester }}</strong></p>
      <p v-if="data.placement.approved_semester" class="text-sm text-green-600 font-medium mt-1">Disetujui: Semester {{ data.placement.approved_semester }}</p>
    </div>

    <!-- Actions -->
    <div class="flex items-center gap-3">
      <button v-if="data.status === 'DRAFT'" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg" @click="submitApp">Submit Aplikasi</button>
      <button v-if="data.status === 'APPROVED'" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-medium rounded-lg" @click="handleFinalize">Finalisasi ke Transkrip</button>
    </div>

    <!-- Transferred Grades (after finalize) -->
    <div v-if="data.transferred_grades?.length" class="bg-white rounded-xl border border-emerald-200 p-5">
      <h2 class="text-sm font-semibold text-emerald-800 mb-3">✓ Nilai yang Masuk Transkrip</h2>
      <table class="w-full text-sm">
        <thead><tr class="text-left text-xs text-gray-400 border-b"><th class="pb-2">Mata Kuliah</th><th class="pb-2 text-center">SKS</th><th class="pb-2 text-center">Nilai</th><th class="pb-2 text-center">Bobot</th></tr></thead>
        <tbody>
          <tr v-for="g in data.transferred_grades" :key="g.id" class="border-b border-gray-50">
            <td class="py-2 text-gray-800">{{ g.target_course?.name }}</td>
            <td class="py-2 text-center">{{ g.recognized_credits }}</td>
            <td class="py-2 text-center font-bold">{{ g.grade_letter }}</td>
            <td class="py-2 text-center text-gray-600">{{ g.grade_point }}</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Modal: Tambah MK Asal -->
  <BaseModal :open="scModal" title="Tambah Mata Kuliah Asal" @close="scModal = false">
    <form class="space-y-3" @submit.prevent="saveSourceCourse">
      <div class="grid grid-cols-4 gap-3">
        <div><label class="text-xs text-gray-700">Kode</label><input v-model="scForm.course_code" class="w-full mt-1 px-3 py-2 border rounded-lg text-sm" /></div>
        <div class="col-span-3"><label class="text-xs text-gray-700">Nama MK <span class="text-red-500">*</span></label><input v-model="scForm.course_name" required class="w-full mt-1 px-3 py-2 border rounded-lg text-sm" /></div>
      </div>
      <div class="grid grid-cols-4 gap-3">
        <div><label class="text-xs text-gray-700">SKS</label><input v-model.number="scForm.credits" type="number" step="0.5" min="1" class="w-full mt-1 px-3 py-2 border rounded-lg text-sm" /></div>
        <div><label class="text-xs text-gray-700">Nilai Huruf</label><input v-model="scForm.grade_letter" placeholder="A" class="w-full mt-1 px-3 py-2 border rounded-lg text-sm" /></div>
        <div><label class="text-xs text-gray-700">Bobot</label><input v-model="scForm.grade_numeric" type="number" step="0.25" min="0" max="4" class="w-full mt-1 px-3 py-2 border rounded-lg text-sm" /></div>
        <div><label class="text-xs text-gray-700">Semester</label><input v-model="scForm.semester_taken" class="w-full mt-1 px-3 py-2 border rounded-lg text-sm" /></div>
      </div>
    </form>
    <template #footer>
      <button class="px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded-lg" @click="scModal = false">Batal</button>
      <button :disabled="scSaving" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 disabled:bg-blue-400 text-white text-sm font-medium rounded-lg" @click="saveSourceCourse">Simpan</button>
    </template>
  </BaseModal>

  <!-- Modal: Pemetaan Konversi -->
  <BaseModal :open="convModal" title="Pemetaan Mata Kuliah" @close="convModal = false">
    <form class="space-y-4" @submit.prevent="saveConversion">
      <div>
        <label class="text-xs font-medium text-gray-700">Mata Kuliah Tujuan <span class="text-red-500">*</span></label>
        <select v-model="convForm.target_course_id" required class="w-full mt-1 px-3 py-2 border rounded-lg text-sm">
          <option value="">-- Pilih --</option>
          <option v-for="c in courses" :key="c.id" :value="c.id">{{ c.code }} - {{ c.name }} ({{ c.credits }} SKS)</option>
        </select>
      </div>
      <div class="grid grid-cols-3 gap-3">
        <div><label class="text-xs text-gray-700">SKS Diakui</label><input v-model.number="convForm.recognized_credits" type="number" step="0.5" class="w-full mt-1 px-3 py-2 border rounded-lg text-sm" /></div>
        <div><label class="text-xs text-gray-700">Nilai Konversi</label><input v-model="convForm.converted_grade" class="w-full mt-1 px-3 py-2 border rounded-lg text-sm" /></div>
        <div><label class="text-xs text-gray-700">Bobot</label><input v-model="convForm.converted_grade_point" type="number" step="0.25" min="0" max="4" class="w-full mt-1 px-3 py-2 border rounded-lg text-sm" /></div>
      </div>
      <div>
        <label class="text-xs font-medium text-gray-700">Jenis Konversi</label>
        <select v-model="convForm.conversion_type" class="w-full mt-1 px-3 py-2 border rounded-lg text-sm">
          <option value="DIRECT">Langsung (Direct)</option>
          <option value="PARTIAL">Parsial</option>
          <option value="COMBINATION">Gabungan</option>
          <option value="ELECTIVE">Pilihan (Elective)</option>
          <option value="REJECTED">Ditolak</option>
        </select>
      </div>
      <div><label class="text-xs text-gray-700">Catatan</label><textarea v-model="convForm.notes" rows="2" class="w-full mt-1 px-3 py-2 border rounded-lg text-sm" /></div>
    </form>
    <template #footer>
      <button class="px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded-lg" @click="convModal = false">Batal</button>
      <button :disabled="convSaving" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 disabled:bg-blue-400 text-white text-sm font-medium rounded-lg" @click="saveConversion">Simpan Pemetaan</button>
    </template>
  </BaseModal>
</template>
