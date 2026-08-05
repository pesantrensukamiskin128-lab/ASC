<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { PlusIcon, PencilIcon, TrashIcon } from '@heroicons/vue/24/outline'
import { useCrud } from '@/composables/useCrud'
import DataTable from '@/components/ui/DataTable.vue'
import BaseModal from '@/components/ui/BaseModal.vue'
import api from '@/services/api'

interface Building { id: number; name: string; code: string }
interface Room {
  id: number; code: string; name: string; floor: number
  capacity: number; type: string; status: boolean; building?: Building
  facilities?: string[]
}

const { items, pagination, loading, fetchAll, create, update, remove } = useCrud<Room>('/rooms')
const buildings = ref<Building[]>([])
const search = ref(''); const filterBuilding = ref(''); const filterType = ref('')
const modalOpen = ref(false); const editingId = ref<number | null>(null); const saving = ref(false)
const facilitiesInput = ref('')

const form = reactive({
  building_id: '', code: '', name: '', floor: 1,
  capacity: 40, type: 'Kelas', facilities: [] as string[], status: true,
})

const roomTypes = ['Kelas', 'Lab', 'Aula', 'Seminar', 'Kantor', 'Lainnya']
const typeColor: Record<string, string> = {
  Kelas: 'bg-blue-100 text-blue-700', Lab: 'bg-green-100 text-green-700',
  Aula: 'bg-purple-100 text-purple-700', Seminar: 'bg-yellow-100 text-yellow-700',
  Kantor: 'bg-gray-100 text-gray-600', Lainnya: 'bg-orange-100 text-orange-700',
}

const columns = [
  { key: 'code', label: 'Kode' }, { key: 'name', label: 'Nama Ruangan' },
  { key: 'building', label: 'Gedung' }, { key: 'floor', label: 'Lantai' },
  { key: 'capacity', label: 'Kapasitas' }, { key: 'type', label: 'Tipe' },
  { key: 'status', label: 'Status' }, { key: 'aksi', label: 'Aksi', class: 'text-right' },
]

onMounted(async () => {
  load()
  const { data } = await api.get('/buildings/all')
  buildings.value = data
})

async function load(page = 1) {
  await fetchAll({ search: search.value, building_id: filterBuilding.value, type: filterType.value, page })
}

function openCreate() {
  editingId.value = null
  facilitiesInput.value = ''
  Object.assign(form, { building_id: '', code: '', name: '', floor: 1, capacity: 40, type: 'Kelas', facilities: [], status: true })
  modalOpen.value = true
}

function openEdit(item: Room) {
  editingId.value = item.id
  facilitiesInput.value = (item.facilities ?? []).join(', ')
  Object.assign(form, { building_id: item.building?.id ?? '', code: item.code, name: item.name, floor: item.floor, capacity: item.capacity, type: item.type, facilities: item.facilities ?? [], status: item.status })
  modalOpen.value = true
}

async function handleSave() {
  saving.value = true
  // Parse fasilitas dari input teks
  form.facilities = facilitiesInput.value.split(',').map(s => s.trim()).filter(Boolean)
  try {
    editingId.value ? await update(editingId.value, form) : await create(form)
    modalOpen.value = false; load()
  } catch { } finally { saving.value = false }
}

async function handleDelete(item: Room) {
  if (!confirm(`Hapus ruangan "${item.name}"?`)) return
  await remove(item.id); load()
}
</script>

<template>
  <div class="space-y-5">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-xl font-bold text-gray-900">Ruangan</h1>
        <p class="text-sm text-gray-500 mt-0.5">Kelola data ruang kelas, lab, dan fasilitas</p>
      </div>
      <button class="flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition-colors" @click="openCreate">
        <PlusIcon class="w-4 h-4" /> Tambah Ruangan
      </button>
    </div>
    <div class="flex flex-wrap gap-3">
      <input v-model="search" type="text" placeholder="Cari kode atau nama..." class="px-3.5 py-2 rounded-lg border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 w-56" @input="load()" />
      <select v-model="filterBuilding" class="px-3.5 py-2 rounded-lg border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" @change="load()">
        <option value="">Semua Gedung</option>
        <option v-for="b in buildings" :key="b.id" :value="b.id">{{ b.code }} - {{ b.name }}</option>
      </select>
      <select v-model="filterType" class="px-3.5 py-2 rounded-lg border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" @change="load()">
        <option value="">Semua Tipe</option>
        <option v-for="t in roomTypes" :key="t" :value="t">{{ t }}</option>
      </select>
    </div>

    <DataTable :columns="columns" :rows="items" :loading="loading" :total="pagination.total" :current-page="pagination.currentPage" :last-page="pagination.lastPage" @page-change="load">
      <template #default="{ row }">
        <td class="px-4 py-3 font-mono text-xs text-gray-600">{{ row.code }}</td>
        <td class="px-4 py-3 font-medium text-gray-900">{{ row.name }}</td>
        <td class="px-4 py-3 text-gray-600 text-xs">{{ row.building?.name ?? '-' }}</td>
        <td class="px-4 py-3 text-center text-gray-600">{{ row.floor }}</td>
        <td class="px-4 py-3 text-center text-gray-700 font-medium">{{ row.capacity }}</td>
        <td class="px-4 py-3"><span :class="['inline-flex px-2 py-0.5 rounded-full text-xs font-medium', typeColor[row.type]]">{{ row.type }}</span></td>
        <td class="px-4 py-3"><span :class="['inline-flex px-2 py-0.5 rounded-full text-xs font-medium', row.status ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500']">{{ row.status ? 'Aktif' : 'Nonaktif' }}</span></td>
        <td class="px-4 py-3">
          <div class="flex items-center justify-end gap-1">
            <button class="p-1.5 rounded-lg text-blue-600 hover:bg-blue-50" @click="openEdit(row)"><PencilIcon class="w-4 h-4" /></button>
            <button class="p-1.5 rounded-lg text-red-500 hover:bg-red-50" @click="handleDelete(row)"><TrashIcon class="w-4 h-4" /></button>
          </div>
        </td>
      </template>
    </DataTable>
  </div>

  <BaseModal :open="modalOpen" :title="editingId ? 'Edit Ruangan' : 'Tambah Ruangan'" size="lg" @close="modalOpen = false">
    <form class="space-y-4" @submit.prevent="handleSave">
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Gedung <span class="text-red-500">*</span></label>
        <select v-model="form.building_id" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
          <option value="">-- Pilih Gedung --</option>
          <option v-for="b in buildings" :key="b.id" :value="b.id">{{ b.code }} - {{ b.name }}</option>
        </select>
      </div>
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Kode <span class="text-red-500">*</span></label>
          <input v-model="form.code" required placeholder="R-101" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Tipe <span class="text-red-500">*</span></label>
          <select v-model="form.type" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            <option v-for="t in roomTypes" :key="t" :value="t">{{ t }}</option>
          </select>
        </div>
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Nama Ruangan <span class="text-red-500">*</span></label>
        <input v-model="form.name" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
      </div>
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Lantai</label>
          <input v-model="form.floor" type="number" min="1" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Kapasitas (orang)</label>
          <input v-model="form.capacity" type="number" min="1" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
        </div>
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Fasilitas</label>
        <input v-model="facilitiesInput" placeholder="AC, Proyektor, Whiteboard (pisahkan dengan koma)" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
        <select v-model="form.status" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
          <option :value="true">Aktif</option><option :value="false">Nonaktif</option>
        </select>
      </div>
    </form>
    <template #footer>
      <button class="px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded-lg" @click="modalOpen = false">Batal</button>
      <button :disabled="saving" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 disabled:bg-blue-400 text-white text-sm font-medium rounded-lg" @click="handleSave">{{ saving ? 'Menyimpan...' : 'Simpan' }}</button>
    </template>
  </BaseModal>
</template>
