<script setup lang="ts">
import { ref, reactive, onMounted, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useToast } from 'vue-toastification'
import { PlusIcon, TrashIcon, ArrowLeftIcon } from '@heroicons/vue/24/outline'
import api from '@/services/api'

const router = useRouter()
const toast = useToast()
const saving = ref(false)

const students = ref<any[]>([])
const semesters = ref<any[]>([])
const feeTypes = ref<any[]>([])
const searchStudent = ref('')
const studentResults = ref<any[]>([])
const searching = ref(false)

const form = reactive({
  student_id: '' as string | number,
  semester_id: '',
  due_date: '',
  discount_amount: 0,
  scholarship_amount: 0,
  note: '',
  items: [{ fee_type_id: '', description: '', amount: 0 }] as { fee_type_id: string; description: string; amount: number }[],
})

const selectedStudent = ref<any>(null)

const totalAmount = computed(() => form.items.reduce((sum, i) => sum + (Number(i.amount) || 0), 0))

onMounted(async () => {
  const [semRes, feeRes] = await Promise.all([
    api.get('/semesters', { params: { per_page: 50 } }),
    api.get('/finance/fee-types'),
  ])
  semesters.value = semRes.data.data ?? semRes.data
  feeTypes.value = feeRes.data

  // Default semester aktif
  const active = semesters.value.find((s: any) => s.is_active)
  if (active) form.semester_id = active.id
})

let searchTimeout: any = null
function onSearchStudent() {
  clearTimeout(searchTimeout)
  if (searchStudent.value.length < 2) { studentResults.value = []; return }
  searchTimeout = setTimeout(async () => {
    searching.value = true
    try {
      const { data } = await api.get('/students', { params: { search: searchStudent.value, per_page: 10 } })
      studentResults.value = data.data ?? data
    } finally { searching.value = false }
  }, 300)
}

function selectStudent(s: any) {
  selectedStudent.value = s
  form.student_id = s.id
  searchStudent.value = `${s.nim} - ${s.name}`
  studentResults.value = []
}

function addItem() {
  form.items.push({ fee_type_id: '', description: '', amount: 0 })
}
function removeItem(i: number) {
  if (form.items.length > 1) form.items.splice(i, 1)
}

async function handleSubmit() {
  if (!form.student_id) { toast.error('Pilih mahasiswa.'); return }
  if (!form.due_date) { toast.error('Isi tanggal jatuh tempo.'); return }
  if (form.items.some(i => !i.fee_type_id || !i.amount)) { toast.error('Lengkapi semua item tagihan.'); return }

  saving.value = true
  try {
    const { data } = await api.post('/finance/invoices', {
      student_id: form.student_id,
      semester_id: form.semester_id || null,
      due_date: form.due_date,
      discount_amount: form.discount_amount || 0,
      scholarship_amount: form.scholarship_amount || 0,
      note: form.note || null,
      items: form.items.map(i => ({ fee_type_id: i.fee_type_id, description: i.description || null, amount: i.amount })),
    })
    toast.success('Tagihan berhasil dibuat.')
    router.push(`/keuangan/tagihan/${data.data.id}`)
  } catch (e: any) {
    toast.error(e?.response?.data?.message ?? 'Gagal membuat tagihan.')
  } finally { saving.value = false }
}

function formatCurrency(n: number) {
  return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(n)
}
</script>

<template>
  <div class="max-w-3xl mx-auto space-y-6">
    <div class="flex items-center gap-3">
      <button class="p-2 rounded-lg hover:bg-gray-100" @click="router.back()"><ArrowLeftIcon class="w-5 h-5 text-gray-500" /></button>
      <h1 class="text-xl font-bold text-gray-900">Buat Tagihan Baru</h1>
    </div>

    <div class="bg-white rounded-xl border border-gray-200 p-6 space-y-5">
      <!-- Mahasiswa -->
      <div class="relative">
        <label class="block text-sm font-medium text-gray-700 mb-1">Mahasiswa <span class="text-red-500">*</span></label>
        <input
          v-model="searchStudent"
          placeholder="Ketik NIM atau nama mahasiswa..."
          class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
          @input="onSearchStudent"
        />
        <div v-if="studentResults.length" class="absolute z-10 w-full mt-1 bg-white border border-gray-200 rounded-lg shadow-lg max-h-48 overflow-y-auto">
          <button
            v-for="s in studentResults" :key="s.id"
            class="w-full text-left px-3 py-2 hover:bg-blue-50 text-sm"
            @click="selectStudent(s)"
          >
            <span class="font-mono text-xs text-gray-500">{{ s.nim }}</span> — {{ s.name }}
            <span class="text-xs text-gray-400 ml-1">({{ s.study_program?.code }})</span>
          </button>
        </div>
      </div>

      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Semester</label>
          <select v-model="form.semester_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            <option value="">-- Opsional --</option>
            <option v-for="s in semesters" :key="s.id" :value="s.id">{{ s.name }}</option>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Jatuh Tempo <span class="text-red-500">*</span></label>
          <input v-model="form.due_date" type="date" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
        </div>
      </div>

      <!-- Items -->
      <div>
        <div class="flex items-center justify-between mb-2">
          <label class="text-sm font-medium text-gray-700">Item Tagihan</label>
          <button class="text-xs text-blue-600 hover:text-blue-700 font-medium flex items-center gap-1" @click="addItem"><PlusIcon class="w-3 h-3" /> Tambah</button>
        </div>
        <div class="space-y-2">
          <div v-for="(item, i) in form.items" :key="i" class="grid grid-cols-12 gap-2 items-center">
            <select v-model="item.fee_type_id" class="col-span-4 px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
              <option value="">Jenis</option>
              <option v-for="ft in feeTypes" :key="ft.id" :value="ft.id">{{ ft.name }}</option>
            </select>
            <input v-model="item.description" placeholder="Keterangan" class="col-span-4 px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
            <input v-model.number="item.amount" type="number" min="0" placeholder="Jumlah" class="col-span-3 px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
            <button v-if="form.items.length > 1" class="col-span-1 p-2 rounded-lg text-red-500 hover:bg-red-50" @click="removeItem(i)"><TrashIcon class="w-4 h-4" /></button>
          </div>
        </div>
      </div>

      <!-- Discount & Scholarship -->
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Diskon (Rp)</label>
          <input v-model.number="form.discount_amount" type="number" min="0" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Potongan Beasiswa (Rp)</label>
          <input v-model.number="form.scholarship_amount" type="number" min="0" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
        </div>
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Catatan</label>
        <textarea v-model="form.note" rows="2" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
      </div>

      <!-- Total -->
      <div class="border-t border-gray-200 pt-4 flex items-center justify-between">
        <span class="text-sm text-gray-500">Total Tagihan</span>
        <span class="text-xl font-bold text-gray-900">{{ formatCurrency(totalAmount) }}</span>
      </div>

      <div class="flex justify-end gap-3">
        <button class="px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded-lg" @click="router.back()">Batal</button>
        <button :disabled="saving" class="px-6 py-2 bg-blue-600 hover:bg-blue-700 disabled:bg-blue-400 text-white text-sm font-medium rounded-lg" @click="handleSubmit">
          {{ saving ? 'Menyimpan...' : 'Buat Tagihan' }}
        </button>
      </div>
    </div>
  </div>
</template>
