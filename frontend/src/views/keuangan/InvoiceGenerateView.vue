<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useToast } from 'vue-toastification'
import { ArrowLeftIcon, BanknotesIcon } from '@heroicons/vue/24/outline'
import api from '@/services/api'

const router = useRouter()
const toast = useToast()
const saving = ref(false)
const semesters = ref<any[]>([])
const programs = ref<any[]>([])
const result = ref<any>(null)

const form = reactive({ semester_id: '', study_program_id: '', due_date: '' })

onMounted(async () => {
  const [semRes, progRes] = await Promise.all([
    api.get('/semesters', { params: { per_page: 50 } }),
    api.get('/study-programs/all'),
  ])
  semesters.value = semRes.data.data ?? semRes.data
  programs.value = progRes.data
  const active = semesters.value.find((s: any) => s.is_active)
  if (active) form.semester_id = active.id
})

async function handleGenerate() {
  if (!form.semester_id || !form.due_date) {
    toast.error('Pilih semester dan tanggal jatuh tempo.')
    return
  }
  if (!confirm('Generate tagihan otomatis untuk semua mahasiswa aktif di semester ini?')) return

  saving.value = true
  try {
    const { data } = await api.post('/finance/invoices/generate-batch', {
      semester_id: form.semester_id,
      study_program_id: form.study_program_id || null,
      due_date: form.due_date,
    })
    result.value = data
    toast.success(data.message)
  } catch (e: any) {
    toast.error(e?.response?.data?.message ?? 'Gagal generate.')
  } finally { saving.value = false }
}
</script>

<template>
  <div class="max-w-2xl mx-auto space-y-6">
    <div class="flex items-center gap-3">
      <button class="p-2 rounded-lg hover:bg-gray-100" @click="router.back()"><ArrowLeftIcon class="w-5 h-5 text-gray-500" /></button>
      <h1 class="text-xl font-bold text-gray-900">Generate Tagihan Batch</h1>
    </div>

    <div class="bg-white rounded-xl border border-gray-200 p-6 space-y-5">
      <p class="text-sm text-gray-600">
        Generate tagihan secara otomatis untuk semua mahasiswa aktif berdasarkan struktur biaya yang sudah dikonfigurasi.
        Tagihan hanya dibuat untuk mahasiswa yang belum punya tagihan di semester yang dipilih.
      </p>

      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Semester <span class="text-red-500">*</span></label>
          <select v-model="form.semester_id" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            <option value="">-- Pilih --</option>
            <option v-for="s in semesters" :key="s.id" :value="s.id">{{ s.name }}</option>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Jatuh Tempo <span class="text-red-500">*</span></label>
          <input v-model="form.due_date" type="date" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
        </div>
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Filter Program Studi (opsional)</label>
        <select v-model="form.study_program_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
          <option value="">Semua Program Studi</option>
          <option v-for="p in programs" :key="p.id" :value="p.id">{{ p.code }} - {{ p.name }}</option>
        </select>
      </div>

      <div class="border-t border-gray-200 pt-4">
        <button :disabled="saving" class="flex items-center gap-2 px-6 py-2.5 bg-green-600 hover:bg-green-700 disabled:bg-green-400 text-white font-medium rounded-lg" @click="handleGenerate">
          <BanknotesIcon class="w-5 h-5" />
          {{ saving ? 'Memproses...' : 'Generate Tagihan' }}
        </button>
      </div>

      <!-- Result -->
      <div v-if="result" class="mt-4 p-4 bg-green-50 border border-green-200 rounded-lg">
        <p class="text-sm font-medium text-green-800">{{ result.message }}</p>
        <p class="text-xs text-green-600 mt-1">{{ result.count }} tagihan berhasil dibuat.</p>
        <button class="mt-3 text-sm text-green-700 underline hover:text-green-800" @click="router.push('/keuangan/tagihan')">
          Lihat Semua Tagihan →
        </button>
      </div>
    </div>
  </div>
</template>
