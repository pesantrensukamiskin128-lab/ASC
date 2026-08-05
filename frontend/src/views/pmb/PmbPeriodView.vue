<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { PlusIcon, PencilIcon, TrashIcon } from '@heroicons/vue/24/outline'
import { useCrud } from '@/composables/useCrud'
import DataTable from '@/components/ui/DataTable.vue'
import BaseModal from '@/components/ui/BaseModal.vue'
import api from '@/services/api'

interface AcademicYear { id: number; name: string }
interface Period {
  id: number; name: string; registration_start: string; registration_end: string
  selection_date: string; announcement_date: string; quota: number
  registration_fee: number; is_active: boolean; registrants_count: number
  academic_year?: AcademicYear
}

const { items, pagination, loading, fetchAll, create, update, remove } = useCrud<Period>('/pmb-periods')
const academicYears = ref<AcademicYear[]>([])
const modalOpen = ref(false); const editingId = ref<number | null>(null); const saving = ref(false)

const form = reactive({
  academic_year_id: '', name: '', registration_start: '', registration_end: '',
  selection_date: '', announcement_date: '', re_registration_start: '', re_registration_end: '',
  quota: 200, registration_fee: 250000, is_active: false,
})

const columns = [
  { key: 'name', label: 'Nama Gelombang' }, { key: 'year', label: 'Tahun Akademik' },
  { key: 'period', label: 'Periode Pendaftaran' }, { key: 'quota', label: 'Kuota' },
  { key: 'registrants', label: 'Pendaftar' }, { key: 'status', label: 'Status' },
  { key: 'aksi', label: 'Aksi', class: 'text-right' },
]

onMounted(async () => {
  fetchAll()
  const { data } = await api.get('/academic-years/all')
  academicYears.value = data
})

function openCreate() {
  editingId.value = null
  Object.assign(form, { academic_year_id: '', name: '', registration_start: '', registration_end: '', selection_date: '', announcement_date: '', re_registration_start: '', re_registration_end: '', quota: 200, registration_fee: 250000, is_active: false })
  modalOpen.value = true
}

function openEdit(item: Period) {
  editingId.value = item.id
  Object.assign(form, {
    academic_year_id: item.academic_year?.id ?? '', name: item.name,
    registration_start: item.registration_start, registration_end: item.registration_end,
    selection_date: item.selection_date ?? '', announcement_date: item.announcement_date ?? '',
    quota: item.quota, registration_fee: item.registration_fee, is_active: item.is_active,
  })
  modalOpen.value = true
}

async function handleSave() {
  saving.value = true
  try {
    editingId.value ? await update(editingId.value, form) : await create(form)
    modalOpen.value = false; fetchAll()
  } catch { } finally { saving.value = false }
}

async function handleDelete(item: Period) {
  if (!confirm(`Hapus "${item.name}"?`)) return
  await remove(item.id); fetchAll()
}

function formatDate(d: string) { return d ? new Date(d).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' }) : '-' }
function formatCurrency(n: number) { return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(n) }
</script>

<template>
  <div class="space-y-5">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-xl font-bold text-gray-900">Periode PMB</h1>
        <p class="text-sm text-gray-500 mt-0.5">Kelola gelombang pendaftaran mahasiswa baru</p>
      </div>
      <button class="flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg" @click="openCreate">
        <PlusIcon class="w-4 h-4" /> Tambah Gelombang
      </button>
    </div>

    <DataTable :columns="columns" :rows="items" :loading="loading" :total="pagination.total" :current-page="pagination.currentPage" :last-page="pagination.lastPage" @page-change="fetchAll">
      <template #default="{ row }">
        <td class="px-4 py-3 font-medium text-gray-900">{{ row.name }}</td>
        <td class="px-4 py-3 text-gray-600 text-sm">{{ row.academic_year?.name ?? '-' }}</td>
        <td class="px-4 py-3 text-gray-500 text-xs whitespace-nowrap">{{ formatDate(row.registration_start) }} – {{ formatDate(row.registration_end) }}</td>
        <td class="px-4 py-3 text-gray-700 font-medium text-center">{{ row.quota }}</td>
        <td class="px-4 py-3 text-center"><span class="inline-flex px-2 py-0.5 bg-blue-100 text-blue-700 rounded-full text-xs font-medium">{{ row.registrants_count }}</span></td>
        <td class="px-4 py-3"><span :class="['inline-flex px-2 py-0.5 rounded-full text-xs font-medium', row.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500']">{{ row.is_active ? 'Aktif' : 'Nonaktif' }}</span></td>
        <td class="px-4 py-3">
          <div class="flex items-center justify-end gap-1">
            <button class="p-1.5 rounded-lg text-blue-600 hover:bg-blue-50" @click="openEdit(row)"><PencilIcon class="w-4 h-4" /></button>
            <button class="p-1.5 rounded-lg text-red-500 hover:bg-red-50" @click="handleDelete(row)"><TrashIcon class="w-4 h-4" /></button>
          </div>
        </td>
      </template>
    </DataTable>
  </div>

  <BaseModal :open="modalOpen" :title="editingId ? 'Edit Periode' : 'Tambah Periode'" size="xl" @close="modalOpen = false">
    <form class="space-y-4" @submit.prevent="handleSave">
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Tahun Akademik <span class="text-red-500">*</span></label>
          <select v-model="form.academic_year_id" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            <option value="">-- Pilih --</option>
            <option v-for="y in academicYears" :key="y.id" :value="y.id">{{ y.name }}</option>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Nama Gelombang <span class="text-red-500">*</span></label>
          <input v-model="form.name" required placeholder="Gelombang 1" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
        </div>
      </div>
      <p class="text-xs font-semibold text-gray-400 uppercase">Periode Pendaftaran</p>
      <div class="grid grid-cols-2 gap-4">
        <div><label class="block text-sm font-medium text-gray-700 mb-1">Mulai <span class="text-red-500">*</span></label><input v-model="form.registration_start" type="date" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" /></div>
        <div><label class="block text-sm font-medium text-gray-700 mb-1">Selesai <span class="text-red-500">*</span></label><input v-model="form.registration_end" type="date" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" /></div>
      </div>
      <p class="text-xs font-semibold text-gray-400 uppercase">Jadwal Seleksi & Pengumuman</p>
      <div class="grid grid-cols-2 gap-4">
        <div><label class="block text-sm font-medium text-gray-700 mb-1">Tanggal Seleksi</label><input v-model="form.selection_date" type="date" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" /></div>
        <div><label class="block text-sm font-medium text-gray-700 mb-1">Tanggal Pengumuman</label><input v-model="form.announcement_date" type="date" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" /></div>
      </div>
      <p class="text-xs font-semibold text-gray-400 uppercase">Kuota & Biaya</p>
      <div class="grid grid-cols-2 gap-4">
        <div><label class="block text-sm font-medium text-gray-700 mb-1">Kuota</label><input v-model="form.quota" type="number" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" /></div>
        <div><label class="block text-sm font-medium text-gray-700 mb-1">Biaya Pendaftaran (Rp)</label><input v-model="form.registration_fee" type="number" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" /></div>
      </div>
      <div class="flex items-center gap-2">
        <input v-model="form.is_active" type="checkbox" id="period_active" class="rounded" />
        <label for="period_active" class="text-sm text-gray-700">Aktifkan periode ini</label>
      </div>
    </form>
    <template #footer>
      <button class="px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded-lg" @click="modalOpen = false">Batal</button>
      <button :disabled="saving" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 disabled:bg-blue-400 text-white text-sm font-medium rounded-lg" @click="handleSave">{{ saving ? 'Menyimpan...' : 'Simpan' }}</button>
    </template>
  </BaseModal>
</template>
