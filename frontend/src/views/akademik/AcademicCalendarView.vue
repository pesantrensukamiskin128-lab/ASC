<script setup lang="ts">
import { ref, reactive, onMounted, computed } from 'vue'
import { useToast } from 'vue-toastification'
import { useAuthStore } from '@/stores/auth'
import { PlusIcon, PencilIcon, TrashIcon, ChevronLeftIcon, ChevronRightIcon } from '@heroicons/vue/24/outline'
import BaseModal from '@/components/ui/BaseModal.vue'
import api from '@/services/api'

const toast = useToast()
const auth = useAuthStore()
const isAdmin = auth.user?.roles?.includes('SUPER_ADMIN') || auth.user?.roles?.includes('ADMIN_AKADEMIK')

const events = ref<any[]>([])
const loading = ref(true)
const academicYears = ref<any[]>([])
const filterYear = ref('')
const filterCategory = ref('')
const viewMode = ref<'list' | 'calendar'>('calendar')

// Calendar navigation
const currentMonth = ref(new Date().getMonth())
const currentYear = ref(new Date().getFullYear())

// Modal
const modalOpen = ref(false)
const editingId = ref<number | null>(null)
const saving = ref(false)
const form = reactive({ academic_year_id: '', title: '', description: '', start_date: '', end_date: '', category: 'Akademik', color: '' })

const categories = ['Akademik', 'UTS', 'UAS', 'Libur', 'KKN', 'Wisuda', 'Lainnya']
const categoryColor: Record<string, string> = {
  Akademik: 'bg-blue-500', UTS: 'bg-orange-500', UAS: 'bg-red-500',
  Libur: 'bg-green-500', KKN: 'bg-purple-500', Wisuda: 'bg-emerald-500', Lainnya: 'bg-gray-500',
}
const categoryBadge: Record<string, string> = {
  Akademik: 'bg-blue-100 text-blue-700', UTS: 'bg-orange-100 text-orange-700', UAS: 'bg-red-100 text-red-700',
  Libur: 'bg-green-100 text-green-700', KKN: 'bg-purple-100 text-purple-700', Wisuda: 'bg-emerald-100 text-emerald-700', Lainnya: 'bg-gray-100 text-gray-700',
}

const monthNames = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember']
const dayNames = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab']

onMounted(async () => {
  const { data } = await api.get('/academic-years/all')
  academicYears.value = data
  load()
})

async function load() {
  loading.value = true
  try {
    const { data } = await api.get('/academic-calendars', { params: { academic_year_id: filterYear.value, category: filterCategory.value } })
    events.value = data
  } finally { loading.value = false }
}

// Calendar helpers
const calendarDays = computed(() => {
  const firstDay = new Date(currentYear.value, currentMonth.value, 1).getDay()
  const daysInMonth = new Date(currentYear.value, currentMonth.value + 1, 0).getDate()
  const days: { day: number; date: string; events: any[] }[] = []

  // Empty slots
  for (let i = 0; i < firstDay; i++) days.push({ day: 0, date: '', events: [] })

  // Days
  for (let d = 1; d <= daysInMonth; d++) {
    const dateStr = `${currentYear.value}-${String(currentMonth.value + 1).padStart(2, '0')}-${String(d).padStart(2, '0')}`
    const dayEvents = events.value.filter(e => {
      const start = e.start_date?.split('T')[0] ?? e.start_date
      const end = e.end_date?.split('T')[0] ?? e.end_date ?? start
      return dateStr >= start && dateStr <= end
    })
    days.push({ day: d, date: dateStr, events: dayEvents })
  }

  return days
})

const isToday = (date: string) => date === new Date().toISOString().split('T')[0]

function prevMonth() { if (currentMonth.value === 0) { currentMonth.value = 11; currentYear.value-- } else currentMonth.value-- }
function nextMonth() { if (currentMonth.value === 11) { currentMonth.value = 0; currentYear.value++ } else currentMonth.value++ }

function openCreate() {
  editingId.value = null
  Object.assign(form, { academic_year_id: filterYear.value || '', title: '', description: '', start_date: '', end_date: '', category: 'Akademik', color: '' })
  modalOpen.value = true
}

function openEdit(event: any) {
  editingId.value = event.id
  Object.assign(form, {
    academic_year_id: event.academic_year_id, title: event.title, description: event.description ?? '',
    start_date: event.start_date?.split('T')[0] ?? event.start_date,
    end_date: event.end_date?.split('T')[0] ?? event.end_date ?? '',
    category: event.category, color: event.color ?? '',
  })
  modalOpen.value = true
}

async function handleSave() {
  saving.value = true
  try {
    if (editingId.value) {
      await api.put(`/academic-calendars/${editingId.value}`, form)
      toast.success('Event berhasil diupdate.')
    } else {
      await api.post('/academic-calendars', form)
      toast.success('Event berhasil ditambahkan.')
    }
    modalOpen.value = false; load()
  } catch (e: any) { toast.error(e?.response?.data?.message ?? 'Gagal.') }
  finally { saving.value = false }
}

async function handleDelete(event: any) {
  if (!confirm(`Hapus "${event.title}"?`)) return
  try { await api.delete(`/academic-calendars/${event.id}`); toast.success('Dihapus.'); load() }
  catch (e: any) { toast.error(e?.response?.data?.message ?? 'Gagal.') }
}

function formatDate(d: string) { return d ? new Date(d).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' }) : '-' }
</script>

<template>
  <div class="space-y-5">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-xl font-bold text-gray-900">Kalender Akademik</h1>
        <p class="text-sm text-gray-500 mt-0.5">Jadwal kegiatan akademik sepanjang tahun</p>
      </div>
      <div class="flex items-center gap-2">
        <div class="flex rounded-lg border border-gray-300 overflow-hidden">
          <button :class="['px-3 py-1.5 text-xs font-medium', viewMode === 'calendar' ? 'bg-blue-600 text-white' : 'text-gray-600 hover:bg-gray-50']" @click="viewMode = 'calendar'">Kalender</button>
          <button :class="['px-3 py-1.5 text-xs font-medium', viewMode === 'list' ? 'bg-blue-600 text-white' : 'text-gray-600 hover:bg-gray-50']" @click="viewMode = 'list'">Daftar</button>
        </div>
        <button v-if="isAdmin" class="flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg" @click="openCreate">
          <PlusIcon class="w-4 h-4" /> Tambah Event
        </button>
      </div>
    </div>

    <!-- Filters -->
    <div class="flex flex-wrap gap-3">
      <select v-model="filterYear" class="px-3.5 py-2 rounded-lg border border-gray-300 text-sm" @change="load()">
        <option value="">Semua Tahun</option>
        <option v-for="y in academicYears" :key="y.id" :value="y.id">{{ y.name }}</option>
      </select>
      <select v-model="filterCategory" class="px-3.5 py-2 rounded-lg border border-gray-300 text-sm" @change="load()">
        <option value="">Semua Kategori</option>
        <option v-for="c in categories" :key="c" :value="c">{{ c }}</option>
      </select>
    </div>

    <!-- CALENDAR VIEW -->
    <div v-if="viewMode === 'calendar'" class="bg-white rounded-xl border border-gray-200 overflow-hidden">
      <!-- Month navigation -->
      <div class="flex items-center justify-between px-4 py-3 bg-gray-50 border-b border-gray-200">
        <button class="p-1.5 rounded-lg hover:bg-gray-200" @click="prevMonth"><ChevronLeftIcon class="w-5 h-5 text-gray-600" /></button>
        <h2 class="text-sm font-bold text-gray-800">{{ monthNames[currentMonth] }} {{ currentYear }}</h2>
        <button class="p-1.5 rounded-lg hover:bg-gray-200" @click="nextMonth"><ChevronRightIcon class="w-5 h-5 text-gray-600" /></button>
      </div>

      <!-- Day headers -->
      <div class="grid grid-cols-7 border-b border-gray-200">
        <div v-for="d in dayNames" :key="d" class="px-2 py-2 text-center text-xs font-semibold text-gray-500 uppercase">{{ d }}</div>
      </div>

      <!-- Days grid -->
      <div class="grid grid-cols-7">
        <div v-for="(cell, i) in calendarDays" :key="i"
          :class="['min-h-[80px] border-b border-r border-gray-100 p-1', cell.day === 0 ? 'bg-gray-50' : '']">
          <div v-if="cell.day > 0">
            <span :class="['inline-flex w-6 h-6 items-center justify-center rounded-full text-xs font-medium mb-0.5',
              isToday(cell.date) ? 'bg-blue-600 text-white' : 'text-gray-700']">
              {{ cell.day }}
            </span>
            <div class="space-y-0.5">
              <div v-for="ev in cell.events.slice(0, 2)" :key="ev.id"
                :class="['px-1 py-0.5 rounded text-[10px] font-medium truncate cursor-pointer', categoryColor[ev.category] ?? 'bg-gray-500', 'text-white']"
                :title="ev.title"
                @click="openEdit(ev)">
                {{ ev.title }}
              </div>
              <span v-if="cell.events.length > 2" class="text-[10px] text-gray-400">+{{ cell.events.length - 2 }} lagi</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- LIST VIEW -->
    <div v-if="viewMode === 'list'" class="space-y-2">
      <div v-if="loading" class="text-center py-8 text-gray-400">Memuat...</div>
      <div v-else-if="!events.length" class="text-center py-8 text-gray-400 text-sm">Belum ada event.</div>
      <div v-else v-for="event in events" :key="event.id" class="flex items-center gap-4 p-4 bg-white rounded-xl border border-gray-200 hover:border-blue-200 transition-colors">
        <div :class="['w-2 h-12 rounded-full shrink-0', categoryColor[event.category] ?? 'bg-gray-400']" />
        <div class="flex-1 min-w-0">
          <div class="flex items-center gap-2">
            <p class="font-medium text-gray-900 text-sm">{{ event.title }}</p>
            <span :class="['px-2 py-0.5 rounded-full text-[10px] font-medium', categoryBadge[event.category]]">{{ event.category }}</span>
          </div>
          <p class="text-xs text-gray-500 mt-0.5">
            {{ formatDate(event.start_date) }}
            <span v-if="event.end_date && event.end_date !== event.start_date"> – {{ formatDate(event.end_date) }}</span>
          </p>
          <p v-if="event.description" class="text-xs text-gray-400 mt-0.5 truncate">{{ event.description }}</p>
        </div>
        <div v-if="isAdmin" class="flex items-center gap-1 shrink-0">
          <button class="p-1.5 rounded-lg text-blue-600 hover:bg-blue-50" @click="openEdit(event)"><PencilIcon class="w-4 h-4" /></button>
          <button class="p-1.5 rounded-lg text-red-500 hover:bg-red-50" @click="handleDelete(event)"><TrashIcon class="w-4 h-4" /></button>
        </div>
      </div>
    </div>

    <!-- Legend -->
    <div class="flex flex-wrap gap-3">
      <div v-for="c in categories" :key="c" class="flex items-center gap-1.5">
        <div :class="['w-3 h-3 rounded-full', categoryColor[c]]" />
        <span class="text-xs text-gray-600">{{ c }}</span>
      </div>
    </div>
  </div>

  <!-- Modal -->
  <BaseModal :open="modalOpen" :title="editingId ? 'Edit Event' : 'Tambah Event'" size="xl" @close="modalOpen = false">
    <form class="space-y-4" @submit.prevent="handleSave">
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Tahun Akademik <span class="text-red-500">*</span></label>
          <select v-model="form.academic_year_id" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
            <option value="">-- Pilih --</option>
            <option v-for="y in academicYears" :key="y.id" :value="y.id">{{ y.name }}</option>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Kategori <span class="text-red-500">*</span></label>
          <select v-model="form.category" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
            <option v-for="c in categories" :key="c" :value="c">{{ c }}</option>
          </select>
        </div>
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Judul <span class="text-red-500">*</span></label>
        <input v-model="form.title" required placeholder="Awal Perkuliahan Semester Ganjil" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" />
      </div>
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Tanggal Mulai <span class="text-red-500">*</span></label>
          <input v-model="form.start_date" type="date" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Tanggal Selesai</label>
          <input v-model="form.end_date" type="date" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" />
        </div>
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Deskripsi</label>
        <textarea v-model="form.description" rows="2" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" />
      </div>
    </form>
    <template #footer>
      <button class="px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded-lg" @click="modalOpen = false">Batal</button>
      <button :disabled="saving" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 disabled:bg-blue-400 text-white text-sm font-medium rounded-lg" @click="handleSave">{{ saving ? 'Menyimpan...' : 'Simpan' }}</button>
    </template>
  </BaseModal>
</template>
