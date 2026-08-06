<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { PlusIcon, CalendarDaysIcon, MapPinIcon, UsersIcon } from '@heroicons/vue/24/outline'
import { useAuthStore } from '@/stores/auth'
import { useCrud } from '@/composables/useCrud'

const router = useRouter()
const auth = useAuthStore()
const canCreate = auth.hasPermission('agenda.create')

const { items, pagination, loading, fetchAll } = useCrud<any>('/events')
const search = ref('')
const filterCategory = ref('')

const categories = ['Rapat', 'Seminar', 'Workshop', 'Pelatihan', 'Wisuda', 'Dies Natalis', 'Lainnya']
const categoryColor: Record<string, string> = {
  Rapat: 'bg-blue-100 text-blue-700', Seminar: 'bg-purple-100 text-purple-700',
  Workshop: 'bg-amber-100 text-amber-700', Pelatihan: 'bg-green-100 text-green-700',
  Wisuda: 'bg-pink-100 text-pink-700', 'Dies Natalis': 'bg-indigo-100 text-indigo-700',
  Lainnya: 'bg-gray-100 text-gray-600',
}

onMounted(() => load())
function load(page = 1) { fetchAll({ search: search.value, category: filterCategory.value, page }) }

function formatDate(d: string) { return new Date(d).toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' }) }
</script>

<template>
  <div class="space-y-5">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-xl font-bold text-gray-900">Agenda Kegiatan</h1>
        <p class="text-sm text-gray-500 mt-0.5">Kelola rapat, seminar, workshop, dan kegiatan kampus</p>
      </div>
      <button v-if="canCreate" class="flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg" @click="router.push('/agenda/buat')">
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
  </div>
</template>
