<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { PlusIcon, PencilIcon, TrashIcon, CheckCircleIcon } from '@heroicons/vue/24/outline'
import { useCrud } from '@/composables/useCrud'
import { useToast } from 'vue-toastification'
import DataTable from '@/components/ui/DataTable.vue'
import BaseModal from '@/components/ui/BaseModal.vue'
import api from '@/services/api'

interface AcademicYear { id: number; name: string }
interface Semester {
  id: number
  name: string
  type: string
  start_date: string
  end_date: string
  krs_start: string; krs_end: string
  exam_mid_start: string; exam_mid_end: string
  exam_final_start: string; exam_final_end: string
  is_active: boolean
  academic_year?: AcademicYear
}

const { items, pagination, loading, fetchAll, create, update, remove } = useCrud<Semester>('/semesters')
const toast         = useToast()
const academicYears = ref<AcademicYear[]>([])
const filterYear    = ref('')
const modalOpen     = ref(false)
const editingId     = ref<number | null>(null)
const saving        = ref(false)

const form = reactive({
  academic_year_id: '',
  name:             '',
  type:             'Ganjil',
  start_date:       '',
  end_date:         '',
  krs_start:        '',
  krs_end:          '',
  exam_mid_start:   '',
  exam_mid_end:     '',
  exam_final_start: '',
  exam_final_end:   '',
})

const columns = [
  { key: 'name',   label: 'Nama Semester' },
  { key: 'type',   label: 'Tipe' },
  { key: 'year',   label: 'Tahun Akademik' },
  { key: 'period', label: 'Periode' },
  { key: 'krs',    label: 'Periode KRS' },
  { key: 'status', label: 'Status' },
  { key: 'aksi',   label: 'Aksi', class: 'text-right' },
]

const typeColor: Record<string, string> = {
  Ganjil: 'bg-blue-100 text-blue-700',
  Genap:  'bg-purple-100 text-purple-700',
  Pendek: 'bg-orange-100 text-orange-700',
}

onMounted(async () => {
  load()
  const { data } = await api.get('/academic-years/all')
  academicYears.value = data
})

async function load(page = 1) {
  await fetchAll({ academic_year_id: filterYear.value, page })
}

function resetForm() {
  Object.assign(form, {
    academic_year_id: '', name: '', type: 'Ganjil',
    start_date: '', end_date: '',
    krs_start: '', krs_end: '',
    exam_mid_start: '', exam_mid_end: '',
    exam_final_start: '', exam_final_end: '',
  })
}

function openCreate() {
  editingId.value = null
  resetForm()
  modalOpen.value = true
}

function openEdit(item: Semester) {
  editingId.value = item.id
  Object.assign(form, {
    academic_year_id: item.academic_year?.id ?? '',
    name:             item.name,
    type:             item.type,
    start_date:       item.start_date ?? '',
    end_date:         item.end_date ?? '',
    krs_start:        item.krs_start ?? '',
    krs_end:          item.krs_end ?? '',
    exam_mid_start:   item.exam_mid_start ?? '',
    exam_mid_end:     item.exam_mid_end ?? '',
    exam_final_start: item.exam_final_start ?? '',
    exam_final_end:   item.exam_final_end ?? '',
  })
  modalOpen.value = true
}

async function handleSave() {
  saving.value = true
  try {
    editingId.value ? await update(editingId.value, form) : await create(form)
    modalOpen.value = false
    load()
  } catch { } finally { saving.value = false }
}

async function handleActivate(item: Semester) {
  const { data } = await api.post(`/semesters/${item.id}/activate`)
  toast.success(data.message)
  load()
}

async function handleDelete(item: Semester) {
  if (!confirm('Hapus semester ini?')) return
  await remove(item.id)
  load()
}

function formatDate(d: string) {
  if (!d) return '-'
  return new Date(d).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' })
}
</script>

<template>
  <div class="space-y-5">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-xl font-bold text-gray-900">Semester</h1>
        <p class="text-sm text-gray-500 mt-0.5">Kelola periode dan jadwal semester</p>
      </div>
      <button
        class="flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition-colors"
        @click="openCreate"
      >
        <PlusIcon class="w-4 h-4" /> Tambah Semester
      </button>
    </div>

    <!-- Filter -->
    <select
      v-model="filterYear"
      class="px-3.5 py-2 rounded-lg border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
      @change="load()"
    >
      <option value="">Semua Tahun Akademik</option>
      <option v-for="y in academicYears" :key="y.id" :value="y.id">{{ y.name }}</option>
    </select>

    <DataTable
      :columns="columns" :rows="items" :loading="loading"
      :total="pagination.total" :current-page="pagination.currentPage"
      :last-page="pagination.lastPage" @page-change="load"
    >
      <template #default="{ row }">
        <td class="px-4 py-3 font-medium text-gray-900">{{ row.name }}</td>
        <td class="px-4 py-3">
          <span :class="['inline-flex px-2 py-0.5 rounded-full text-xs font-medium', typeColor[row.type] ?? 'bg-gray-100 text-gray-600']">
            {{ row.type }}
          </span>
        </td>
        <td class="px-4 py-3 text-gray-600 text-sm">{{ row.academic_year?.name ?? '-' }}</td>
        <td class="px-4 py-3 text-gray-500 text-xs whitespace-nowrap">
          {{ formatDate(row.start_date) }} – {{ formatDate(row.end_date) }}
        </td>
        <td class="px-4 py-3 text-gray-500 text-xs whitespace-nowrap">
          {{ row.krs_start ? `${formatDate(row.krs_start)} – ${formatDate(row.krs_end)}` : '-' }}
        </td>
        <td class="px-4 py-3">
          <span :class="['inline-flex px-2 py-0.5 rounded-full text-xs font-medium',
            row.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500']">
            {{ row.is_active ? 'Aktif' : 'Tidak Aktif' }}
          </span>
        </td>
        <td class="px-4 py-3">
          <div class="flex items-center justify-end gap-1">
            <button
              v-if="!row.is_active"
              class="p-1.5 rounded-lg text-green-600 hover:bg-green-50"
              title="Aktifkan"
              @click="handleActivate(row)"
            >
              <CheckCircleIcon class="w-4 h-4" />
            </button>
            <button class="p-1.5 rounded-lg text-blue-600 hover:bg-blue-50" @click="openEdit(row)">
              <PencilIcon class="w-4 h-4" />
            </button>
            <button class="p-1.5 rounded-lg text-red-500 hover:bg-red-50" @click="handleDelete(row)">
              <TrashIcon class="w-4 h-4" />
            </button>
          </div>
        </td>
      </template>
    </DataTable>
  </div>

  <BaseModal
    :open="modalOpen"
    :title="editingId ? 'Edit Semester' : 'Tambah Semester'"
    size="xl"
    @close="modalOpen = false"
  >
    <form class="space-y-4" @submit.prevent="handleSave">
      <!-- Baris 1: Tahun Akademik + Tipe -->
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">
            Tahun Akademik <span class="text-red-500">*</span>
          </label>
          <select v-model="form.academic_year_id" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            <option value="">-- Pilih --</option>
            <option v-for="y in academicYears" :key="y.id" :value="y.id">{{ y.name }}</option>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">
            Tipe <span class="text-red-500">*</span>
          </label>
          <select v-model="form.type" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            <option v-for="t in ['Ganjil','Genap','Pendek']" :key="t" :value="t">{{ t }}</option>
          </select>
        </div>
      </div>

      <!-- Nama semester -->
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">
          Nama Semester <span class="text-red-500">*</span>
        </label>
        <input
          v-model="form.name"
          required
          placeholder="contoh: Ganjil 2025/2026"
          class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
        />
      </div>

      <!-- Periode semester -->
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">
            Tanggal Mulai <span class="text-red-500">*</span>
          </label>
          <input v-model="form.start_date" type="date" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">
            Tanggal Selesai <span class="text-red-500">*</span>
          </label>
          <input v-model="form.end_date" type="date" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
        </div>
      </div>

      <!-- Periode KRS -->
      <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide pt-1">Periode KRS</p>
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Mulai KRS</label>
          <input v-model="form.krs_start" type="date" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Selesai KRS</label>
          <input v-model="form.krs_end" type="date" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
        </div>
      </div>

      <!-- Periode Ujian -->
      <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide pt-1">Periode Ujian</p>
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">UTS Mulai</label>
          <input v-model="form.exam_mid_start" type="date" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">UTS Selesai</label>
          <input v-model="form.exam_mid_end" type="date" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">UAS Mulai</label>
          <input v-model="form.exam_final_start" type="date" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">UAS Selesai</label>
          <input v-model="form.exam_final_end" type="date" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
        </div>
      </div>
    </form>

    <template #footer>
      <button class="px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded-lg" @click="modalOpen = false">
        Batal
      </button>
      <button
        :disabled="saving"
        class="px-4 py-2 bg-blue-600 hover:bg-blue-700 disabled:bg-blue-400 text-white text-sm font-medium rounded-lg"
        @click="handleSave"
      >
        {{ saving ? 'Menyimpan...' : 'Simpan' }}
      </button>
    </template>
  </BaseModal>
</template>
