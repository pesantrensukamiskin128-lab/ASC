<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useToast } from 'vue-toastification'
import { PlusIcon, TrashIcon, PaperAirplaneIcon, CheckCircleIcon, ExclamationTriangleIcon } from '@heroicons/vue/24/outline'
import BaseModal from '@/components/ui/BaseModal.vue'
import api from '@/services/api'

const toast = useToast()
const loading = ref(true)
const student = ref<any>(null)
const krs = ref<any>(null)
const availableClasses = ref<any[]>([])
const activeSemester = ref<any>(null)

// Add course modal
const addModal = ref(false)
const selectedCourseId = ref('')
const selectedClassId = ref('')
const addingCourse = ref(false)

const statusColor: Record<string, string> = {
  DRAFT: 'bg-gray-100 text-gray-600', SUBMITTED: 'bg-yellow-100 text-yellow-700',
  APPROVED: 'bg-green-100 text-green-700', REJECTED: 'bg-red-100 text-red-600',
}

onMounted(async () => {
  try {
    // Get student info
    const { data: me } = await api.get('/auth/me')
    const { data: dashboard } = await api.get('/dashboard')
    student.value = dashboard.student

    // Get active semester
    const { data: semesters } = await api.get('/semesters')
    const semData = semesters.data ?? semesters
    activeSemester.value = semData.find((s: any) => s.is_active) ?? semData[0]

    if (activeSemester.value) {
      await loadKrs()
      await loadAvailableClasses()
    }
  } catch (e: any) { console.error(e) }
  finally { loading.value = false }
})

async function loadKrs() {
  try {
    const { data } = await api.get('/krs', { params: { semester_id: activeSemester.value?.id } })
    const list = data.data ?? data ?? []
    // Mahasiswa: backend sudah filter by student, ambil yang pertama untuk semester ini
    const found = list.length > 0 ? list[0] : null
    if (found?.id) {
      const { data: detail } = await api.get(`/krs/${found.id}`)
      krs.value = detail
    } else {
      krs.value = null
    }
  } catch { krs.value = null }
}

async function loadAvailableClasses() {
  if (!activeSemester.value) return
  try {
    const { data } = await api.get('/classes', { params: { semester_id: activeSemester.value.id, per_page: 100 } })
    availableClasses.value = data.data ?? data ?? []
  } catch { availableClasses.value = [] }
}

// Grup classes by course
const courseOptions = computed(() => {
  const map = new Map<number, { course: any; classes: any[] }>()
  for (const cls of availableClasses.value) {
    if (!cls.course) continue
    if (!map.has(cls.course.id)) {
      map.set(cls.course.id, { course: cls.course, classes: [] })
    }
    map.get(cls.course.id)!.classes.push(cls)
  }
  return Array.from(map.values())
})

// Classes for selected course
const classesForCourse = computed(() => {
  if (!selectedCourseId.value) return []
  return availableClasses.value.filter((c: any) => c.course_id == selectedCourseId.value)
})

const isEditable = computed(() => {
  if (!krs.value) return false
  const status = krs.value.status ?? 'DRAFT'
  return status === 'DRAFT' || status === 'REJECTED'
})

async function createKrs() {
  if (!activeSemester.value) return
  try {
    const { data } = await api.post('/krs', { semester_id: activeSemester.value.id })
    toast.success(data.message)
    // Load detail KRS yang baru dibuat
    if (data.data?.id) {
      const { data: detail } = await api.get(`/krs/${data.data.id}`)
      krs.value = detail
    }
  } catch (e: any) { toast.error(e?.response?.data?.message ?? 'Gagal membuat KRS.') }
}

function openAddCourse() {
  selectedCourseId.value = ''
  selectedClassId.value = ''
  addModal.value = true
}

async function addCourse() {
  if (!krs.value || !selectedCourseId.value) return
  addingCourse.value = true
  try {
    await api.post(`/krs/${krs.value.id}/courses`, { course_id: selectedCourseId.value, class_id: selectedClassId.value || null })
    toast.success('Mata kuliah ditambahkan.')
    addModal.value = false
    await loadKrs()
  } catch (e: any) { toast.error(e?.response?.data?.message ?? 'Gagal.') }
  finally { addingCourse.value = false }
}

async function removeCourse(detailId: number) {
  if (!confirm('Hapus mata kuliah ini dari KRS?')) return
  try {
    await api.delete(`/krs/${krs.value.id}/courses/${detailId}`)
    toast.success('Dihapus.')
    await loadKrs()
  } catch (e: any) { toast.error(e?.response?.data?.message ?? 'Gagal.') }
}

async function submitKrs() {
  if (!confirm('Submit KRS ke dosen wali? Pastikan semua mata kuliah sudah benar.')) return
  try {
    await api.post(`/krs/${krs.value.id}/submit`)
    toast.success('KRS berhasil disubmit ke dosen wali.')
    await loadKrs()
  } catch (e: any) { toast.error(e?.response?.data?.message ?? 'Gagal submit.') }
}

async function downloadKrsPdf() {
  try {
    const res = await api.get(`/krs/${krs.value.id}/pdf`, { responseType: 'blob' })
    const url = URL.createObjectURL(new Blob([res.data], { type: 'application/pdf' }))
    const link = document.createElement('a')
    link.href = url
    link.download = `KRS-${student.value?.nim ?? ''}.pdf`
    document.body.appendChild(link); link.click(); link.remove()
    URL.revokeObjectURL(url)
  } catch { toast.error('Gagal download PDF.') }
}
</script>

<template>
  <div class="space-y-6">
    <div>
      <h1 class="text-xl font-bold text-gray-900">KRS Saya</h1>
      <p class="text-sm text-gray-500 mt-0.5">Kartu Rencana Studi — {{ activeSemester?.name ?? 'Memuat...' }}</p>
    </div>

    <div v-if="loading" class="flex items-center justify-center h-48"><p class="text-gray-400">Memuat...</p></div>

    <template v-else>
      <!-- Belum buat KRS -->
      <div v-if="!krs" class="bg-white rounded-xl border border-dashed border-gray-300 p-8 text-center">
        <p class="text-gray-500 mb-4">Anda belum membuat KRS untuk semester ini.</p>
        <button class="px-6 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg" @click="createKrs">
          Mulai Buat KRS
        </button>
      </div>

      <!-- KRS ada -->
      <template v-else>
        <!-- Status Banner -->
        <div v-if="krs.status === 'APPROVED'" class="bg-green-50 border border-green-200 rounded-xl p-4 flex items-center gap-3">
          <CheckCircleIcon class="w-6 h-6 text-green-600 shrink-0" />
          <div class="flex-1">
            <p class="text-sm font-medium text-green-800">KRS Disetujui ✓</p>
            <p class="text-xs text-green-600">Disetujui oleh dosen wali. Total {{ krs.total_credits }} SKS.</p>
          </div>
          <button class="px-3 py-1.5 bg-red-50 hover:bg-red-100 text-red-700 border border-red-200 text-xs font-medium rounded-lg" @click="downloadKrsPdf">PDF</button>
        </div>
        <div v-else-if="krs.status === 'SUBMITTED'" class="bg-yellow-50 border border-yellow-200 rounded-xl p-4 flex items-center gap-3">
          <ExclamationTriangleIcon class="w-6 h-6 text-yellow-600 shrink-0" />
          <div>
            <p class="text-sm font-medium text-yellow-800">Menunggu Persetujuan Dosen Wali</p>
            <p class="text-xs text-yellow-600">KRS Anda sedang direview. Tunggu notifikasi.</p>
          </div>
        </div>
        <div v-else-if="krs.status === 'REJECTED'" class="bg-red-50 border border-red-200 rounded-xl p-4 flex items-center gap-3">
          <ExclamationTriangleIcon class="w-6 h-6 text-red-600 shrink-0" />
          <div>
            <p class="text-sm font-medium text-red-800">KRS Ditolak</p>
            <p class="text-xs text-red-600">{{ krs.advisor_note || 'Perbaiki KRS Anda dan submit ulang.' }}</p>
          </div>
        </div>

        <!-- Summary -->
        <div class="grid grid-cols-3 gap-4">
          <div class="bg-white rounded-xl border border-gray-200 p-4 text-center">
            <p class="text-2xl font-bold text-blue-700">{{ krs.total_credits }}</p>
            <p class="text-xs text-gray-500">Total SKS</p>
          </div>
          <div class="bg-white rounded-xl border border-gray-200 p-4 text-center">
            <p class="text-2xl font-bold text-gray-700">{{ krs.details?.length ?? 0 }}</p>
            <p class="text-xs text-gray-500">Mata Kuliah</p>
          </div>
          <div class="bg-white rounded-xl border border-gray-200 p-4 text-center">
            <span :class="['px-3 py-1 rounded-full text-sm font-medium', statusColor[krs.status]]">{{ krs.status }}</span>
            <p class="text-xs text-gray-500 mt-1">Status</p>
          </div>
        </div>

        <!-- List MK -->
        <div class="bg-white rounded-xl border border-gray-200 p-5">
          <div class="flex items-center justify-between mb-4">
            <h2 class="text-sm font-semibold text-gray-800">Mata Kuliah yang Diambil</h2>
            <button v-if="isEditable" class="flex items-center gap-1 px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-medium rounded-lg" @click="openAddCourse">
              <PlusIcon class="w-3.5 h-3.5" /> Tambah MK
            </button>
          </div>

          <div v-if="!krs.details?.length" class="text-center text-gray-400 py-6 text-sm">Belum ada mata kuliah. Klik "Tambah MK" untuk mulai.</div>

          <table v-else class="w-full text-sm">
            <thead><tr class="text-left text-xs text-gray-500 border-b"><th class="pb-2">Kode</th><th class="pb-2">Mata Kuliah</th><th class="pb-2 text-center">SKS</th><th class="pb-2">Kelas</th><th class="pb-2">Dosen</th><th class="pb-2">Jadwal</th><th class="pb-2">Ruangan</th><th v-if="isEditable" class="pb-2"></th></tr></thead>
            <tbody>
              <tr v-for="d in krs.details" :key="d.id" class="border-b border-gray-50">
                <td class="py-2.5 font-mono text-xs text-gray-600">{{ d.course?.code }}</td>
                <td class="py-2.5 font-medium text-gray-900">{{ d.course?.name }}</td>
                <td class="py-2.5 text-center">{{ d.course?.credits }}</td>
                <td class="py-2.5 text-gray-600">{{ d.class_?.name ?? '-' }}</td>
                <td class="py-2.5 text-gray-600 text-xs">{{ d.class_?.lecturer?.full_name ?? d.class_?.lecturer?.name ?? '-' }}</td>
                <td class="py-2.5 text-gray-600 text-xs">{{ d.class_?.schedules?.[0] ? d.class_.schedules[0].day + ' ' + d.class_.schedules[0].start_time?.slice(0,5) + '-' + d.class_.schedules[0].end_time?.slice(0,5) : '-' }}</td>
                <td class="py-2.5 text-gray-600 text-xs">{{ d.class_?.room?.name ?? d.class_?.schedules?.[0]?.room?.name ?? '-' }}</td>
                <td v-if="isEditable" class="py-2.5 text-right">
                  <button class="p-1 text-red-400 hover:text-red-600" @click="removeCourse(d.id)"><TrashIcon class="w-4 h-4" /></button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Submit button -->
        <div v-if="isEditable && krs.details?.length" class="flex justify-end">
          <button class="flex items-center gap-2 px-5 py-2.5 bg-green-600 hover:bg-green-700 text-white text-sm font-medium rounded-lg" @click="submitKrs">
            <PaperAirplaneIcon class="w-4 h-4" /> Submit KRS ke Dosen Wali
          </button>
        </div>
      </template>
    </template>
  </div>

  <!-- Modal Tambah MK -->
  <BaseModal :open="addModal" title="Tambah Mata Kuliah" size="lg" @close="addModal = false">
    <form class="space-y-4" @submit.prevent="addCourse">
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Mata Kuliah <span class="text-red-500">*</span></label>
        <select v-model="selectedCourseId" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
          <option value="">-- Pilih Mata Kuliah --</option>
          <option v-for="opt in courseOptions" :key="opt.course.id" :value="opt.course.id">
            {{ opt.course.code }} - {{ opt.course.name }} ({{ opt.course.credits }} SKS)
          </option>
        </select>
      </div>
      <div v-if="classesForCourse.length">
        <label class="block text-sm font-medium text-gray-700 mb-1">Pilih Kelas</label>
        <select v-model="selectedClassId" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
          <option value="">-- Auto (belum ditentukan) --</option>
          <option v-for="cls in classesForCourse" :key="cls.id" :value="cls.id">
            {{ cls.name }} — {{ cls.lecturer?.full_name ?? cls.lecturer?.name ?? '-' }} ({{ cls.members_count ?? 0 }}/{{ cls.capacity }})
          </option>
        </select>
      </div>
      <p v-if="!courseOptions.length" class="text-sm text-gray-400 text-center py-4">Tidak ada kelas yang tersedia untuk semester ini.</p>
    </form>
    <template #footer>
      <button class="px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded-lg" @click="addModal = false">Batal</button>
      <button :disabled="addingCourse || !selectedCourseId" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 disabled:bg-blue-400 text-white text-sm font-medium rounded-lg" @click="addCourse">
        {{ addingCourse ? 'Menambahkan...' : 'Tambah ke KRS' }}
      </button>
    </template>
  </BaseModal>
</template>
