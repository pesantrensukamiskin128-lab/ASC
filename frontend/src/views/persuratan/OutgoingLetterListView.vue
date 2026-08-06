<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useRouter } from 'vue-router'
import { PlusIcon, EyeIcon, PencilIcon, TrashIcon } from '@heroicons/vue/24/outline'
import { useAuthStore } from '@/stores/auth'
import { useCrud } from '@/composables/useCrud'
import { useToast } from 'vue-toastification'
import api from '@/services/api'

const router = useRouter()
const auth = useAuthStore()
const toast = useToast()
const canCreate = auth.hasPermission('surat-keluar.create')

const { items, pagination, loading, fetchAll, remove } = useCrud<any>('/outgoing-letters')
const search = ref('')
const filterStatus = ref('')

const statuses = ['DRAFT', 'MENUNGGU_PEMERIKSA', 'MENUNGGU_PENANDATANGAN', 'REVISI_PEMERIKSA', 'REVISI_PENANDATANGAN', 'DITANDATANGANI', 'TERKIRIM']

const statusColor: Record<string, string> = {
  DRAFT: 'bg-gray-100 text-gray-600',
  MENUNGGU_PEMERIKSA: 'bg-yellow-100 text-yellow-700',
  MENUNGGU_PENANDATANGAN: 'bg-blue-100 text-blue-700',
  REVISI_PEMERIKSA: 'bg-red-100 text-red-600',
  REVISI_PENANDATANGAN: 'bg-red-100 text-red-600',
  DITANDATANGANI: 'bg-green-100 text-green-700',
  TERKIRIM: 'bg-emerald-100 text-emerald-700',
}

const statusLabel: Record<string, string> = {
  DRAFT: 'Draft',
  MENUNGGU_PEMERIKSA: 'Menunggu Pemeriksa',
  MENUNGGU_PENANDATANGAN: 'Menunggu TTD',
  REVISI_PEMERIKSA: 'Revisi (Pemeriksa)',
  REVISI_PENANDATANGAN: 'Revisi (Penandatangan)',
  DITANDATANGANI: 'Ditandatangani',
  TERKIRIM: 'Terkirim',
}

onMounted(() => load())

function load(page = 1) {
  fetchAll({ search: search.value, status: filterStatus.value, page })
}

async function handleDelete(item: any) {
  if (!confirm(`Hapus surat "${item.subject}"?`)) return
  await remove(item.id)
  load()
}
</script>

<template>
  <div class="space-y-5">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-xl font-bold text-gray-900">Surat Keluar</h1>
        <p class="text-sm text-gray-500 mt-0.5">Kelola surat keluar dengan alur tanda tangan elektronik</p>
      </div>
      <button v-if="canCreate" class="flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg" @click="router.push('/persuratan/surat-keluar/buat')">
        <PlusIcon class="w-4 h-4" /> Buat Surat
      </button>
    </div>

    <div class="flex flex-wrap gap-3">
      <input v-model="search" type="text" placeholder="Cari perihal..." class="px-3.5 py-2 rounded-lg border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 w-56" @input="load()" />
      <select v-model="filterStatus" class="px-3.5 py-2 rounded-lg border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" @change="load()">
        <option value="">Semua Status</option>
        <option v-for="s in statuses" :key="s" :value="s">{{ statusLabel[s] }}</option>
      </select>
    </div>

    <div v-if="loading" class="text-center text-gray-400 py-12">Memuat...</div>
    <div v-else-if="items.length === 0" class="text-center text-gray-400 py-12">Belum ada surat keluar.</div>
    <div v-else class="bg-white rounded-xl border border-gray-200 overflow-hidden">
      <table class="w-full text-sm">
        <thead>
          <tr class="bg-gray-50 text-left text-xs text-gray-500 uppercase tracking-wide">
            <th class="px-4 py-3">No. Surat</th>
            <th class="px-4 py-3">Perihal</th>
            <th class="px-4 py-3">Tanggal</th>
            <th class="px-4 py-3">Penandatangan</th>
            <th class="px-4 py-3 text-center">Status</th>
            <th class="px-4 py-3 text-right">Aksi</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="item in items" :key="item.id" class="border-t border-gray-100 hover:bg-gray-50">
            <td class="px-4 py-3 font-mono text-xs text-gray-600">{{ item.letter_number || '-' }}</td>
            <td class="px-4 py-3 font-medium text-gray-900">
              <div class="max-w-xs truncate">{{ item.subject }}</div>
              <div class="text-xs text-gray-400 mt-0.5">{{ item.letter_type?.name }}</div>
            </td>
            <td class="px-4 py-3 text-gray-600 text-xs">{{ item.letter_date }}</td>
            <td class="px-4 py-3 text-xs text-gray-600">{{ item.signer?.name ?? '-' }}</td>
            <td class="px-4 py-3 text-center">
              <span :class="['px-2 py-0.5 rounded-full text-xs font-medium', statusColor[item.status]]">
                {{ statusLabel[item.status] }}
              </span>
            </td>
            <td class="px-4 py-3">
              <div class="flex items-center justify-end gap-1">
                <button class="p-1.5 rounded-lg text-blue-600 hover:bg-blue-50" @click="router.push(`/persuratan/surat-keluar/${item.id}`)">
                  <EyeIcon class="w-4 h-4" />
                </button>
                <button v-if="canCreate && ['DRAFT','REVISI_PEMERIKSA','REVISI_PENANDATANGAN'].includes(item.status)" class="p-1.5 rounded-lg text-amber-600 hover:bg-amber-50" @click="router.push(`/persuratan/surat-keluar/${item.id}/edit`)">
                  <PencilIcon class="w-4 h-4" />
                </button>
                <button v-if="canCreate && ['DRAFT','REVISI_PEMERIKSA','REVISI_PENANDATANGAN'].includes(item.status)" class="p-1.5 rounded-lg text-red-500 hover:bg-red-50" @click="handleDelete(item)">
                  <TrashIcon class="w-4 h-4" />
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <div v-if="pagination.lastPage > 1" class="flex justify-center gap-2">
      <button v-for="p in pagination.lastPage" :key="p" :class="['px-3 py-1 rounded text-sm', p === pagination.currentPage ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200']" @click="load(p)">{{ p }}</button>
    </div>
  </div>
</template>
