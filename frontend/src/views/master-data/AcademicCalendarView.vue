<script setup lang="ts">
import { ref, reactive, onMounted, computed } from 'vue'
import { PlusIcon, PencilIcon, TrashIcon, ArrowDownTrayIcon, ArrowUpTrayIcon, DocumentArrowDownIcon } from '@heroicons/vue/24/outline'
import { useAuthStore } from '@/stores/auth'
import { useToast } from 'vue-toastification'
import BaseModal from '@/components/ui/BaseModal.vue'
import api from '@/services/api'
import { cleanPayload, extractErrorMessage } from '@/composables/useCrud'

const auth = useAuthStore()
const isAdmin = computed(() => auth.hasRole('SUPER_ADMIN') || auth.hasRole('ADMIN_AKADEMIK'))

interface AcademicYear { id: number; name: string }
interface CalendarEvent {
  id: number; title: string; description: string; start_date: string
  end_date: string; category: string; color: string
  academic_year?: AcademicYear
}

const toast = useToast()
const events = ref<CalendarEvent[]>([])
const academicYears = ref<AcademicYear[]>([])
const loading = ref(false)
const filterYear = ref('')
const filterCategory = ref('')
const modalOpen = ref(false); const editingId = ref<number | null>(null); const saving = ref(false)

const form = reactive({
  academic_year_id: '', title: '', description: '',
  start_date: '', end_date: '', category: 'Akademik', color: '#3b82f6',
})

const categories = ['Akademik', 'UTS', 'UAS', 'Libur', 'KKN', 'Wisuda', 'Lainnya']
const categoryColor: Record<string, string> = {
  Akademik: 'bg-blue-100 text-blue-700', UTS: 'bg-orange-100 text-orange-700',
  UAS: 'bg-red-100 text-red-700', Libur: 'bg-green-100 text-green-700',
  KKN: 'bg-purple-100 text-purple-700', Wisuda: 'bg-yellow-100 text-yellow-700',
  Lainnya: 'bg-gray-100 text-gray-600',
}

const colorOptions = [
  { label: 'Biru', value: '#3b82f6' }, { label: 'Hijau', value: '#22c55e' },
  { label: 'Merah', value: '#ef4444' }, { label: 'Orange', value: '#f97316' },
  { label: 'Ungu', value: '#a855f7' }, { label: 'Kuning', value: '#eab308' },
  { label: 'Abu', value: '#6b7280' },
]

const filteredEvents = computed(() => {
  return events.value.filter(e => {
    if (filterCategory.value && e.category !== filterCategory.value) return false
    return true
  })
})

// Group events by month
const groupedEvents = computed(() => {
  const groups: Record<string, CalendarEvent[]> = {}
  filteredEvents.value.forEach(e => {
    const month = e.start_date.substring(0, 7)
    if (!groups[month]) groups[month] = []
    groups[month].push(e)
  })
  return groups
})

function formatMonth(ym: string) {
  const [y, m] = ym.split('-')
  const months = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des']
  if (!y || !m) return ym
  const month = months[Number.parseInt(m, 10) - 1]
  return month ? `${month} ${y}` : ym
}

onMounted(async () => {
  const { data } = await api.get('/academic-years/all')
  academicYears.value = data
  if (data.length) {
    filterYear.value = data.find((y: AcademicYear & { is_active: boolean }) => y.is_active)?.id ?? data[0].id
    load()
  }
})

async function load() {
  loading.value = true
  try {
    const { data } = await api.get('/academic-calendars', {
      params: { academic_year_id: filterYear.value, category: filterCategory.value },
    })
    events.value = data
  } catch { toast.error('Gagal memuat kalender.') } finally { loading.value = false }
}

function openCreate() {
  editingId.value = null
  Object.assign(form, { academic_year_id: filterYear.value || '', title: '', description: '', start_date: '', end_date: '', category: 'Akademik', color: '#3b82f6' })
  modalOpen.value = true
}

function openEdit(item: CalendarEvent) {
  editingId.value = item.id
  Object.assign(form, { academic_year_id: item.academic_year?.id ?? '', title: item.title, description: item.description ?? '', start_date: item.start_date, end_date: item.end_date ?? '', category: item.category, color: item.color })
  modalOpen.value = true
}

async function handleSave() {
  saving.value = true
  try {
    if (editingId.value) {
      const { data } = await api.put(`/academic-calendars/${editingId.value}`, cleanPayload(form))
      toast.success(data.message)
    } else {
      const { data } = await api.post('/academic-calendars', cleanPayload(form))
      toast.success(data.message)
    }
    modalOpen.value = false; load()
  } catch (err: any) {
    toast.error(extractErrorMessage(err))
  } finally { saving.value = false }
}

async function handleDelete(item: CalendarEvent) {
  if (!confirm(`Hapus event "${item.title}"?`)) return
  try {
    const { data } = await api.delete(`/academic-calendars/${item.id}`)
    toast.success(data.message); load()
  } catch (err: any) { toast.error(extractErrorMessage(err)) }
}

const exporting = ref(false)
const importing = ref(false)
const downloadingPdf = ref(false)
const fileInput = ref<HTMLInputElement | null>(null)

async function handleExport() {
  exporting.value = true
  try {
    const res = await api.get('/academic-calendars/export', { params: { academic_year_id: filterYear.value }, responseType: 'blob' })
    const url = URL.createObjectURL(new Blob([res.data]))
    const link = document.createElement('a')
    link.href = url; link.download = 'kalender-akademik.xlsx'
    document.body.appendChild(link); link.click(); link.remove()
    URL.revokeObjectURL(url)
  } catch { toast.error('Gagal export.') }
  finally { exporting.value = false }
}

async function handleImport(e: Event) {
  const file = (e.target as HTMLInputElement).files?.[0]
  if (!file) return
  importing.value = true
  try {
    const fd = new FormData(); fd.append('file', file)
    const { data } = await api.post('/academic-calendars/import', fd, { headers: { 'Content-Type': 'multipart/form-data' } })
    toast.success(data.message); load()
  } catch (err: any) { toast.error(extractErrorMessage(err)) }
  finally { importing.value = false; if (fileInput.value) fileInput.value.value = '' }
}

async function handleDownloadPdf() {
  downloadingPdf.value = true
  try {
    const res = await api.get('/academic-calendars/pdf', { params: { academic_year_id: filterYear.value }, responseType: 'blob' })
    const url = URL.createObjectURL(new Blob([res.data], { type: 'application/pdf' }))
    const link = document.createElement('a')
    link.href = url; link.download = 'kalender-akademik.pdf'
    document.body.appendChild(link); link.click(); link.remove()
    URL.revokeObjectURL(url)
  } catch { toast.error('Gagal download PDF.') }
  finally { downloadingPdf.value = false }
}

function formatDate(d: string) {
  if (!d) return ''
  return new Date(d).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' })
}
</script>

<template>
  <div class="space-y-5">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-xl font-bold text-gray-900">Kalender Akademik</h1>
        <p class="text-sm text-gray-500 mt-0.5">Jadwal dan event penting tahun akademik</p>
      </div>
      <div class="flex items-center gap-2">
        <!-- Download PDF (semua user) -->
        <button :disabled="downloadingPdf" class="flex items-center gap-1.5 px-3 py-2 text-sm font-medium text-red-700 bg-red-50 hover:bg-red-100 border border-red-200 rounded-lg" @click="handleDownloadPdf">
          <DocumentArrowDownIcon class="w-4 h-4" />
          <span>{{ downloadingPdf ? 'Menyiapkan...' : 'PDF' }}</span>
        </button>
        <!-- Export (semua user) -->
        <button :disabled="exporting" class="flex items-center gap-1.5 px-3 py-2 text-sm font-medium text-green-700 bg-green-50 hover:bg-green-100 border border-green-200 rounded-lg" @click="handleExport">
          <ArrowDownTrayIcon class="w-4 h-4" />
          <span>{{ exporting ? 'Mengunduh...' : 'Export' }}</span>
        </button>
        <!-- Import (admin only) -->
        <template v-if="isAdmin">
          <button :disabled="importing" class="flex items-center gap-1.5 px-3 py-2 text-sm font-medium text-blue-700 bg-blue-50 hover:bg-blue-100 border border-blue-200 rounded-lg" @click="fileInput?.click()">
            <ArrowUpTrayIcon class="w-4 h-4" />
            <span>{{ importing ? 'Mengimpor...' : 'Import' }}</span>
          </button>
          <input ref="fileInput" type="file" accept=".xlsx,.xls,.csv" class="hidden" @change="handleImport" />
          <!-- Tambah Event (admin only) -->
          <button class="flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg" @click="openCreate">
            <PlusIcon class="w-4 h-4" /> Tambah Event
          </button>
        </template>
      </div>
    </div>

    <div class="flex flex-wrap gap-3">
      <select v-model="filterYear" class="px-3.5 py-2 rounded-lg border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" @change="load()">
        <option value="">Semua Tahun</option>
        <option v-for="y in academicYears" :key="y.id" :value="y.id">{{ y.name }}</option>
      </select>
      <select v-model="filterCategory" class="px-3.5 py-2 rounded-lg border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" @change="load()">
        <option value="">Semua Kategori</option>
        <option v-for="c in categories" :key="c" :value="c">{{ c }}</option>
      </select>
    </div>

    <div v-if="loading" class="text-center py-12 text-gray-400">Memuat kalender...</div>

    <div v-else-if="Object.keys(groupedEvents).length === 0" class="bg-white rounded-xl border border-dashed border-gray-300 p-12 text-center text-gray-400">
      Belum ada event. Klik Tambah Event untuk mulai.
    </div>

    <div v-else class="space-y-4">
      <div v-for="(evts, month) in groupedEvents" :key="month" class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="px-5 py-3 bg-gray-50 border-b border-gray-200">
          <h3 class="font-semibold text-gray-700">{{ formatMonth(month as string) }}</h3>
        </div>
        <div class="divide-y divide-gray-100">
          <div v-for="evt in evts" :key="evt.id" class="flex items-center gap-4 px-5 py-3 hover:bg-gray-50 group">
            <div class="w-1 h-10 rounded-full shrink-0" :style="{ backgroundColor: evt.color }" />
            <div class="flex-1 min-w-0">
              <p class="font-medium text-gray-900 text-sm">{{ evt.title }}</p>
              <p class="text-xs text-gray-500 mt-0.5">
                {{ formatDate(evt.start_date) }}
                <span v-if="evt.end_date && evt.end_date !== evt.start_date"> – {{ formatDate(evt.end_date) }}</span>
              </p>
            </div>
            <span :class="['inline-flex px-2 py-0.5 rounded-full text-xs font-medium shrink-0', categoryColor[evt.category]]">{{ evt.category }}</span>
            <div v-if="isAdmin" class="flex gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
              <button class="p-1.5 rounded-lg text-blue-600 hover:bg-blue-50" @click="openEdit(evt)"><PencilIcon class="w-4 h-4" /></button>
              <button class="p-1.5 rounded-lg text-red-500 hover:bg-red-50" @click="handleDelete(evt)"><TrashIcon class="w-4 h-4" /></button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <BaseModal :open="modalOpen" :title="editingId ? 'Edit Event' : 'Tambah Event Kalender'" size="lg" @close="modalOpen = false">
    <form class="space-y-4" @submit.prevent="handleSave">
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Tahun Akademik <span class="text-red-500">*</span></label>
        <select v-model="form.academic_year_id" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
          <option value="">-- Pilih --</option>
          <option v-for="y in academicYears" :key="y.id" :value="y.id">{{ y.name }}</option>
        </select>
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Judul Event <span class="text-red-500">*</span></label>
        <input v-model="form.title" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
      </div>
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Kategori <span class="text-red-500">*</span></label>
          <select v-model="form.category" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            <option v-for="c in categories" :key="c" :value="c">{{ c }}</option>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Warna</label>
          <select v-model="form.color" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            <option v-for="c in colorOptions" :key="c.value" :value="c.value">{{ c.label }}</option>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Tanggal Mulai <span class="text-red-500">*</span></label>
          <input v-model="form.start_date" type="date" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Tanggal Selesai</label>
          <input v-model="form.end_date" type="date" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
        </div>
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Keterangan</label>
        <textarea v-model="form.description" rows="2" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
      </div>
    </form>
    <template #footer>
      <button class="px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded-lg" @click="modalOpen = false">Batal</button>
      <button :disabled="saving" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 disabled:bg-blue-400 text-white text-sm font-medium rounded-lg" @click="handleSave">{{ saving ? 'Menyimpan...' : 'Simpan' }}</button>
    </template>
  </BaseModal>
</template>
