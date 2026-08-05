<script setup lang="ts">
import { ref, reactive, onMounted, computed } from 'vue'
import { PlusIcon, PencilIcon, TrashIcon, ExclamationTriangleIcon } from '@heroicons/vue/24/outline'
import { useCrud } from '@/composables/useCrud'
import BaseModal from '@/components/ui/BaseModal.vue'

interface ExamType {
  id: number; code: string; name: string
  weight: number; passing_grade: number; is_active: boolean
}

const { items, loading, fetchAll, create, update, remove } = useCrud<ExamType>('/pmb-exam-types')
const modalOpen = ref(false)
const editingId = ref<number | null>(null)
const saving = ref(false)

const form = reactive({
  code: '', name: '', weight: 25, passing_grade: 60, is_active: true,
})

onMounted(() => fetchAll())

const totalWeight = computed(() => items.value.reduce((sum, item) => sum + (item.weight ?? 0), 0))
const weightWarning = computed(() => totalWeight.value !== 100 && items.value.length > 0)

function openCreate() {
  editingId.value = null
  Object.assign(form, { code: '', name: '', weight: 25, passing_grade: 60, is_active: true })
  modalOpen.value = true
}

function openEdit(item: ExamType) {
  editingId.value = item.id
  Object.assign(form, {
    code: item.code, name: item.name,
    weight: item.weight, passing_grade: item.passing_grade, is_active: item.is_active,
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

async function handleDelete(item: ExamType) {
  if (!confirm(`Hapus jenis ujian "${item.name}"?`)) return
  await remove(item.id); fetchAll()
}
</script>

<template>
  <div class="space-y-5">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-xl font-bold text-gray-900">Jenis Ujian Seleksi PMB</h1>
        <p class="text-sm text-gray-500 mt-0.5">Kelola komponen ujian seleksi dan bobotnya</p>
      </div>
      <button class="flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg" @click="openCreate">
        <PlusIcon class="w-4 h-4" /> Tambah Jenis Ujian
      </button>
    </div>

    <!-- Weight Warning -->
    <div v-if="weightWarning" class="p-3 bg-yellow-50 border border-yellow-200 rounded-lg flex items-center gap-2">
      <ExclamationTriangleIcon class="w-5 h-5 text-yellow-500 shrink-0" />
      <p class="text-sm text-yellow-700">
        Total bobot saat ini <strong>{{ totalWeight }}%</strong>. Sebaiknya total bobot = 100% agar perhitungan akurat.
      </p>
    </div>

    <!-- Table -->
    <div v-if="loading" class="text-center text-gray-400 py-12">Memuat...</div>
    <div v-else-if="items.length === 0" class="text-center text-gray-400 py-12">Belum ada jenis ujian.</div>
    <div v-else class="bg-white rounded-xl border border-gray-200 overflow-hidden">
      <table class="w-full text-sm">
        <thead>
          <tr class="bg-gray-50 text-left text-xs text-gray-500 uppercase tracking-wide">
            <th class="px-4 py-3">Kode</th>
            <th class="px-4 py-3">Nama Ujian</th>
            <th class="px-4 py-3 text-center">Bobot (%)</th>
            <th class="px-4 py-3 text-center">KKM</th>
            <th class="px-4 py-3 text-center">Status</th>
            <th class="px-4 py-3 text-right">Aksi</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="item in items" :key="item.id" class="border-t border-gray-100 hover:bg-gray-50">
            <td class="px-4 py-3 font-mono text-xs text-gray-600">{{ item.code }}</td>
            <td class="px-4 py-3 font-medium text-gray-900">{{ item.name }}</td>
            <td class="px-4 py-3 text-center">
              <span class="inline-flex px-2.5 py-0.5 bg-blue-100 text-blue-700 rounded-full text-xs font-semibold">
                {{ item.weight }}%
              </span>
            </td>
            <td class="px-4 py-3 text-center text-gray-600">{{ item.passing_grade }}</td>
            <td class="px-4 py-3 text-center">
              <span :class="['inline-flex px-2 py-0.5 rounded-full text-xs font-medium', item.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500']">
                {{ item.is_active ? 'Aktif' : 'Nonaktif' }}
              </span>
            </td>
            <td class="px-4 py-3">
              <div class="flex items-center justify-end gap-1">
                <button class="p-1.5 rounded-lg text-blue-600 hover:bg-blue-50" @click="openEdit(item)"><PencilIcon class="w-4 h-4" /></button>
                <button class="p-1.5 rounded-lg text-red-500 hover:bg-red-50" @click="handleDelete(item)"><TrashIcon class="w-4 h-4" /></button>
              </div>
            </td>
          </tr>
        </tbody>
        <tfoot>
          <tr class="border-t border-gray-200 bg-gray-50">
            <td colspan="2" class="px-4 py-2.5 text-xs font-semibold text-gray-600 text-right">Total Bobot:</td>
            <td class="px-4 py-2.5 text-center">
              <span :class="['text-xs font-bold', totalWeight === 100 ? 'text-green-600' : 'text-red-600']">
                {{ totalWeight }}%
              </span>
            </td>
            <td colspan="3" />
          </tr>
        </tfoot>
      </table>
    </div>
  </div>

  <BaseModal :open="modalOpen" :title="editingId ? 'Edit Jenis Ujian' : 'Tambah Jenis Ujian'" @close="modalOpen = false">
    <form class="space-y-4" @submit.prevent="handleSave">
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Kode <span class="text-red-500">*</span></label>
          <input v-model="form.code" required placeholder="TPA" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Nama Ujian <span class="text-red-500">*</span></label>
          <input v-model="form.name" required placeholder="Tes Potensi Akademik" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
        </div>
      </div>
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Bobot (%) <span class="text-red-500">*</span></label>
          <input v-model.number="form.weight" required type="number" min="0" max="100" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
          <p class="text-xs text-gray-400 mt-1">Persentase kontribusi terhadap nilai akhir</p>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">KKM (Passing Grade)</label>
          <input v-model.number="form.passing_grade" type="number" min="0" max="100" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
          <p class="text-xs text-gray-400 mt-1">Nilai minimum agar dianggap lulus komponen ini</p>
        </div>
      </div>
      <div class="flex items-center gap-2">
        <input v-model="form.is_active" type="checkbox" id="exam_active" class="rounded" />
        <label for="exam_active" class="text-sm text-gray-700">Aktifkan jenis ujian ini</label>
      </div>
    </form>
    <template #footer>
      <button class="px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded-lg" @click="modalOpen = false">Batal</button>
      <button :disabled="saving" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 disabled:bg-blue-400 text-white text-sm font-medium rounded-lg" @click="handleSave">{{ saving ? 'Menyimpan...' : 'Simpan' }}</button>
    </template>
  </BaseModal>
</template>
