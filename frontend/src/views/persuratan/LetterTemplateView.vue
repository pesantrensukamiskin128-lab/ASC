<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { PlusIcon, PencilIcon, TrashIcon, DocumentDuplicateIcon } from '@heroicons/vue/24/outline'
import { useCrud } from '@/composables/useCrud'
import { useToast } from 'vue-toastification'
import BaseModal from '@/components/ui/BaseModal.vue'
import RichTextEditor from '@/components/ui/RichTextEditor.vue'
import api from '@/services/api'

const toast = useToast()
const { items, pagination, loading, fetchAll, create, update, remove } = useCrud<any>('/letter-templates')
const search = ref('')
const modalOpen = ref(false)
const editingId = ref<number | null>(null)
const saving = ref(false)
const letterTypes = ref<any[]>([])

const form = reactive({
  name: '', description: '', letter_type_id: '',
  subject: '', recipient: '', attachment_note: '',
  city: 'Bandung', body: '', appendix_body: '', is_shared: true,
})

onMounted(async () => {
  load()
  const { data } = await api.get('/outgoing-letters/letter-types')
  letterTypes.value = data
})

function load(page = 1) { fetchAll({ search: search.value, page }) }

function openCreate() {
  editingId.value = null
  Object.assign(form, { name: '', description: '', letter_type_id: '', subject: '', recipient: '', attachment_note: '', city: 'Bandung', body: '', appendix_body: '', is_shared: true })
  modalOpen.value = true
}

function openEdit(item: any) {
  editingId.value = item.id
  Object.assign(form, {
    name: item.name, description: item.description ?? '', letter_type_id: item.letter_type_id ?? '',
    subject: item.subject ?? '', recipient: item.recipient ?? '', attachment_note: item.attachment_note ?? '',
    city: item.city ?? 'Bandung', body: item.body, appendix_body: item.appendix_body ?? '', is_shared: item.is_shared,
  })
  modalOpen.value = true
}

async function handleSave() {
  saving.value = true
  try {
    const payload = { ...form }
    if (!payload.letter_type_id) delete (payload as any).letter_type_id
    editingId.value ? await update(editingId.value, payload) : await create(payload)
    modalOpen.value = false
    load()
  } catch {} finally { saving.value = false }
}

async function handleDelete(item: any) {
  if (!confirm(`Hapus template "${item.name}"?`)) return
  await remove(item.id); load()
}

function copyToClipboard(item: any) {
  // Emit event yang bisa digunakan oleh form surat
  toast.success(`Template "${item.name}" siap digunakan. Buka form Buat Surat dan pilih template.`)
}
</script>

<template>
  <div class="space-y-5">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-xl font-bold text-gray-900">Template Surat</h1>
        <p class="text-sm text-gray-500 mt-0.5">Simpan dan gunakan ulang format surat yang sering dipakai</p>
      </div>
      <button class="flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg" @click="openCreate">
        <PlusIcon class="w-4 h-4" /> Buat Template
      </button>
    </div>

    <input v-model="search" type="text" placeholder="Cari template..." class="px-3.5 py-2 rounded-lg border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 w-64" @input="load()" />

    <div v-if="loading" class="text-center text-gray-400 py-12">Memuat...</div>
    <div v-else-if="items.length === 0" class="text-center text-gray-400 py-12">Belum ada template.</div>
    <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
      <div v-for="item in items" :key="item.id" class="bg-white rounded-xl border border-gray-200 p-5 hover:shadow-md transition-shadow">
        <div class="flex items-start justify-between mb-2">
          <div>
            <h3 class="text-sm font-semibold text-gray-900">{{ item.name }}</h3>
            <p v-if="item.letter_type" class="text-[10px] text-gray-400 mt-0.5">{{ item.letter_type.code }} - {{ item.letter_type.name }}</p>
          </div>
          <div class="flex items-center gap-1">
            <button class="p-1 rounded text-blue-600 hover:bg-blue-50" @click="openEdit(item)" title="Edit"><PencilIcon class="w-3.5 h-3.5" /></button>
            <button class="p-1 rounded text-red-500 hover:bg-red-50" @click="handleDelete(item)" title="Hapus"><TrashIcon class="w-3.5 h-3.5" /></button>
          </div>
        </div>
        <p v-if="item.description" class="text-xs text-gray-500 line-clamp-2 mb-2">{{ item.description }}</p>
        <p v-if="item.subject" class="text-xs text-gray-600"><span class="text-gray-400">Perihal:</span> {{ item.subject }}</p>
        <div class="mt-3 pt-3 border-t border-gray-100 flex items-center justify-between">
          <span class="text-[10px] text-gray-400">{{ item.creator?.name }}</span>
          <span :class="['px-2 py-0.5 rounded-full text-[10px] font-medium', item.is_shared ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500']">
            {{ item.is_shared ? 'Publik' : 'Pribadi' }}
          </span>
        </div>
      </div>
    </div>

    <div v-if="pagination.lastPage > 1" class="flex justify-center gap-2">
      <button v-for="p in pagination.lastPage" :key="p" :class="['px-3 py-1 rounded text-sm', p === pagination.currentPage ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200']" @click="load(p)">{{ p }}</button>
    </div>
  </div>

  <!-- Modal Template -->
  <BaseModal :open="modalOpen" :title="editingId ? 'Edit Template' : 'Buat Template'" size="xl" @close="modalOpen = false">
    <form class="space-y-4" @submit.prevent="handleSave">
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Nama Template <span class="text-red-500">*</span></label>
          <input v-model="form.name" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" placeholder="cth: Undangan Rapat" />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Jenis Surat</label>
          <select v-model="form.letter_type_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
            <option value="">-- Semua Jenis --</option>
            <option v-for="t in letterTypes" :key="t.id" :value="t.id">{{ t.code }} - {{ t.name }}</option>
          </select>
        </div>
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Deskripsi</label>
        <input v-model="form.description" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" placeholder="Deskripsi singkat..." />
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Perihal (default)</label>
        <input v-model="form.subject" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" />
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Isi Surat <span class="text-red-500">*</span></label>
        <RichTextEditor v-model="form.body" min-height="200px" />
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Isi Lampiran (opsional)</label>
        <RichTextEditor v-model="form.appendix_body" min-height="120px" placeholder="Isi lampiran jika ada..." />
      </div>
      <div class="flex items-center gap-2">
        <input v-model="form.is_shared" type="checkbox" id="tpl_shared" class="rounded" />
        <label for="tpl_shared" class="text-sm text-gray-700">Template ini bisa digunakan semua user</label>
      </div>
    </form>
    <template #footer>
      <button class="px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded-lg" @click="modalOpen = false">Batal</button>
      <button :disabled="saving" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 disabled:bg-blue-400 text-white text-sm font-medium rounded-lg" @click="handleSave">{{ saving ? 'Menyimpan...' : 'Simpan' }}</button>
    </template>
  </BaseModal>
</template>
