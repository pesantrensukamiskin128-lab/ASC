<script setup lang="ts">
import { ref, onMounted, watch } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { MagnifyingGlassIcon, BookOpenIcon, AcademicCapIcon, BeakerIcon } from '@heroicons/vue/24/outline'
import api from '@/services/api'

interface RepoItem {
  id: number; source: string; type: string; type_label: string
  title: string; author: string; study_program?: string
  year?: number; abstract?: string; cover_url?: string; has_file: boolean
  published_at: string
}

const router = useRouter()
const route  = useRoute()

const items      = ref<RepoItem[]>([])
const loading    = ref(false)
const total      = ref(0)
const currentPage= ref(1)
const lastPage   = ref(1)
const stats      = ref({ total: 0, penelitian: 0, pengabdian: 0, skripsi: 0, karya_dosen: 0 })

const search     = ref((route.query.search as string) || '')
const filterType = ref((route.query.type as string) || '')
const filterYear = ref((route.query.year as string) || '')
const filterProdi= ref((route.query.prodi as string) || '')

const programs   = ref<any[]>([])
const years      = ref<number[]>([])

const typeOptions = [
  { value: '', label: 'Semua Jenis' },
  { value: 'penelitian', label: '🔬 Penelitian' },
  { value: 'pengabdian', label: '🤝 Pengabdian' },
  { value: 'skripsi', label: '🎓 Skripsi' },
  { value: 'buku', label: '📚 Buku' },
  { value: 'modul_ajar', label: '📝 Modul Ajar' },
  { value: 'hki_paten', label: '🏅 HKI / Paten' },
  { value: 'penelitian_mandiri', label: '🔬 Penelitian Mandiri' },
  { value: 'pengabdian_mandiri', label: '🤝 Pengabdian Mandiri' },
]

const sourceIcon: Record<string, string> = {
  penelitian: '🔬', skripsi: '🎓', karya_dosen: '📚'
}
const sourceBg: Record<string, string> = {
  penelitian: 'bg-blue-50', skripsi: 'bg-green-50', karya_dosen: 'bg-purple-50'
}

onMounted(async () => {
  // Load stats, programs, years bersamaan
  const [statsRes, prodiRes] = await Promise.all([
    api.get('/repository/stats').catch(() => ({ data: stats.value })),
    api.get('/study-programs/all').catch(() => ({ data: [] })),
  ])
  stats.value    = statsRes.data
  programs.value = prodiRes.data

  // Generate tahun 5 tahun terakhir
  const now = new Date().getFullYear()
  years.value = Array.from({ length: 6 }, (_, i) => now - i)

  load()
})

watch([filterType, filterYear, filterProdi], () => { currentPage.value = 1; load() })

let searchTimer: ReturnType<typeof setTimeout>
watch(search, () => {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => { currentPage.value = 1; load() }, 400)
})

async function load(page = currentPage.value) {
  loading.value = true
  try {
    const { data } = await api.get('/repository', {
      params: {
        search: search.value || undefined,
        type: filterType.value || undefined,
        year: filterYear.value || undefined,
        study_program_id: filterProdi.value || undefined,
        page,
        per_page: 12,
      },
    })
    items.value      = data.data
    total.value      = data.total
    currentPage.value= data.current_page
    lastPage.value   = data.last_page
  } catch { } finally { loading.value = false }
}

function goDetail(item: RepoItem) {
  router.push(`/repository/${item.source}/${item.id}`)
}

function truncate(text: string | undefined, max = 120) {
  if (!text) return '-'
  return text.length > max ? text.substring(0, max) + '...' : text
}
</script>

<template>
  <div class="space-y-6">
    <!-- Hero -->
    <div class="bg-gradient-to-r from-blue-700 to-blue-500 rounded-2xl p-8 text-white">
      <h1 class="text-2xl font-bold mb-1">Repository Karya Ilmiah</h1>
      <p class="text-blue-100 text-sm mb-5">Kumpulan penelitian, skripsi, dan karya ilmiah yang telah dipublikasikan</p>

      <!-- Search Box -->
      <div class="relative max-w-2xl">
        <MagnifyingGlassIcon class="absolute left-3.5 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400" />
        <input
          v-model="search"
          type="text"
          placeholder="Cari judul, abstrak, kata kunci..."
          class="w-full pl-10 pr-4 py-3 rounded-xl text-gray-900 text-sm focus:outline-none focus:ring-2 focus:ring-blue-300"
        />
      </div>

      <!-- Stats -->
      <div class="flex flex-wrap gap-4 mt-5">
        <div v-for="s in [
          { label: 'Total Karya', value: stats.total, icon: '📖' },
          { label: 'Penelitian', value: stats.penelitian, icon: '🔬' },
          { label: 'Pengabdian', value: stats.pengabdian, icon: '🤝' },
          { label: 'Skripsi', value: stats.skripsi, icon: '🎓' },
          { label: 'Karya Dosen', value: stats.karya_dosen, icon: '📚' },
        ]" :key="s.label" class="bg-white/20 rounded-xl px-4 py-2 text-center">
          <p class="text-lg font-bold">{{ s.icon }} {{ s.value }}</p>
          <p class="text-xs text-blue-100">{{ s.label }}</p>
        </div>
      </div>
    </div>

    <!-- Filters -->
    <div class="flex flex-wrap gap-3 items-center bg-white rounded-xl border border-gray-200 p-4">
      <select v-model="filterType" class="px-3 py-2 rounded-lg border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
        <option v-for="t in typeOptions" :key="t.value" :value="t.value">{{ t.label }}</option>
      </select>

      <select v-model="filterYear" class="px-3 py-2 rounded-lg border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
        <option value="">Semua Tahun</option>
        <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
      </select>

      <select v-model="filterProdi" class="px-3 py-2 rounded-lg border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
        <option value="">Semua Program Studi</option>
        <option v-for="p in programs" :key="p.id" :value="p.id">{{ p.name }}</option>
      </select>

      <span v-if="total > 0" class="text-sm text-gray-500 ml-auto">{{ total }} karya ditemukan</span>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="flex items-center justify-center py-16 text-gray-400">
      <svg class="animate-spin w-8 h-8 mr-3" fill="none" viewBox="0 0 24 24">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z" />
      </svg>
      Memuat karya ilmiah...
    </div>

    <!-- Empty -->
    <div v-else-if="!items.length" class="text-center py-16 text-gray-400">
      <BookOpenIcon class="w-16 h-16 mx-auto mb-3 text-gray-300" />
      <p class="font-medium text-gray-500">Tidak ada karya yang ditemukan</p>
      <p class="text-sm mt-1">Coba ubah kata kunci atau filter</p>
    </div>

    <!-- Grid -->
    <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-5">
      <div
        v-for="item in items"
        :key="`${item.source}-${item.id}`"
        class="bg-white rounded-xl border border-gray-200 overflow-hidden hover:border-blue-300 hover:shadow-md transition-all cursor-pointer group"
        @click="goDetail(item)"
      >
        <!-- Cover / Thumbnail -->
        <div :class="['h-36 flex items-center justify-center text-4xl', sourceBg[item.source] ?? 'bg-gray-50']">
          <img v-if="item.cover_url" :src="item.cover_url" :alt="item.title" class="h-full w-full object-cover" />
          <span v-else>{{ sourceIcon[item.source] ?? '📄' }}</span>
        </div>

        <!-- Info -->
        <div class="p-4">
          <div class="flex items-center gap-1.5 mb-2">
            <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-700">
              {{ item.type_label }}
            </span>
            <span v-if="item.year" class="text-xs text-gray-400">{{ item.year }}</span>
          </div>
          <h3 class="text-sm font-semibold text-gray-900 line-clamp-2 group-hover:text-blue-700 transition-colors leading-snug">
            {{ item.title }}
          </h3>
          <p class="text-xs text-gray-500 mt-1.5 truncate">{{ item.author }}</p>
          <p v-if="item.study_program" class="text-xs text-gray-400 truncate">{{ item.study_program }}</p>

          <div class="mt-3 flex items-center justify-between">
            <button class="text-xs text-blue-600 hover:underline font-medium">Lihat Detail →</button>
            <span v-if="item.has_file" class="text-xs text-green-600" title="File tersedia">📎</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Pagination -->
    <div v-if="lastPage > 1" class="flex items-center justify-center gap-2">
      <button
        v-for="p in lastPage"
        :key="p"
        :disabled="p === currentPage"
        :class="[
          'w-9 h-9 rounded-lg text-sm font-medium transition-colors',
          p === currentPage ? 'bg-blue-600 text-white' : 'bg-white border border-gray-300 text-gray-600 hover:bg-gray-50',
        ]"
        @click="load(p)"
      >
        {{ p }}
      </button>
    </div>
  </div>
</template>
