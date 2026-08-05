<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { PlusIcon, PencilIcon, TrashIcon, CheckCircleIcon, CalendarDaysIcon } from '@heroicons/vue/24/outline'
import { useCrud } from '@/composables/useCrud'
import { useToast } from 'vue-toastification'
import DataTable from '@/components/ui/DataTable.vue'
import BaseModal from '@/components/ui/BaseModal.vue'
import api from '@/services/api'

interface AcademicYear {
  id: number
  name: string
  start_date: string
  end_date: string
  is_active: boolean
  semesters_count?: number
}

const { items, loading, fetchAll, create, update, remove } = useCrud<AcademicYear>('/academic-years')
const toast       = useToast()
const modalOpen   = ref(false)
const editingId   = ref<number | null>(null)
const saving      = ref(false)

const form = reactive({
  name:       '',
  start_date: '',
  end_date:   '',
  is_active:  false,
})

const columns = [
  { key: 'name',     label: 'Nama Tahun Akademik' },
  { key: 'period',   label: 'Periode' },
  { key: 'semester', label: 'Semester' },
  { key: 'status',   label: 'Status' },
  { key: 'aksi',     label: 'Aksi', class: 'text-right' },
]

onMounted(() => fetchAll())

function openCreate() {
  editingId.value = null
  Object.assign(form, { name: '', start_date: '', end_date: '', is_active: false })
  modalOpen.value = true
}

function openEdit(item: AcademicYear) {
  editingId.value = item.id
  Object.assign(form, {
    name:       item.name,
    start_date: item.start_date ?? '',
    end_date:   item.end_date ?? '',
    is_active:  item.is_active,
  })
  modalOpen.value = true
}

async function handleSave() {
  saving.value = true
  try {
    editingId.value ? await update(editingId.value, form) : await create(form)
    modalOpen.value = false
    fetchAll()
  } catch { } finally { saving.value = false }
}

async function handleActivate(item: AcademicYear) {
  const { data } = await api.post(`/academic-years/${item.id}/activate`)
  toast.success(data.message)
  fetchAll()
}

async function handleDelete(item: AcademicYear) {
  if (!confirm(`Hapus "${item.name}"?`)) return
  await remove(item.id)
  fetchAll()
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
        <h1 class="text-xl font-bold text-gray-900">Tahun Akademik</h1>
        <p class="text-sm text-gray-500 mt-0.5">Kelola tahun akademik institusi</p>
      </div>
      <button
        class="flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition-colors"
        @click="openCreate"
      >
        <PlusIcon class="w-4 h-4" /> Tambah
      </button>
    </div>

    <DataTable :columns="columns" :rows="items" :loading="loading">
      <template #default="{ row }">
        <td class="px-4 py-3">
          <p class="font-medium text-gray-900">{{ row.name }}</p>
        </td>
        <td class="px-4 py-3 text-gray-500 text-sm">
          {{ formatDate(row.start_date) }} – {{ formatDate(row.end_date) }}
        </td>
        <td class="px-4 py-3">
          <span class="inline-flex items-center gap-1 text-xs text-gray-500">
            <CalendarDaysIcon class="w-3.5 h-3.5" />
            {{ row.semesters_count ?? 0 }} semester
          </span>
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
    :title="editingId ? 'Edit Tahun Akademik' : 'Tambah Tahun Akademik'"
    @close="modalOpen = false"
  >
    <form class="space-y-4" @submit.prevent="handleSave">
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">
          Nama <span class="text-red-500">*</span>
        </label>
        <input
          v-model="form.name"
          required
          placeholder="Tahun Akademik 2025/2026"
          class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
        />
      </div>
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">
            Tanggal Mulai <span class="text-red-500">*</span>
          </label>
          <input
            v-model="form.start_date"
            type="date"
            required
            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
          />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">
            Tanggal Selesai <span class="text-red-500">*</span>
          </label>
          <input
            v-model="form.end_date"
            type="date"
            required
            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
          />
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
