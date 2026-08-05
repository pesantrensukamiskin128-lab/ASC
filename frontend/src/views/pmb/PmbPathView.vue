<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { PlusIcon, PencilIcon, TrashIcon } from '@heroicons/vue/24/outline'
import { useCrud } from '@/composables/useCrud'
import BaseModal from '@/components/ui/BaseModal.vue'

interface PathItem {
  id: number; code: string; name: string; description: string
  requirements: string; is_active: boolean
}

const { items, loading, fetchAll, create, update, remove } = useCrud<PathItem>('/pmb-paths')
const modalOpen = ref(false)
const editingId = ref<number | null>(null)
const saving = ref(false)

const form = reactive({
  code: '', name: '', description: '', requirements: '', is_active: true,
})

onMounted(() => fetchAll())

function openCreate() {
  editingId.value = null
  Object.assign(form, { code: '', name: '', description: '', requirements: '', is_active: true })
  modalOpen.value = true
}

function openEdit(item: PathItem) {
  editingId.value = item.id
  Object.assign(form, {
    code: item.code, name: item.name, description: item.description ?? '',
    requirements: item.requirements ?? '', is_active: item.is_active,
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

async function handleDelete(item: PathItem) {
  if (!confirm(`Hapus jalur "${item.name}"?`)) return
  await remove(item.id); fetchAll()
}
</script>

<template>
  <div class="space-y-5">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-xl font-bold text-gray-900">Jalur Seleksi PMB</h1>
        <p class="text-sm text-gray-500 mt-0.5">Kelola jalur pendaftaran (reguler, prestasi, khusus, dll)</p>
      </div>
      <button class="flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg" @click="openCreate">
        <PlusIcon class="w-4 h-4" /> Tambah Jalur
      </button>
    </div>

    <!-- Card List -->
    <div v-if="loading" class="text-center text-gray-400 py-12">Memuat...</div>
    <div v-else-if="items.length === 0" class="text-center text-gray-400 py-12">Belum ada jalur seleksi.</div>
    <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
      <div v-for="item in items" :key="item.id" class="bg-white rounded-xl border border-gray-200 p-5 hover:shadow-md transition-shadow">
        <div class="flex items-start justify-between">
          <div>
            <div class="flex items-center gap-2">
              <span class="text-xs font-mono bg-gray-100 text-gray-600 px-2 py-0.5 rounded">{{ item.code }}</span>
              <span :class="['inline-flex px-2 py-0.5 rounded-full text-xs font-medium', item.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500']">
                {{ item.is_active ? 'Aktif' : 'Nonaktif' }}
              </span>
            </div>
            <h3 class="mt-2 text-sm font-semibold text-gray-900">{{ item.name }}</h3>
            <p v-if="item.description" class="mt-1 text-xs text-gray-500 line-clamp-2">{{ item.description }}</p>
          </div>
          <div class="flex items-center gap-1 shrink-0">
            <button class="p-1.5 rounded-lg text-blue-600 hover:bg-blue-50" @click="openEdit(item)"><PencilIcon class="w-4 h-4" /></button>
            <button class="p-1.5 rounded-lg text-red-500 hover:bg-red-50" @click="handleDelete(item)"><TrashIcon class="w-4 h-4" /></button>
          </div>
        </div>
        <div v-if="item.requirements" class="mt-3 pt-3 border-t border-gray-100">
          <p class="text-xs text-gray-400 font-medium uppercase">Persyaratan</p>
          <p class="text-xs text-gray-600 mt-1 whitespace-pre-line">{{ item.requirements }}</p>
        </div>
      </div>
    </div>
  </div>

  <BaseModal :open="modalOpen" :title="editingId ? 'Edit Jalur' : 'Tambah Jalur'" @close="modalOpen = false">
    <form class="space-y-4" @submit.prevent="handleSave">
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Kode <span class="text-red-500">*</span></label>
          <input v-model="form.code" required placeholder="REG" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Nama Jalur <span class="text-red-500">*</span></label>
          <input v-model="form.name" required placeholder="Reguler" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
        </div>
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Deskripsi</label>
        <textarea v-model="form.description" rows="2" placeholder="Deskripsi singkat jalur seleksi..." class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Persyaratan</label>
        <textarea v-model="form.requirements" rows="3" placeholder="Persyaratan untuk mendaftar di jalur ini..." class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
      </div>
      <div class="flex items-center gap-2">
        <input v-model="form.is_active" type="checkbox" id="path_active" class="rounded" />
        <label for="path_active" class="text-sm text-gray-700">Aktifkan jalur ini</label>
      </div>
    </form>
    <template #footer>
      <button class="px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded-lg" @click="modalOpen = false">Batal</button>
      <button :disabled="saving" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 disabled:bg-blue-400 text-white text-sm font-medium rounded-lg" @click="handleSave">{{ saving ? 'Menyimpan...' : 'Simpan' }}</button>
    </template>
  </BaseModal>
</template>
