<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useToast } from 'vue-toastification'
import { useAuthStore } from '@/stores/auth'
import { PlusIcon, EyeIcon, TrashIcon } from '@heroicons/vue/24/outline'
import api from '@/services/api'

const router = useRouter()
const toast  = useToast()
const auth   = useAuthStore()

const isAdmin    = auth.hasRole('SUPER_ADMIN') || auth.hasRole('ADMIN_AKADEMIK')
const isLp2m     = auth.hasPermission('karya.verify') || isAdmin
const isDosen    = auth.hasRole('DOSEN')

const items      = ref<any[]>([])
const stats      = ref<any>(null)
const periods    = ref<any[]>([])
const pagination = ref({ total: 0, currentPage: 1, lastPage: 1 })
const loading    = ref(true)
const filterType   = ref('')
const filterStatus = ref('')
const filterPeriod = ref('')
const search       = ref('')

const TYPE_LABELS: Record<string, string> = {
  penelitian: 'Penelitian', pengabdian: 'Pengabdian kepada Masyarakat',
}
const STATUS_LABELS: Record<string, string> = {
  draft: 'Draft', review_kaprodi: 'Review Ka.Prodi', submitted: 'Dikembalikan',
  seleksi_reviewer: 'Seleksi Reviewer', tidak_lolos: 'Tidak Lolos', kontrak: 'Kontrak',
  pelaksanaan_1: 'Pelaksanaan I', monev: 'Monev', revisi_kemajuan: 'Revisi Kemajuan',
  pelaksanaan_2: 'Pelaksanaan II', seminar: 'Seminar', revisi_seminar: 'Revisi Seminar',
  lpj: 'LPJ', revisi_lpj: 'Revisi LPJ', selesai: 'Selesai',
}
const STATUS_COLORS: Record<string, string> = {
  draft: 'bg-gray-100 text-gray-600', review_kaprodi: 'bg-yellow-100 text-yellow-700',
  submitted: 'bg-orange-100 text-orange-700', seleksi_reviewer: 'bg-blue-100 text-blue-700',
  tidak_lolos: 'bg-red-100 text-red-700', kontrak: 'bg-purple-100 text-purple-700',
  pelaksanaan_1: 'bg-indigo-100 text-indigo-700', monev: 'bg-cyan-100 text-cyan-700',
  revisi_kemajuan: 'bg-orange-100 text-orange-700', pelaksanaan_2: 'bg-indigo-100 text-indigo-700',
  seminar: 'bg-teal-100 text-teal-700', revisi_seminar: 'bg-orange-100 text-orange-700',
  lpj: 'bg-violet-100 text-violet-700', revisi_lpj: 'bg-orange-100 text-orange-700',
  selesai: 'bg-green-100 text-green-700',
}

onMounted(async () => {
  load()
  try {
    const [s, p] = await Promise.all([api.get('/penelitian/stats'), api.get('/penelitian-periods')])
    stats.value = s.data; periods.value = p.data
  } catch {}
})

async function load(page = 1) {
  loading.value = true
  try {
    const { data } = await api.get('/penelitian', {
      params: { status: filterStatus.value, type: filterType.value, period_id: filterPeriod.value, search: search.value, page },
    })
    items.value = data.data
    pagination.value = { total: data.total, currentPage: data.current_page, lastPage: data.last_page }
  } finally { loading.value = false }
}

async function del(item: any) {
  if (!confirm(`Hapus proposal "${item.title}"?`)) return
  try {
    await api.delete(`/penelitian/${item.id}`)
    toast.success('Proposal dihapus.')
    load()
  } catch (e: any) { toast.error(e?.response?.data?.message ?? 'Gagal.') }
}
</script>

<template>
  <div class="space-y-5">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-xl font-bold text-gray-900">Hibah Penelitian & Pengabdian</h1>
        <p class="text-sm text-gray-500 mt-0.5">Kelola proposal hibah penelitian dan pengabdian kepada masyarakat</p>
      </div>
      <button v-if="isDosen || isAdmin"
        class="flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg"
        @click="router.push('/penelitian/buat')">
        <PlusIcon class="w-4 h-4" /> Ajukan Proposal
      </button>
    </div>

    <!-- Stats -->
    <div v-if="stats" class="grid grid-cols-3 md:grid-cols-6 gap-3">
      <div v-for="(label, key) in { draft: 'Draft', review_kaprodi: 'Review Kaprodi', seleksi_reviewer: 'Seleksi', kontrak: 'Kontrak', pelaksanaan_1: 'Pelaksanaan', selesai: 'Selesai' }"
        :key="key" class="bg-white border border-gray-200 rounded-xl p-3 text-center">
        <p class="text-xl font-bold text-gray-800">{{ (stats as any)[key] ?? 0 }}</p>
        <p class="text-xs text-gray-500 mt-0.5">{{ label }}</p>
      </div>
    </div>

    <!-- Filters -->
    <div class="flex flex-wrap gap-3">
      <select v-model="filterType" class="px-3 py-2 rounded-lg border border-gray-300 text-sm" @change="load()">
        <option value="">Semua Jenis</option>
        <option value="penelitian">Penelitian</option>
        <option value="pengabdian">Pengabdian</option>
      </select>
      <select v-model="filterStatus" class="px-3 py-2 rounded-lg border border-gray-300 text-sm" @change="load()">
        <option value="">Semua Status</option>
        <option v-for="(label, val) in STATUS_LABELS" :key="val" :value="val">{{ label }}</option>
      </select>
      <select v-model="filterPeriod" class="px-3 py-2 rounded-lg border border-gray-300 text-sm" @change="load()">
        <option value="">Semua Periode</option>
        <option v-for="p in periods" :key="p.id" :value="p.id">{{ p.name }}</option>
      </select>
      <input v-model="search" type="text" placeholder="Cari judul..."
        class="px-3 py-2 rounded-lg border border-gray-300 text-sm w-52" @input="load()" />
    </div>

    <!-- List -->
    <div v-if="loading" class="text-center py-10 text-gray-400">Memuat...</div>
    <div v-else-if="!items.length" class="text-center py-10 text-gray-400 bg-white rounded-xl border border-gray-200">
      <p>Belum ada proposal.</p>
      <button v-if="isDosen || isAdmin" class="mt-3 px-4 py-2 bg-blue-600 text-white text-sm rounded-lg"
        @click="router.push('/penelitian/buat')">Ajukan Proposal Pertama</button>
    </div>
    <div v-else class="space-y-3">
      <div v-for="item in items" :key="item.id"
        class="bg-white rounded-xl border border-gray-200 p-4 hover:border-blue-200 transition-colors cursor-pointer"
        @click="router.push(`/penelitian/${item.id}`)">
        <div class="flex items-start gap-4">
          <div class="w-10 h-10 rounded-lg flex items-center justify-center shrink-0 text-lg"
            :class="item.type === 'penelitian' ? 'bg-blue-50' : 'bg-green-50'">
            {{ item.type === 'penelitian' ? '🔬' : '🤝' }}
          </div>
          <div class="flex-1 min-w-0">
            <div class="flex items-start justify-between gap-2">
              <div class="flex-1 min-w-0">
                <p class="font-semibold text-gray-900 line-clamp-2">{{ item.title }}</p>
                <div class="flex items-center gap-2 mt-1 flex-wrap text-xs text-gray-500">
                  <span class="px-2 py-0.5 rounded bg-gray-100 text-gray-600">{{ TYPE_LABELS[item.type] }}</span>
                  <span v-if="item.period">{{ item.period.name }}</span>
                  <span v-if="item.ketua">· {{ item.ketua.full_name }}</span>
                </div>
                <div v-if="item.total_dana" class="mt-1 text-xs text-green-700 font-medium">
                  Dana: Rp {{ Number(item.total_dana).toLocaleString('id-ID') }}
                </div>
              </div>
              <span :class="['text-xs px-2.5 py-1 rounded-full font-medium shrink-0', STATUS_COLORS[item.status] ?? 'bg-gray-100 text-gray-600']">
                {{ STATUS_LABELS[item.status] ?? item.status }}
              </span>
            </div>
          </div>
          <button v-if="(isDosen || isAdmin) && ['draft','submitted'].includes(item.status)"
            class="p-1.5 rounded-lg text-red-500 hover:bg-red-50 shrink-0"
            title="Hapus" @click.stop="del(item)">
            <TrashIcon class="w-4 h-4" />
          </button>
        </div>
      </div>

      <div v-if="pagination.lastPage > 1" class="flex items-center justify-center gap-2 pt-2">
        <button v-for="p in pagination.lastPage" :key="p"
          :class="['w-8 h-8 rounded-lg text-xs font-medium transition-colors',
            p === pagination.currentPage ? 'bg-blue-600 text-white' : 'bg-gray-100 hover:bg-gray-200 text-gray-700']"
          @click="load(p)">{{ p }}</button>
      </div>
    </div>
  </div>
</template>
