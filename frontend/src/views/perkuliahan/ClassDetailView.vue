<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ArrowLeftIcon, PlusIcon, TrashIcon, PencilIcon, CheckCircleIcon, BookOpenIcon, ClipboardDocumentListIcon, MegaphoneIcon, AcademicCapIcon } from '@heroicons/vue/24/outline'
import { useToast } from 'vue-toastification'
import { useAuthStore } from '@/stores/auth'
import BaseModal from '@/components/ui/BaseModal.vue'
import api from '@/services/api'

const route  = useRoute()
const router = useRouter()
const toast  = useToast()
const auth   = useAuthStore()

const classData = ref<any>(null)
const loading   = ref(true)
const activeTab = ref('jurnal')

// Data
const journals      = ref<any[]>([])
const materials     = ref<any[]>([])
const assignments   = ref<any[]>([])
const announcements = ref<any[]>([])
const exams         = ref<any[]>([])

const tabs = [
  { key: 'jurnal', label: 'Jurnal & Presensi', icon: ClipboardDocumentListIcon },
  { key: 'materi', label: 'Materi', icon: BookOpenIcon },
  { key: 'tugas', label: 'Tugas', icon: ClipboardDocumentListIcon },
  { key: 'ujian', label: 'Ujian', icon: AcademicCapIcon },
  { key: 'pengumuman', label: 'Pengumuman', icon: MegaphoneIcon },
]

onMounted(async () => {
  try {
    const { data } = await api.get(`/lectures/${route.params.id}`)
    classData.value = data
    loadTab()
  } catch { toast.error('Gagal memuat data kelas.') }
  finally { loading.value = false }
})

async function loadTab() {
  const id = route.params.id
  if (activeTab.value === 'jurnal') {
    const { data } = await api.get(`/lectures/${id}/journals`)
    journals.value = data
  } else if (activeTab.value === 'materi') {
    const { data } = await api.get(`/lectures/${id}/materials`)
    materials.value = data
  } else if (activeTab.value === 'tugas') {
    const { data } = await api.get(`/lectures/${id}/assignments`)
    assignments.value = data
  } else if (activeTab.value === 'ujian') {
    const { data } = await api.get('/exams', { params: { class_id: id } })
    exams.value = data.data ?? data
  } else if (activeTab.value === 'pengumuman') {
    const { data } = await api.get(`/lectures/${id}/announcements`)
    announcements.value = data
  }
}

function switchTab(key: string) {
  activeTab.value = key
  loadTab()
}

// === Jurnal ===
const journalModal = ref(false)
const journalForm = ref({ meeting_number: 1, meeting_date: '', topic: '', description: '', learning_activity: '', status: 'PLANNED', latitude: null as number | null, longitude: null as number | null })
const journalPhoto = ref<File | null>(null)
const journalPhotoPreview = ref<string | null>(null)
const rpsPlans = ref<any[]>([])
const gettingLocation = ref(false)

// Load rencana dari RPS
async function loadRpsPlans() {
  try {
    const { data } = await api.get(`/lectures/${route.params.id}/rps-plans`)
    rpsPlans.value = data.plans ?? []
  } catch { rpsPlans.value = [] }
}

// Auto-fill dari RPS berdasarkan meeting_number
function fillFromRps() {
  const plan = rpsPlans.value.find((p: any) => p.meeting_number === journalForm.value.meeting_number)
  if (plan) {
    journalForm.value.topic = plan.topic || journalForm.value.topic
    journalForm.value.description = plan.description || journalForm.value.description
    journalForm.value.learning_activity = plan.learning_activity || journalForm.value.learning_activity
  }
}

// Ambil lokasi GPS
function getLocation() {
  if (!navigator.geolocation) { toast.error('Geolocation tidak didukung browser.'); return }
  gettingLocation.value = true
  navigator.geolocation.getCurrentPosition(
    (pos) => {
      journalForm.value.latitude = pos.coords.latitude
      journalForm.value.longitude = pos.coords.longitude
      gettingLocation.value = false
      toast.success('Lokasi berhasil diambil.')
    },
    (err) => {
      gettingLocation.value = false
      toast.error('Gagal ambil lokasi: ' + err.message)
    },
    { enableHighAccuracy: true, timeout: 10000 }
  )
}

// Handle photo
const cameraStream = ref<MediaStream | null>(null)
const videoRef = ref<HTMLVideoElement | null>(null)
const showCamera = ref(false)

function onJournalPhoto(e: Event) {
  const file = (e.target as HTMLInputElement).files?.[0]
  if (!file) return
  journalPhoto.value = file
  journalPhotoPreview.value = URL.createObjectURL(file)
}

async function openCamera() {
  try {
    const stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } })
    cameraStream.value = stream
    showCamera.value = true
    // Tunggu DOM update lalu assign stream ke video
    setTimeout(() => {
      if (videoRef.value) {
        videoRef.value.srcObject = stream
        videoRef.value.play()
      }
    }, 100)
  } catch (err: any) {
    toast.error('Tidak bisa mengakses kamera: ' + (err.message || 'Permission denied'))
  }
}

function capturePhoto() {
  if (!videoRef.value) return
  const canvas = document.createElement('canvas')
  canvas.width = videoRef.value.videoWidth
  canvas.height = videoRef.value.videoHeight
  canvas.getContext('2d')?.drawImage(videoRef.value, 0, 0)
  canvas.toBlob((blob) => {
    if (!blob) return
    const file = new File([blob], `foto-${Date.now()}.jpg`, { type: 'image/jpeg' })
    journalPhoto.value = file
    journalPhotoPreview.value = URL.createObjectURL(file)
    closeCamera()
  }, 'image/jpeg', 0.85)
}

function closeCamera() {
  if (cameraStream.value) {
    cameraStream.value.getTracks().forEach(t => t.stop())
    cameraStream.value = null
  }
  showCamera.value = false
}

async function deleteItem(type: 'journal' | 'material' | 'assignment' | 'announcement', id: number) {
  const labels: Record<string, string> = {
    journal: 'jurnal pertemuan', material: 'materi', assignment: 'tugas', announcement: 'pengumuman',
  }
  if (!confirm(`Hapus ${labels[type]} ini? Data tidak dapat dikembalikan.`)) return
  const urls: Record<string, string> = {
    journal: `/lectures/journals/${id}`,
    material: `/lectures/materials/${id}`,
    assignment: `/lectures/assignments/${id}`,
    announcement: `/lectures/announcements/${id}`,
  }
  try {
    await api.delete(urls[type])
    toast.success(`${labels[type].charAt(0).toUpperCase() + labels[type].slice(1)} berhasil dihapus.`)
    loadTab()
  } catch (e: any) {
    toast.error(e?.response?.data?.message ?? 'Gagal menghapus.')
  }
}

// === Edit items ===
const editModal = ref(false)
const editType = ref<'journal' | 'material' | 'assignment' | 'announcement' | null>(null)
const editId = ref<number | null>(null)
const editForm = ref<any>({})
const savingEdit = ref(false)

function openEdit(type: 'journal' | 'material' | 'assignment' | 'announcement', item: any) {
  editType.value = type
  editId.value = item.id
  if (type === 'journal') {
    editForm.value = { meeting_number: item.meeting_number, meeting_date: item.meeting_date, topic: item.topic, description: item.description ?? '', learning_activity: item.learning_activity ?? '', status: item.status }
  } else if (type === 'material') {
    editForm.value = { title: item.title, description: item.description ?? '', file_url: item.file_url ?? '' }
  } else if (type === 'assignment') {
    editForm.value = { title: item.title, description: item.description ?? '', instructions: item.instructions ?? '', due_date: item.due_date ? item.due_date.slice(0, 16) : '', max_score: item.max_score ?? 100 }
  } else if (type === 'announcement') {
    editForm.value = { title: item.title, content: item.content }
  }
  editModal.value = true
}

async function saveEdit() {
  if (!editId.value || !editType.value) return
  savingEdit.value = true
  const urls: Record<string, string> = {
    journal: `/lectures/journals/${editId.value}`,
    material: `/lectures/materials/${editId.value}`,
    assignment: `/lectures/assignments/${editId.value}`,
    announcement: `/lectures/announcements/${editId.value}`,
  }
  try {
    await api.put(urls[editType.value], editForm.value)
    toast.success('Berhasil diupdate.')
    editModal.value = false
    loadTab()
  } catch (e: any) {
    toast.error(e?.response?.data?.message ?? 'Gagal mengupdate.')
  } finally { savingEdit.value = false }
}
async function saveJournal() {
  try {
    const fd = new FormData()
    fd.append('meeting_number', String(journalForm.value.meeting_number))
    fd.append('meeting_date', journalForm.value.meeting_date)
    fd.append('topic', journalForm.value.topic)
    fd.append('description', journalForm.value.description || '')
    fd.append('learning_activity', journalForm.value.learning_activity || '')
    fd.append('status', journalForm.value.status)
    if (journalForm.value.latitude) fd.append('latitude', String(journalForm.value.latitude))
    if (journalForm.value.longitude) fd.append('longitude', String(journalForm.value.longitude))
    if (journalPhoto.value) fd.append('photo', journalPhoto.value)

    await api.post(`/lectures/${route.params.id}/journals`, fd, { headers: { 'Content-Type': 'multipart/form-data' } })
    toast.success('Jurnal berhasil disimpan.')
    journalModal.value = false
    journalPhoto.value = null
    journalPhotoPreview.value = null
    loadTab()
  } catch (e: any) { toast.error(e?.response?.data?.message ?? 'Gagal.') }
}

function openJournalModal() {
  journalForm.value = { meeting_number: (journals.value?.length ?? 0) + 1, meeting_date: new Date().toISOString().slice(0, 10), topic: '', description: '', learning_activity: '', status: 'PLANNED', latitude: null, longitude: null }
  journalPhoto.value = null
  journalPhotoPreview.value = null
  loadRpsPlans()
  journalModal.value = true
}

// === Materi ===
const materialModal = ref(false)
const materialForm = ref({ title: '', description: '', file_url: '' })

async function saveMaterial() {
  try {
    await api.post(`/lectures/${route.params.id}/materials`, materialForm.value)
    toast.success('Materi berhasil ditambahkan.')
    materialModal.value = false
    materialForm.value = { title: '', description: '', file_url: '' }
    loadTab()
  } catch (e: any) { toast.error(e?.response?.data?.message ?? 'Gagal.') }
}

// === Tugas ===
const assignmentModal = ref(false)
const assignmentForm = ref({ title: '', description: '', instructions: '', type: 'INDIVIDU', due_date: '', max_score: 100 })

async function saveAssignment() {
  try {
    await api.post(`/lectures/${route.params.id}/assignments`, assignmentForm.value)
    toast.success('Tugas berhasil dibuat.')
    assignmentModal.value = false
    loadTab()
  } catch (e: any) { toast.error(e?.response?.data?.message ?? 'Gagal.') }
}

// === Pengumuman ===
const annModal = ref(false)
const annForm = ref({ title: '', content: '' })

async function saveAnnouncement() {
  try {
    await api.post(`/lectures/${route.params.id}/announcements`, annForm.value)
    toast.success('Pengumuman berhasil dibuat.')
    annModal.value = false
    annForm.value = { title: '', content: '' }
    loadTab()
  } catch (e: any) { toast.error(e?.response?.data?.message ?? 'Gagal.') }
}

// === Ujian ===
const examModal = ref(false)
const examEditId = ref<number | null>(null)
const examForm = ref({ title: '', type: 'UTS', description: '', start_time: '', end_time: '', duration_minutes: 60, is_online: true, shuffle_questions: true, shuffle_options: true, show_score: false })
const examTypes = ['UTS', 'UAS', 'QUIZ', 'TUGAS_BESAR']
const savingExam = ref(false)

function openCreateExam() {
  examEditId.value = null
  examForm.value = { title: '', type: 'UTS', description: '', start_time: '', end_time: '', duration_minutes: 60, is_online: true, shuffle_questions: true, shuffle_options: true, show_score: false }
  examModal.value = true
}

function openEditExam(exam: any) {
  examEditId.value = exam.id
  examForm.value = {
    title: exam.title ?? '',
    type: exam.type ?? 'UTS',
    description: exam.description ?? '',
    start_time: toLocalDatetime(exam.start_time),
    end_time: toLocalDatetime(exam.end_time),
    duration_minutes: exam.duration_minutes ?? 60,
    is_online: exam.is_online ?? true,
    shuffle_questions: exam.shuffle_questions ?? true,
    shuffle_options: exam.shuffle_options ?? true,
    show_score: exam.show_score ?? false,
  }
  examModal.value = true
}

async function saveExam() {
  savingExam.value = true
  try {
    // Konversi datetime-local ke ISO dengan offset timezone lokal
    const payload = {
      ...examForm.value,
      start_time: localDatetimeToISO(examForm.value.start_time),
      end_time: localDatetimeToISO(examForm.value.end_time),
    }
    if (examEditId.value) {
      await api.put(`/exams/${examEditId.value}`, payload)
      toast.success('Ujian berhasil diupdate.')
    } else {
      await api.post('/exams', { ...payload, class_id: route.params.id })
      toast.success('Ujian berhasil dibuat.')
    }
    examModal.value = false
    examForm.value = { title: '', type: 'UTS', description: '', start_time: '', end_time: '', duration_minutes: 60, is_online: true, shuffle_questions: true, shuffle_options: true, show_score: false }
    examEditId.value = null
    loadTab()
  } catch (e: any) { toast.error(e?.response?.data?.message ?? 'Gagal.') }
  finally { savingExam.value = false }
}

async function deleteExam(exam: any) {
  if (!confirm(`Hapus ujian "${exam.title}"?`)) return
  try { await api.delete(`/exams/${exam.id}`); toast.success('Ujian dihapus.'); loadTab() }
  catch (e: any) { toast.error(e?.response?.data?.message ?? 'Gagal.') }
}

async function publishExam(exam: any) {
  try { await api.put(`/exams/${exam.id}`, { status: 'PUBLISHED' }); toast.success('Ujian dipublish.'); loadTab() }
  catch (e: any) { toast.error(e?.response?.data?.message ?? 'Gagal.') }
}

const examStatusColor: Record<string, string> = { DRAFT: 'bg-gray-100 text-gray-600', PUBLISHED: 'bg-green-100 text-green-700', ONGOING: 'bg-blue-100 text-blue-700', FINISHED: 'bg-purple-100 text-purple-700' }

/** Konversi ISO datetime string dari server (UTC) ke format datetime-local input (lokal) */
function toLocalDatetime(dt: string | null): string {
  if (!dt) return ''
  const d = new Date(dt)
  if (isNaN(d.getTime())) return ''
  const pad = (n: number) => String(n).padStart(2, '0')
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`
}

/** Format datetime untuk tampilan (bukan input) */
function fmtDatetime(dt: string | null): string {
  if (!dt) return '-'
  return new Date(dt).toLocaleString('id-ID', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })
}

/** Konversi datetime-local value (tanpa timezone) ke ISO string dengan offset lokal browser */
function localDatetimeToISO(val: string): string {
  if (!val) return ''
  // Dapatkan offset timezone lokal dalam menit, konversi ke ±HH:MM
  const d = new Date(val)
  const offset = -d.getTimezoneOffset()
  const sign = offset >= 0 ? '+' : '-'
  const hh = String(Math.floor(Math.abs(offset) / 60)).padStart(2, '0')
  const mm = String(Math.abs(offset) % 60).padStart(2, '0')
  return `${val}:00${sign}${hh}:${mm}`
}
</script>

<template>
  <div v-if="loading" class="flex items-center justify-center h-64"><p class="text-gray-400">Memuat...</p></div>

  <div v-else-if="classData" class="space-y-5 max-w-5xl mx-auto">
    <!-- Header -->
    <div class="flex items-start gap-3">
      <button class="p-1.5 rounded-lg text-gray-500 hover:bg-gray-100" @click="router.back()"><ArrowLeftIcon class="w-5 h-5" /></button>
      <div class="flex-1">
        <h1 class="text-lg font-bold text-gray-900">{{ classData.course?.name }}</h1>
        <p class="text-sm text-gray-500">{{ classData.name }} · {{ classData.course?.code }} · {{ classData.semester?.name }}</p>
      </div>
      <span class="text-sm text-gray-500">{{ classData.members_count ?? 0 }} mahasiswa</span>
    </div>

    <!-- Tabs -->
    <div class="flex gap-1 border-b border-gray-200 overflow-x-auto">
      <button v-for="t in tabs" :key="t.key"
        :class="['px-4 py-2.5 text-sm font-medium border-b-2 -mb-px transition-colors whitespace-nowrap',
          activeTab === t.key ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700']"
        @click="switchTab(t.key)">
        {{ t.label }}
      </button>
    </div>

    <!-- TAB: Jurnal -->
    <div v-if="activeTab === 'jurnal'" class="space-y-4">
      <div class="flex justify-end">
        <button v-if="!auth.hasRole('MAHASISWA')" class="flex items-center gap-1.5 px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-medium rounded-lg" @click="openJournalModal">
          <PlusIcon class="w-3.5 h-3.5" /> Tambah Pertemuan
        </button>
      </div>
      <div v-if="!journals.length" class="text-center py-8 text-gray-400 text-sm">Belum ada jurnal pertemuan.</div>
      <div v-else class="space-y-2">
        <div v-for="j in journals" :key="j.id" class="flex items-center gap-4 p-4 bg-white rounded-xl border border-gray-200 hover:border-blue-200">
          <span class="w-9 h-9 rounded-full bg-blue-100 text-blue-700 flex items-center justify-center font-bold text-sm shrink-0 cursor-pointer" @click="router.push(`/perkuliahan/${route.params.id}/presensi/${j.id}`)">{{ j.meeting_number }}</span>
          <div class="flex-1 min-w-0 cursor-pointer" @click="router.push(`/perkuliahan/${route.params.id}/presensi/${j.id}`)">
            <p class="font-medium text-gray-900 text-sm">{{ j.topic }}</p>
            <p class="text-xs text-gray-500">{{ j.meeting_date }} · {{ j.attendances_count ?? 0 }} hadir</p>
          </div>
          <span :class="['px-2 py-0.5 rounded-full text-xs font-medium',
            j.status === 'COMPLETED' ? 'bg-green-100 text-green-700' : j.status === 'CANCELLED' ? 'bg-red-100 text-red-600' : 'bg-gray-100 text-gray-600']">
            {{ j.status }}
          </span>
          <button v-if="!auth.hasRole('MAHASISWA')" class="p-1.5 rounded-lg text-blue-500 hover:bg-blue-50 shrink-0" title="Edit" @click.stop="openEdit('journal', j)">
            <PencilIcon class="w-4 h-4" />
          </button>
          <button v-if="!auth.hasRole('MAHASISWA')" class="p-1.5 rounded-lg text-red-400 hover:bg-red-50 hover:text-red-600 shrink-0" title="Hapus" @click.stop="deleteItem('journal', j.id)">
            <TrashIcon class="w-4 h-4" />
          </button>        </div>
      </div>
    </div>

    <!-- TAB: Materi -->
    <div v-if="activeTab === 'materi'" class="space-y-4">
      <div class="flex justify-end">
        <button v-if="!auth.hasRole('MAHASISWA')" class="flex items-center gap-1.5 px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-medium rounded-lg" @click="materialModal = true">
          <PlusIcon class="w-3.5 h-3.5" /> Upload Materi
        </button>
      </div>
      <div v-if="!materials.length" class="text-center py-8 text-gray-400 text-sm">Belum ada materi.</div>
      <div v-else class="space-y-2">
        <div v-for="m in materials" :key="m.id" class="flex items-center gap-3 p-3 bg-white rounded-lg border border-gray-200">
          <BookOpenIcon class="w-5 h-5 text-blue-500 shrink-0" />
          <div class="flex-1">
            <p class="font-medium text-gray-900 text-sm">{{ m.title }}</p>
            <p v-if="m.description" class="text-xs text-gray-500">{{ m.description }}</p>
          </div>
          <a v-if="m.file_url" :href="m.file_url" target="_blank" class="text-xs text-blue-600 underline shrink-0">Buka</a>
          <button v-if="!auth.hasRole('MAHASISWA')" class="p-1.5 rounded-lg text-blue-500 hover:bg-blue-50 shrink-0" title="Edit" @click="openEdit('material', m)">
            <PencilIcon class="w-4 h-4" />
          </button>
          <button v-if="!auth.hasRole('MAHASISWA')" class="p-1.5 rounded-lg text-red-400 hover:bg-red-50 hover:text-red-600 shrink-0" title="Hapus" @click="deleteItem('material', m.id)">
            <TrashIcon class="w-4 h-4" />
          </button>
        </div>
      </div>
    </div>

    <!-- TAB: Tugas -->
    <div v-if="activeTab === 'tugas'" class="space-y-4">
      <div class="flex justify-end">
        <button v-if="!auth.hasRole('MAHASISWA')" class="flex items-center gap-1.5 px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-medium rounded-lg" @click="assignmentModal = true">
          <PlusIcon class="w-3.5 h-3.5" /> Buat Tugas
        </button>
      </div>
      <div v-if="!assignments.length" class="text-center py-8 text-gray-400 text-sm">Belum ada tugas.</div>
      <div v-else class="space-y-2">
        <div v-for="a in assignments" :key="a.id" class="p-4 bg-white rounded-xl border border-gray-200">
          <div class="flex items-center justify-between">
            <p class="font-medium text-gray-900 text-sm">{{ a.title }}</p>
            <div class="flex items-center gap-2">
              <span class="text-xs text-gray-400">{{ a.submissions_count ?? 0 }} dikumpulkan</span>
              <button v-if="!auth.hasRole('MAHASISWA')" class="p-1.5 rounded-lg text-blue-500 hover:bg-blue-50" title="Edit" @click="openEdit('assignment', a)">
                <PencilIcon class="w-4 h-4" />
              </button>
              <button v-if="!auth.hasRole('MAHASISWA')" class="p-1.5 rounded-lg text-red-400 hover:bg-red-50 hover:text-red-600" title="Hapus" @click="deleteItem('assignment', a.id)">
                <TrashIcon class="w-4 h-4" />
              </button>
            </div>
          </div>
          <p v-if="a.due_date" class="text-xs text-red-500 mt-1">Deadline: {{ new Date(a.due_date).toLocaleString('id-ID') }}</p>
        </div>
      </div>
    </div>

    <!-- TAB: Pengumuman -->
    <div v-if="activeTab === 'pengumuman'" class="space-y-4">
      <div class="flex justify-end">
        <button v-if="!auth.hasRole('MAHASISWA')" class="flex items-center gap-1.5 px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-medium rounded-lg" @click="annModal = true">
          <PlusIcon class="w-3.5 h-3.5" /> Buat Pengumuman
        </button>
      </div>
      <div v-if="!announcements.length" class="text-center py-8 text-gray-400 text-sm">Belum ada pengumuman.</div>
      <div v-else class="space-y-2">
        <div v-for="a in announcements" :key="a.id" class="p-4 bg-white rounded-xl border border-gray-200">
          <div class="flex items-start justify-between">
            <div class="flex-1">
              <p class="font-semibold text-gray-900 text-sm">{{ a.title }}</p>
              <p class="text-sm text-gray-700 mt-1">{{ a.content }}</p>
              <p class="text-xs text-gray-400 mt-2">{{ a.user?.name }} · {{ new Date(a.created_at).toLocaleDateString('id-ID') }}</p>
            </div>
            <button v-if="!auth.hasRole('MAHASISWA')" class="p-1.5 rounded-lg text-blue-500 hover:bg-blue-50 shrink-0 ml-1" title="Edit" @click="openEdit('announcement', a)">
              <PencilIcon class="w-4 h-4" />
            </button>
            <button v-if="!auth.hasRole('MAHASISWA')" class="p-1.5 rounded-lg text-red-400 hover:bg-red-50 hover:text-red-600 shrink-0 ml-2" title="Hapus" @click="deleteItem('announcement', a.id)">
              <TrashIcon class="w-4 h-4" />
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- TAB: Ujian -->
    <div v-if="activeTab === 'ujian'" class="space-y-4">
      <div class="flex justify-end">
        <button v-if="!auth.hasRole('MAHASISWA')" class="flex items-center gap-1.5 px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-medium rounded-lg" @click="openCreateExam">
          <PlusIcon class="w-3.5 h-3.5" /> Buat Ujian
        </button>
      </div>
      <div v-if="!exams.length" class="text-center py-8 text-gray-400 text-sm">Belum ada ujian.</div>
      <div v-else class="space-y-3">
        <div v-for="e in exams" :key="e.id" class="p-4 bg-white rounded-xl border border-gray-200 hover:border-blue-200 transition-colors">
          <div class="flex items-start justify-between">
            <!-- Info ujian — klik beda tergantung role -->
            <div class="flex-1 cursor-pointer"
              @click="auth.hasRole('MAHASISWA') ? router.push(`/ujian/${e.id}/take`) : router.push(`/ujian/${e.id}`)">
              <div class="flex items-center gap-2">
                <span class="text-xs font-semibold px-2 py-0.5 rounded bg-indigo-100 text-indigo-700">{{ e.type }}</span>
                <span :class="['px-2 py-0.5 rounded-full text-xs font-medium', examStatusColor[e.status] ?? 'bg-gray-100 text-gray-600']">{{ e.status }}</span>
                <span v-if="e.is_online" class="text-xs text-green-600 font-medium">● Online</span>
              </div>
              <h3 class="mt-1.5 font-semibold text-gray-900 text-sm">{{ e.title }}</h3>
              <div class="flex items-center gap-4 mt-1 text-xs text-gray-500">
                <span v-if="e.duration_minutes">{{ e.duration_minutes }} menit</span>
                <span v-if="e.start_time">{{ fmtDatetime(e.start_time) }}</span>
                <span v-if="e.questions_count !== undefined">{{ e.questions_count }} soal</span>
                <span v-if="e.total_score" class="text-blue-600 font-medium">Total: {{ e.total_score }} poin</span>
              </div>
            </div>

            <!-- Tombol aksi -->
            <div class="flex items-center gap-1 shrink-0">
              <!-- Mahasiswa: tombol Kerjakan -->
              <button
                v-if="auth.hasRole('MAHASISWA') && (e.status === 'PUBLISHED' || e.status === 'ONGOING')"
                class="px-3 py-1.5 text-xs bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg"
                @click="router.push(`/ujian/${e.id}/take`)">
                Kerjakan
              </button>
              <span
                v-else-if="auth.hasRole('MAHASISWA') && e.status === 'FINISHED'"
                class="px-3 py-1.5 text-xs bg-gray-100 text-gray-500 rounded-lg">
                Selesai
              </span>

              <!-- Dosen: Publish, Edit, Hapus -->
              <button v-if="!auth.hasRole('MAHASISWA') && e.status === 'DRAFT'" class="px-2 py-1 text-xs bg-green-600 hover:bg-green-700 text-white rounded" @click="publishExam(e)">Publish</button>
              <button v-if="!auth.hasRole('MAHASISWA')" class="p-1.5 rounded-lg text-blue-500 hover:bg-blue-50" title="Edit" @click="openEditExam(e)"><PencilIcon class="w-4 h-4" /></button>
              <button v-if="!auth.hasRole('MAHASISWA') && e.status === 'DRAFT'" class="p-1.5 rounded-lg text-red-500 hover:bg-red-50" @click="deleteExam(e)"><TrashIcon class="w-4 h-4" /></button>
            </div>
          </div>

          <!-- Token: hanya tampil untuk dosen -->
          <div v-if="!auth.hasRole('MAHASISWA') && e.token" class="mt-2 pt-2 border-t border-gray-100 flex items-center gap-2">
            <span class="text-xs text-gray-400">Token:</span>
            <span class="text-xs font-mono font-bold text-gray-700 bg-gray-100 px-1.5 py-0.5 rounded">{{ e.token }}</span>
          </div>

          <!-- Info tambahan untuk mahasiswa jika ujian sudah selesai -->
          <div v-if="auth.hasRole('MAHASISWA') && e.status === 'FINISHED'" class="mt-2 pt-2 border-t border-gray-100">
            <span class="text-xs text-gray-400">Ujian telah berakhir.</span>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Modal Jurnal -->
  <BaseModal :open="journalModal" title="Tambah Pertemuan" size="xl" @close="journalModal = false">
    <form class="space-y-3" @submit.prevent="saveJournal">
      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="text-xs font-medium text-gray-700">Pertemuan ke</label>
          <input v-model.number="journalForm.meeting_number" type="number" min="1" max="16" class="w-full mt-1 px-3 py-2 border rounded-lg text-sm" @change="fillFromRps" />
        </div>
        <div><label class="text-xs font-medium text-gray-700">Tanggal</label><input v-model="journalForm.meeting_date" type="date" required class="w-full mt-1 px-3 py-2 border rounded-lg text-sm" /></div>
      </div>

      <!-- Auto-fill info -->
      <div v-if="rpsPlans.length" class="bg-blue-50 border border-blue-200 rounded-lg p-2">
        <p class="text-xs text-blue-700">📋 Data otomatis diambil dari RPS. Ubah nomor pertemuan untuk auto-fill.</p>
      </div>

      <div><label class="text-xs font-medium text-gray-700">Topik / Sub-CPMK</label><input v-model="journalForm.topic" required class="w-full mt-1 px-3 py-2 border rounded-lg text-sm" /></div>
      <div><label class="text-xs font-medium text-gray-700">Bahan Kajian / Deskripsi</label><textarea v-model="journalForm.description" rows="2" class="w-full mt-1 px-3 py-2 border rounded-lg text-sm" /></div>
      <div><label class="text-xs font-medium text-gray-700">Aktivitas Pembelajaran / Metode</label><textarea v-model="journalForm.learning_activity" rows="2" class="w-full mt-1 px-3 py-2 border rounded-lg text-sm" /></div>

      <!-- Geotagging -->
      <div class="border border-gray-200 rounded-lg p-3 space-y-2">
        <div class="flex items-center justify-between">
          <label class="text-xs font-medium text-gray-700">📍 Geotagging Lokasi</label>
          <button type="button" :disabled="gettingLocation" class="px-3 py-1.5 bg-green-600 hover:bg-green-700 disabled:bg-green-400 text-white text-xs font-medium rounded-lg" @click="getLocation">
            {{ gettingLocation ? 'Mengambil...' : 'Ambil Lokasi' }}
          </button>
        </div>
        <div v-if="journalForm.latitude" class="text-xs text-gray-600 bg-gray-50 rounded p-2 font-mono">
          Lat: {{ journalForm.latitude?.toFixed(7) }} | Lng: {{ journalForm.longitude?.toFixed(7) }}
        </div>
      </div>

      <!-- Upload Foto -->
      <div class="border border-gray-200 rounded-lg p-3 space-y-2">
        <label class="text-xs font-medium text-gray-700">📷 Foto Dokumentasi</label>

        <!-- Camera Preview -->
        <div v-if="showCamera" class="space-y-2">
          <div class="relative bg-black rounded-lg overflow-hidden" style="max-height: 240px;">
            <video ref="videoRef" autoplay playsinline class="w-full" style="max-height: 240px; object-fit: cover;"></video>
          </div>
          <div class="flex gap-2">
            <button type="button" class="flex-1 px-3 py-2 bg-green-600 hover:bg-green-700 text-white text-xs font-medium rounded-lg" @click="capturePhoto">✓ Ambil</button>
            <button type="button" class="px-3 py-2 bg-gray-200 hover:bg-gray-300 text-gray-700 text-xs rounded-lg" @click="closeCamera">Batal</button>
          </div>
        </div>

        <!-- Photo result / buttons -->
        <div v-else class="flex items-center gap-3">
          <div v-if="journalPhotoPreview" class="w-20 h-20 rounded-lg border overflow-hidden shrink-0">
            <img :src="journalPhotoPreview" class="w-full h-full object-cover" />
          </div>
          <div class="flex flex-col gap-2">
            <div class="flex gap-2">
              <button type="button" class="px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-medium rounded-lg inline-flex items-center gap-1" @click="openCamera">
                📷 Buka Kamera
              </button>
              <label class="px-3 py-1.5 bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-medium rounded-lg cursor-pointer border border-gray-300 inline-flex items-center gap-1">
                📁 Pilih File
                <input type="file" accept="image/*" class="hidden" @change="onJournalPhoto" />
              </label>
            </div>
            <p class="text-[10px] text-gray-400">"Buka Kamera" untuk foto langsung. "Pilih File" untuk upload dari galeri. Max 5MB.</p>
          </div>
        </div>
      </div>
    </form>
    <template #footer>
      <button class="px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded-lg" @click="journalModal = false">Batal</button>
      <button class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg" @click="saveJournal">Simpan</button>
    </template>
  </BaseModal>

  <!-- Modal Materi -->
  <BaseModal :open="materialModal" title="Upload Materi" @close="materialModal = false">
    <form class="space-y-3" @submit.prevent="saveMaterial">
      <div><label class="text-xs font-medium text-gray-700">Judul</label><input v-model="materialForm.title" required class="w-full mt-1 px-3 py-2 border rounded-lg text-sm" /></div>
      <div><label class="text-xs font-medium text-gray-700">Deskripsi</label><textarea v-model="materialForm.description" rows="2" class="w-full mt-1 px-3 py-2 border rounded-lg text-sm" /></div>
      <div><label class="text-xs font-medium text-gray-700">Link File (Google Drive)</label><input v-model="materialForm.file_url" placeholder="https://drive.google.com/..." class="w-full mt-1 px-3 py-2 border rounded-lg text-sm" /></div>
    </form>
    <template #footer>
      <button class="px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded-lg" @click="materialModal = false">Batal</button>
      <button class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg" @click="saveMaterial">Simpan</button>
    </template>
  </BaseModal>

  <!-- Modal Tugas -->
  <BaseModal :open="assignmentModal" title="Buat Tugas" @close="assignmentModal = false">
    <form class="space-y-3" @submit.prevent="saveAssignment">
      <div><label class="text-xs font-medium text-gray-700">Judul</label><input v-model="assignmentForm.title" required class="w-full mt-1 px-3 py-2 border rounded-lg text-sm" /></div>
      <div><label class="text-xs font-medium text-gray-700">Deskripsi</label><textarea v-model="assignmentForm.description" rows="2" class="w-full mt-1 px-3 py-2 border rounded-lg text-sm" /></div>
      <div><label class="text-xs font-medium text-gray-700">Instruksi</label><textarea v-model="assignmentForm.instructions" rows="2" class="w-full mt-1 px-3 py-2 border rounded-lg text-sm" /></div>
      <div class="grid grid-cols-2 gap-3">
        <div><label class="text-xs font-medium text-gray-700">Tipe</label><select v-model="assignmentForm.type" class="w-full mt-1 px-3 py-2 border rounded-lg text-sm"><option value="INDIVIDU">Individu</option><option value="KELOMPOK">Kelompok</option></select></div>
        <div><label class="text-xs font-medium text-gray-700">Deadline</label><input v-model="assignmentForm.due_date" type="datetime-local" class="w-full mt-1 px-3 py-2 border rounded-lg text-sm" /></div>
      </div>
    </form>
    <template #footer>
      <button class="px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded-lg" @click="assignmentModal = false">Batal</button>
      <button class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg" @click="saveAssignment">Simpan</button>
    </template>
  </BaseModal>

  <!-- Modal Pengumuman -->
  <BaseModal :open="annModal" title="Buat Pengumuman" @close="annModal = false">
    <form class="space-y-3" @submit.prevent="saveAnnouncement">
      <div><label class="text-xs font-medium text-gray-700">Judul</label><input v-model="annForm.title" required class="w-full mt-1 px-3 py-2 border rounded-lg text-sm" /></div>
      <div><label class="text-xs font-medium text-gray-700">Isi</label><textarea v-model="annForm.content" required rows="4" class="w-full mt-1 px-3 py-2 border rounded-lg text-sm" /></div>
    </form>
    <template #footer>
      <button class="px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded-lg" @click="annModal = false">Batal</button>
      <button class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg" @click="saveAnnouncement">Simpan</button>
    </template>
  </BaseModal>

  <!-- Modal Ujian -->
  <BaseModal :open="examModal" :title="examEditId ? 'Edit Ujian' : 'Buat Ujian'" size="xl" @close="examModal = false">
    <form class="space-y-4" @submit.prevent="saveExam">
      <div class="grid grid-cols-2 gap-4">
        <div><label class="text-xs font-medium text-gray-700">Judul <span class="text-red-500">*</span></label><input v-model="examForm.title" required placeholder="UTS Hukum Perbankan Syariah" class="w-full mt-1 px-3 py-2 border rounded-lg text-sm" /></div>
        <div><label class="text-xs font-medium text-gray-700">Jenis <span class="text-red-500">*</span></label><select v-model="examForm.type" class="w-full mt-1 px-3 py-2 border rounded-lg text-sm"><option v-for="t in examTypes" :key="t" :value="t">{{ t }}</option></select></div>
      </div>
      <div><label class="text-xs font-medium text-gray-700">Deskripsi</label><textarea v-model="examForm.description" rows="2" class="w-full mt-1 px-3 py-2 border rounded-lg text-sm" /></div>
      <div class="grid grid-cols-3 gap-4">
        <div><label class="text-xs font-medium text-gray-700">Waktu Mulai</label><input v-model="examForm.start_time" type="datetime-local" class="w-full mt-1 px-3 py-2 border rounded-lg text-sm" /></div>
        <div><label class="text-xs font-medium text-gray-700">Waktu Selesai</label><input v-model="examForm.end_time" type="datetime-local" class="w-full mt-1 px-3 py-2 border rounded-lg text-sm" /></div>
        <div><label class="text-xs font-medium text-gray-700">Durasi (menit)</label><input v-model.number="examForm.duration_minutes" type="number" min="5" class="w-full mt-1 px-3 py-2 border rounded-lg text-sm" /></div>
      </div>
      <div class="flex flex-wrap items-center gap-5">
        <label class="flex items-center gap-2 text-sm text-gray-700"><input v-model="examForm.is_online" type="checkbox" class="rounded" /> Online</label>
        <label class="flex items-center gap-2 text-sm text-gray-700"><input v-model="examForm.shuffle_questions" type="checkbox" class="rounded" /> Acak Soal</label>
        <label class="flex items-center gap-2 text-sm text-gray-700"><input v-model="examForm.shuffle_options" type="checkbox" class="rounded" /> Acak Pilihan</label>
        <label class="flex items-center gap-2 text-sm text-gray-700"><input v-model="examForm.show_score" type="checkbox" class="rounded" /> Tampilkan Nilai</label>
      </div>
    </form>
    <template #footer>
      <button class="px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded-lg" @click="examModal = false">Batal</button>
      <button :disabled="savingExam" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 disabled:bg-blue-400 text-white text-sm font-medium rounded-lg" @click="saveExam">
        {{ savingExam ? 'Menyimpan...' : (examEditId ? 'Update' : 'Simpan') }}
      </button>
    </template>
  </BaseModal>

  <!-- Modal Edit Universal -->
  <BaseModal :open="editModal" :title="editType ? 'Edit ' + { journal: 'Jurnal', material: 'Materi', assignment: 'Tugas', announcement: 'Pengumuman' }[editType] : 'Edit'" @close="editModal = false">
    <form class="space-y-3" @submit.prevent="saveEdit">
      <!-- Journal fields -->
      <template v-if="editType === 'journal'">
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="text-xs font-medium text-gray-700">Pertemuan ke</label>
            <input v-model.number="editForm.meeting_number" type="number" min="1" max="16" class="w-full mt-1 px-3 py-2 border rounded-lg text-sm" />
          </div>
          <div>
            <label class="text-xs font-medium text-gray-700">Tanggal</label>
            <input v-model="editForm.meeting_date" type="date" class="w-full mt-1 px-3 py-2 border rounded-lg text-sm" />
          </div>
        </div>
        <div>
          <label class="text-xs font-medium text-gray-700">Topik</label>
          <input v-model="editForm.topic" class="w-full mt-1 px-3 py-2 border rounded-lg text-sm" />
        </div>
        <div>
          <label class="text-xs font-medium text-gray-700">Deskripsi</label>
          <textarea v-model="editForm.description" rows="2" class="w-full mt-1 px-3 py-2 border rounded-lg text-sm" />
        </div>
        <div>
          <label class="text-xs font-medium text-gray-700">Aktivitas Pembelajaran</label>
          <textarea v-model="editForm.learning_activity" rows="2" class="w-full mt-1 px-3 py-2 border rounded-lg text-sm" />
        </div>
        <div>
          <label class="text-xs font-medium text-gray-700">Status</label>
          <select v-model="editForm.status" class="w-full mt-1 px-3 py-2 border rounded-lg text-sm">
            <option value="PLANNED">PLANNED</option>
            <option value="COMPLETED">COMPLETED</option>
            <option value="CANCELLED">CANCELLED</option>
          </select>
        </div>
      </template>
      <!-- Material fields -->
      <template v-else-if="editType === 'material'">
        <div>
          <label class="text-xs font-medium text-gray-700">Judul</label>
          <input v-model="editForm.title" required class="w-full mt-1 px-3 py-2 border rounded-lg text-sm" />
        </div>
        <div>
          <label class="text-xs font-medium text-gray-700">Deskripsi</label>
          <textarea v-model="editForm.description" rows="2" class="w-full mt-1 px-3 py-2 border rounded-lg text-sm" />
        </div>
        <div>
          <label class="text-xs font-medium text-gray-700">Link File</label>
          <input v-model="editForm.file_url" placeholder="https://drive.google.com/..." class="w-full mt-1 px-3 py-2 border rounded-lg text-sm" />
        </div>
      </template>
      <!-- Assignment fields -->
      <template v-else-if="editType === 'assignment'">
        <div>
          <label class="text-xs font-medium text-gray-700">Judul</label>
          <input v-model="editForm.title" required class="w-full mt-1 px-3 py-2 border rounded-lg text-sm" />
        </div>
        <div>
          <label class="text-xs font-medium text-gray-700">Deskripsi</label>
          <textarea v-model="editForm.description" rows="2" class="w-full mt-1 px-3 py-2 border rounded-lg text-sm" />
        </div>
        <div>
          <label class="text-xs font-medium text-gray-700">Instruksi</label>
          <textarea v-model="editForm.instructions" rows="2" class="w-full mt-1 px-3 py-2 border rounded-lg text-sm" />
        </div>
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="text-xs font-medium text-gray-700">Deadline</label>
            <input v-model="editForm.due_date" type="datetime-local" class="w-full mt-1 px-3 py-2 border rounded-lg text-sm" />
          </div>
          <div>
            <label class="text-xs font-medium text-gray-700">Skor Maksimal</label>
            <input v-model.number="editForm.max_score" type="number" min="0" class="w-full mt-1 px-3 py-2 border rounded-lg text-sm" />
          </div>
        </div>
      </template>
      <!-- Announcement fields -->
      <template v-else-if="editType === 'announcement'">
        <div>
          <label class="text-xs font-medium text-gray-700">Judul</label>
          <input v-model="editForm.title" required class="w-full mt-1 px-3 py-2 border rounded-lg text-sm" />
        </div>
        <div>
          <label class="text-xs font-medium text-gray-700">Isi Pengumuman</label>
          <textarea v-model="editForm.content" required rows="4" class="w-full mt-1 px-3 py-2 border rounded-lg text-sm" />
        </div>
      </template>
    </form>
    <template #footer>
      <button class="px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded-lg" @click="editModal = false">Batal</button>
      <button :disabled="savingEdit" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 disabled:bg-blue-400 text-white text-sm font-medium rounded-lg" @click="saveEdit">
        {{ savingEdit ? 'Menyimpan...' : 'Simpan' }}
      </button>
    </template>
  </BaseModal>
</template>
