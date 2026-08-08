<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useRouter } from 'vue-router'
import { PlusIcon, CalendarDaysIcon, MapPinIcon, UsersIcon, CheckCircleIcon, ClockIcon } from '@heroicons/vue/24/outline'
import { useAuthStore } from '@/stores/auth'
import { useCrud } from '@/composables/useCrud'
import api from '@/services/api'

const router = useRouter()
const auth = useAuthStore()
const canCreate = auth.hasPermission('agenda.create')

// Admin view
const { items, pagination, loading, fetchAll } = useCrud<any>('/events')
const search = ref('')
const filterCategory = ref('')

// Dosen/Mahasiswa view
const myEvents = ref<any[]>([])
const attendanceHistory = ref<any[]>([])
const userLoading = ref(true)
const activeTab = ref<'upcoming' | 'history'>('upcoming')

const categories = ['Rapat', 'Seminar', 'Workshop', 'Pelatihan', 'Wisuda', 'Dies Natalis', 'Lainnya']
const categoryColor: Record<string, string> = {
  Rapat: 'bg-blue-100 text-blue-700', Seminar: 'bg-purple-100 text-purple-700',
  Workshop: 'bg-amber-100 text-amber-700', Pelatihan: 'bg-green-100 text-green-700',
  Wisuda: 'bg-pink-100 text-pink-700', 'Dies Natalis': 'bg-indigo-100 text-indigo-700',
  Lainnya: 'bg-gray-100 text-gray-600',
}

onMounted(async () => {
  if (canCreate) {
    load()
  } else {
    await loadUserEvents()
  }
})

function load(page = 1) { fetchAll({ search: search.value, category: filterCategory.value, page }) }

async function loadUserEvents() {
  userLoading.value = true
  try {
    const [eventsRes, historyRes] = await Promise.all([
      api.get('/events', { params: { per_page: 50 } }),
      api.get('/events/my-attendance'),
    ])
    myEvents.value = eventsRes.data?.data ?? eventsRes.data ?? []
    attendanceHistory.value = historyRes.data ?? []
  } catch (e) {
    // fallback: hanya pakai events
    try {
      const res = await api.get('/events', { params: { per_page: 50 } })
      myEvents.value = res.data?.data ?? res.data ?? []
    } catch {}
  } finally { userLoading.value = false }
}

const upcomingEvents = computed(() =>
  myEvents.value.filter((e: any) => new Date(e.event_date) >= new Date(new Date().toDateString()))
    .sort((a: any, b: any) => new Date(a.event_date).getTime() - new Date(b.event_date).getTime())
)

const pastEvents = computed(() =>
  myEvents.value.filter((e: any) => new Date(e.event_date) < new Date(new Date().toDateString()))
    .sort((a: any, b: any) => new Date(b.event_date).getTime() - new Date(a.event_date).getTime())
)

function formatDate(d: string) { return new Date(d).toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' }) }
function formatDateShort(d: string) { return new Date(d).toLocaleDateString('id-ID', { day: 'numeric', month: 'short' }) }
function isToday(d: string) { return new Date(d).toDateString() === new Date().toDateString() }
</script>

<template>
  <div class="space-y-5">
    <!-- ============================================ -->
    <!-- ADMIN VIEW -->
    <!-- ============================================ -->
    <template v-if="canCreate">
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-xl font-bold text-gray-900">Agenda Kegiatan</h1>
          <p class="text-sm text-gray-500 mt-0.5">Kelola rapat, seminar, workshop, dan kegiatan kampus</p>
        </div>
        <button class="flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg" @click="router.push('/agenda/buat')">
          <PlusIcon class="w-4 h-4" /> Buat Agenda
        </button>
      </div>

      <div class="flex flex-wrap gap-3">
        <input v-model="search" type="text" placeholder="Cari agenda..." class="px-3.5 py-2 rounded-lg border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 w-56" @input="load()" />
        <select v-model="filterCategory" class="px-3.5 py-2 rounded-lg border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" @change="load()">
          <option value="">Semua Kategori</option>
          <option v-for="c in categories" :key="c" :value="c">{{ c }}</option>
        </select>
      </div>

      <div v-if="loading" class="text-center text-gray-400 py-12">Memuat...</div>
      <div v-else-if="items.length === 0" class="text-center text-gray-400 py-12">Belum ada agenda.</div>
      <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        <div v-for="item in items" :key="item.id"
          class="bg-white rounded-xl border border-gray-200 p-5 hover:shadow-md transition-shadow cursor-pointer"
          @click="router.push(`/agenda/${item.id}`)">
          <div class="flex items-start justify-between mb-3">
            <span :class="['px-2 py-0.5 rounded-full text-[10px] font-semibold', categoryColor[item.category] ?? 'bg-gray-100 text-gray-600']">
              {{ item.category }}
            </span>
            <span :class="['px-2 py-0.5 rounded-full text-[10px] font-medium', item.is_open ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500']">
              {{ item.is_open ? 'Presensi Buka' : 'Presensi Tutup' }}
            </span>
          </div>
          <h3 class="text-sm font-semibold text-gray-900 line-clamp-2">{{ item.title }}</h3>
          <p v-if="item.organizer" class="text-xs text-gray-500 mt-1">{{ item.organizer }}</p>
          <div class="mt-3 space-y-1.5">
            <div class="flex items-center gap-2 text-xs text-gray-500">
              <CalendarDaysIcon class="w-3.5 h-3.5" />
              {{ formatDate(item.event_date) }} {{ item.start_time ? `· ${item.start_time.slice(0,5)}` : '' }}
            </div>
            <div v-if="item.location" class="flex items-center gap-2 text-xs text-gray-500">
              <MapPinIcon class="w-3.5 h-3.5" />
              {{ item.location }}
            </div>
            <div class="flex items-center gap-2 text-xs text-gray-500">
              <UsersIcon class="w-3.5 h-3.5" />
              {{ item.attendances_count ?? 0 }} hadir
            </div>
          </div>
        </div>
      </div>

      <div v-if="pagination.lastPage > 1" class="flex justify-center gap-2">
        <button v-for="p in pagination.lastPage" :key="p" :class="['px-3 py-1 rounded text-sm', p === pagination.currentPage ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200']" @click="load(p)">{{ p }}</button>
      </div>
    </template>

    <!-- ============================================ -->
    <!-- DOSEN / MAHASISWA VIEW -->
    <!-- ============================================ -->
    <template v-else>
      <div>
        <h1 class="text-xl font-bold text-gray-900">Agenda Kegiatan</h1>
        <p class="text-sm text-gray-500 mt-0.5">Undangan kegiatan dan riwayat kehadiran Anda</p>
      </div>

      <!-- Tab Switch -->
      <div class="flex border-b border-gray-200">
        <button :class="['px-4 py-2.5 text-sm font-medium border-b-2 transition-colors', activeTab === 'upcoming' ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700']"
          @click="activeTab = 'upcoming'">
          <span class="inline-flex items-center gap-1.5">
            <CalendarDaysIcon class="w-4 h-4" />
            Undangan
            <span v-if="upcomingEvents.length" class="ml-1 px-1.5 py-0.5 text-[10px] font-bold bg-blue-100 text-blue-700 rounded-full">{{ upcomingEvents.length }}</span>
          </span>
        </button>
        <button :class="['px-4 py-2.5 text-sm font-medium border-b-2 transition-colors', activeTab === 'history' ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700']"
          @click="activeTab = 'history'">
          <span class="inline-flex items-center gap-1.5">
            <CheckCircleIcon class="w-4 h-4" />
            Riwayat Kehadiran
          </span>
        </button>
      </div>

      <div v-if="userLoading" class="text-center text-gray-400 py-12">Memuat...</div>

      <!-- TAB: Undangan -->
      <template v-else-if="activeTab === 'upcoming'">
        <div v-if="upcomingEvents.length === 0 && pastEvents.length === 0" class="text-center py-16">
          <CalendarDaysIcon class="w-12 h-12 text-gray-300 mx-auto mb-3" />
          <p class="text-gray-500 font-medium">Belum ada undangan kegiatan</p>
          <p class="text-gray-400 text-sm mt-1">Anda akan melihat agenda ketika diundang ke suatu kegiatan</p>
        </div>

        <!-- Upcoming events -->
        <div v-if="upcomingEvents.length" class="space-y-3">
          <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Kegiatan Mendatang</h3>
          <div v-for="item in upcomingEvents" :key="item.id"
            class="bg-white rounded-xl border border-gray-200 p-4 hover:shadow-md hover:border-blue-200 transition-all cursor-pointer"
            @click="router.push(`/agenda/${item.id}`)">
            <div class="flex items-start gap-4">
              <!-- Date badge -->
              <div class="w-14 h-14 rounded-xl flex flex-col items-center justify-center flex-shrink-0"
                :class="isToday(item.event_date) ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700'">
                <span class="text-[10px] font-bold uppercase">{{ new Date(item.event_date).toLocaleDateString('id-ID', { month: 'short' }) }}</span>
                <span class="text-lg font-bold -mt-0.5">{{ new Date(item.event_date).getDate() }}</span>
              </div>
              <!-- Content -->
              <div class="flex-1 min-w-0">
                <div class="flex items-start justify-between gap-2">
                  <div class="min-w-0">
                    <h4 class="text-sm font-semibold text-gray-900 line-clamp-1">{{ item.title }}</h4>
                    <p v-if="item.organizer" class="text-xs text-gray-500 mt-0.5">{{ item.organizer }}</p>
                  </div>
                  <span :class="['px-2 py-0.5 rounded-full text-[10px] font-semibold flex-shrink-0', categoryColor[item.category] ?? 'bg-gray-100 text-gray-600']">
                    {{ item.category }}
                  </span>
                </div>
                <div class="flex flex-wrap items-center gap-3 mt-2">
                  <span class="inline-flex items-center gap-1 text-xs text-gray-500">
                    <ClockIcon class="w-3.5 h-3.5" />
                    {{ item.start_time?.slice(0,5) ?? '-' }}{{ item.end_time ? ' - ' + item.end_time.slice(0,5) : '' }}
                  </span>
                  <span v-if="item.location" class="inline-flex items-center gap-1 text-xs text-gray-500">
                    <MapPinIcon class="w-3.5 h-3.5" />
                    {{ item.location }}
                  </span>
                  <span v-if="isToday(item.event_date)" class="inline-flex items-center gap-1 text-[10px] px-2 py-0.5 bg-blue-100 text-blue-700 rounded-full font-semibold">
                    Hari Ini
                  </span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Past events -->
        <div v-if="pastEvents.length" class="space-y-3 mt-6">
          <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Kegiatan Selesai</h3>
          <div v-for="item in pastEvents" :key="item.id"
            class="bg-white rounded-xl border border-gray-100 p-4 hover:bg-gray-50 transition-colors cursor-pointer opacity-75 hover:opacity-100"
            @click="router.push(`/agenda/${item.id}`)">
            <div class="flex items-center gap-4">
              <div class="w-12 h-12 rounded-lg bg-gray-100 flex flex-col items-center justify-center flex-shrink-0">
                <span class="text-[9px] font-bold text-gray-500 uppercase">{{ new Date(item.event_date).toLocaleDateString('id-ID', { month: 'short' }) }}</span>
                <span class="text-sm font-bold text-gray-600 -mt-0.5">{{ new Date(item.event_date).getDate() }}</span>
              </div>
              <div class="flex-1 min-w-0">
                <h4 class="text-sm font-medium text-gray-700 truncate">{{ item.title }}</h4>
                <p class="text-xs text-gray-400">{{ item.location }} · {{ item.start_time?.slice(0,5) ?? '' }}</p>
              </div>
              <span :class="['px-2 py-0.5 rounded-full text-[10px] font-medium flex-shrink-0', categoryColor[item.category] ?? 'bg-gray-100 text-gray-600']">
                {{ item.category }}
              </span>
            </div>
          </div>
        </div>
      </template>

      <!-- TAB: Riwayat Kehadiran -->
      <template v-else-if="activeTab === 'history'">
        <div v-if="attendanceHistory.length === 0" class="text-center py-16">
          <CheckCircleIcon class="w-12 h-12 text-gray-300 mx-auto mb-3" />
          <p class="text-gray-500 font-medium">Belum ada riwayat kehadiran</p>
          <p class="text-gray-400 text-sm mt-1">Riwayat akan muncul setelah Anda mengisi presensi kegiatan</p>
        </div>

        <div v-else class="space-y-3">
          <div v-for="att in attendanceHistory" :key="att.id"
            class="bg-white rounded-xl border border-gray-200 p-4 hover:shadow-sm transition-shadow cursor-pointer"
            @click="router.push(`/agenda/${att.event_id}`)">
            <div class="flex items-center gap-4">
              <!-- Checkmark -->
              <div class="w-10 h-10 rounded-full bg-green-100 flex items-center justify-center flex-shrink-0">
                <CheckCircleIcon class="w-5 h-5 text-green-600" />
              </div>
              <div class="flex-1 min-w-0">
                <h4 class="text-sm font-medium text-gray-800 truncate">{{ att.event_title }}</h4>
                <div class="flex flex-wrap items-center gap-2 mt-1">
                  <span class="text-xs text-gray-500">{{ att.event_date ? formatDate(att.event_date) : '-' }}</span>
                  <span v-if="att.location" class="text-xs text-gray-400">· {{ att.location }}</span>
                </div>
              </div>
              <div class="text-right flex-shrink-0">
                <span class="inline-flex items-center gap-1 text-xs px-2 py-1 rounded-full font-medium"
                  :class="att.method === 'APP' || att.method === 'qr' ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-600'">
                  {{ att.method === 'APP' || att.method === 'qr' ? '📱 QR' : att.method === 'FORM' ? '📝 Form' : att.method }}
                </span>
                <p class="text-[10px] text-gray-400 mt-1">{{ att.attended_at }}</p>
              </div>
            </div>
          </div>
        </div>
      </template>
    </template>
  </div>
</template>
