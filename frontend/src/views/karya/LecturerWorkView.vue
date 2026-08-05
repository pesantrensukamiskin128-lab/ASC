<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useToast } from 'vue-toastification'
import { useAuthStore } from '@/stores/auth'
import { PlusIcon, EyeIcon, PencilIcon, TrashIcon } from '@heroicons/vue/24/outline'
import api from '@/services/api'

const router = useRouter()
const toast  = useToast()
const auth   = useAuthStore()

const isAdmin = auth.hasRole('SUPER_ADMIN') || auth.hasRole('ADMIN_AKADEMIK') || auth.hasRole('LP2M')
const isDosen = auth.hasRole('DOSEN') && !isAdmin

const items      = ref<any[]>([])
const stats      = ref<any>(null)
const pagination = ref({ total: 0, currentPage: 1, lastPage: 1 })
const loading    = ref(true)
const filterType   = ref('')
const filterStatus = ref('')
const search       = ref('')

const TYPE_LABELS: Record<string, string> = {
  buku: 'Buku', modul_ajar: 'Modul Ajar', hki_paten: 'HKI / Paten',
  penelitian_mandiri: 'Penelitian Mandiri', pengabdian_mandiri: 'Pengabdian Mandiri',
}
const STATUS_COLORS: Record<string, string> = {
  draft: 'bg-gray-100 text-gray-600', diajukan: 'bg-blue-100 text-blue-700',
  revisi: 'bg-yellow-100 text-yellow-700', diverifikasi: 'bg-indigo-100 text-indigo-700',
  dipublikasikan: 'bg-green-100 text-green-700',
}
const STATUS_LABELS: Record<string, string> = {
  draft: 'Draft', diajukan: 'Diajukan', revisi: 'Perlu Revisi',
  diverifikasi: 'Diverifikasi', dipublikasikan: 'Dipublikasikan',
}

onMounted(async () => {
  load()
  try { const { data } = await api.get('/lecturer-works/stats'); stats.value = data } catch {}
})

async function load(page = 1) {
  loading.value = true
  try {
    const { data } = await api.get('/lecturer-works', {
      params: { type: filterType.value, status: filterStatus.value, search: search.value, page },
    })
    items.value = data.data
    pagination.value = { total: data.total, currentPage: data.current_page, lastPage: data.last_page }
  } finally { loading.value = false }
}

async function deleteWork(item: any) {
  if (!confirm(`Hapus karya "${item.title}"?`)) return
  try {
    await api.delete(`/lecturer-works/${item.id}`)
    toast.success('Karya berhasil dihapus.')
    load()
    const { data } = await api.get('/lecturer-works/stats'); stats.value = data
  } catch (e: any) { toast.error(e?.response?.data?.message ?? 'Gagal.') }
}
</script>

<template>
  <div class="space-y-5">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-xl font-bold text-gray-900">Karya Dosen</h1>
        <p class="text-sm text-gray-500 mt-0.5">
          {{ isDosen ? 'Kelola karya ilmiah, buku, modul, dan HKI Anda' : 'Verifikasi dan publikasi karya dosen' }}
        </p>
      </div>
      <button v-if="isDosen" class="flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg" @click="router.push('/karya-dosen/buat')">
        <PlusIcon class="w-4 h-4" /> Tambah Karya
      </button>
    </div>

    <!-- Stats -->
    <div v-if="stats" class="grid grid-cols-2 md:grid-cols-5 gap-3">
      <div class="bg-gray-50 border border-gray-200 rounded-xl p-3 text-center">
        <p class="text-xl font-bold text-gray-700">{{ stats.draft }}</p><p class="text-xs text-gray-500">Draft</p>
      </div>
      <div class="bg-blue-50 border border-blue-200 rounded-xl p-3 text-center">
        <p class="text-xl font-bold text-blue-700">{{ stats.diajukan }}</p><p class="text-xs text-blue-600">Diajukan</p>
      </div>
      <div class="bg-yellow-50 border border-yellow-200 rounded-xl p-3 text-center">
        <p class="text-xl font-bold text-yellow-700">{{ stats.revisi }}</p><p class="text-xs text-yellow-600">Revisi</p>
      </div>
      <div class="bg-indigo-50 border border-indigo-200 rounded-xl p-3 text-center">
        <p class="text-xl font-bold text-indigo-700">{{ stats.diverifikasi }}</p><p class="text-xs text-indigo-600">Diverifikasi</p>
      </div>
      <div class="bg-green-50 border border-green-200 rounded-xl p-3 text-center">
        <p class="text-xl font-bold text-green-700">{{ stats.dipublikasikan }}</p><p class="text-xs text-green-600">Dipublikasikan</p>
      </div>
    </div>

    <!-- Filters -->
    <div class="flex flex-wrap gap-3">
      <select v-model="filterType" class="px-3.5 py-2 rounded-lg border border-gray-300 text-sm" @change="load()">
        <option value="">Semua Jenis</option>
        <option v-for="(label, val) in TYPE_LABELS" :key="val" :value="val">{{ label }}</option>
      </select>
      <select v-model="filterStatus" class="px-3.5 py-2 rounded-lg border border-gray-300 text-sm" @change="load()">
        <option value="">Semua Status</option>
        <option v-for="(label, val) in STATUS_LABELS" :key="val" :value="val">{{ label }}</option>
      </select>
      <input v-model="search" type="text" placeholder="Cari judul..." class="px-3.5 py-2 rounded-lg border border-gray-300 text-sm w-52" @input="load()" />
    </div>

    <!-- List -->
    <div v-if="loading" class="text-center py-10 text-gray-400">Memuat...</div>
    <div v-else-if="!items.length" class="text-center py-10 text-gray-400 bg-white rounded-xl border border-gray-200">
      <p>Belum ada karya.</p>
      <button v-if="isDosen" class="mt-3 px-4 py-2 bg-blue-600 text-white text-sm rounded-lg" @click="router.push('/karya-dosen/buat')">Tambah Karya Pertama</button>
    </div>
    <div v-else class="space-y-3">
      <div v-for="item in items" :key="item.id" class="bg-white rounded-xl border border-gray-200 p-4 hover:border-blue-200 transition-colors">
        <div class="flex items-start gap-4">
          <!-- Type badge -->
          <div class="w-10 h-10 rounded-lg bg-blue-50 flex items-center justify-center shrink-0 text-blue-700 text-xs font-bold">
            {{ item.type === 'buku' ? '📚' : item.type === 'modul_ajar' ? '📝' : item.type === 'hki_paten' ? '🏅' : '🔬' }}
          </div>
          <div class="flex-1 min-w-0">
            <div class="flex items-start justify-between gap-2">
              <div class="flex-1 min-w-0">
                <p class="font-semibold text-gray-900 truncate">{{ item.title }}</p>
                <div class="flex items-center gap-2 mt-1 flex-wrap">
                  <span class="text-xs px-2 py-0.5 rounded bg-gray-100 text-gray-600">{{ TYPE_LABELS[item.type] ?? item.type }}</span>
                  <span class="text-xs text-gray-400">{{ item.year }}</span>
                  <span v-if="item.publisher" class="text-xs text-gray-400">· {{ item.publisher }}</span>
                  <!-- Dosen name (admin view) -->
                  <span v-if="isAdmin && item.lecturer" class="text-xs text-purple-600">· {{ item.lecturer.name }}</span>
                </div>
              </div>
              <span :class="['text-xs px-2.5 py-1 rounded-full font-medium shrink-0', STATUS_COLORS[item.status] ?? 'bg-gray-100 text-gray-600']">
                {{ STATUS_LABELS[item.status] ?? item.status }}
              </span>
            </div>
            <p v-if="item.description" class="text-xs text-gray-500 mt-1.5 line-clamp-2">{{ item.description }}</p>
          </div>
          <!-- Actions -->
          <div class="flex items-center gap-1 shrink-0">
            <button class="p-1.5 rounded-lg text-blue-600 hover:bg-blue-50" title="Lihat Detail" @click="router.push(`/karya-dosen/${item.id}`)">
              <EyeIcon class="w-4 h-4" />
            </button>
            <button v-if="isDosen && ['draft','revisi'].includes(item.status)" class="p-1.5 rounded-lg text-gray-500 hover:bg-gray-50" title="Edit" @click="router.push(`/karya-dosen/${item.id}/edit`)">
              <PencilIcon class="w-4 h-4" />
            </button>
            <button v-if="isDosen && ['draft','revisi'].includes(item.status)" class="p-1.5 rounded-lg text-red-500 hover:bg-red-50" title="Hapus" @click="deleteWork(item)">
              <TrashIcon class="w-4 h-4" />
            </button>
          </div>
        </div>
      </div>

      <!-- Pagination -->
      <div v-if="pagination.lastPage > 1" class="flex items-center justify-center gap-2 pt-2">
        <button v-for="p in pagination.lastPage" :key="p"
          :class="['w-8 h-8 rounded-lg text-xs font-medium transition-colors', p === pagination.currentPage ? 'bg-blue-600 text-white' : 'bg-gray-100 hover:bg-gray-200 text-gray-700']"
          @click="load(p)">{{ p }}</button>
      </div>
    </div>
  </div>
</template>
