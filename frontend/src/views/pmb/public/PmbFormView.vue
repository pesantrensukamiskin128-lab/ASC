<script setup lang="ts">
import { ref, reactive, onMounted, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useToast } from 'vue-toastification'
import { CheckCircleIcon, ExclamationCircleIcon } from '@heroicons/vue/24/outline'
import api from '@/services/api'

const router = useRouter()
const auth   = useAuthStore()
const toast  = useToast()

const loading    = ref(true)
const saving     = ref(false)
const submitting = ref(false)
const step       = ref(1)
const totalSteps = 6

// Photo upload
const photoFileInput   = ref<HTMLInputElement | null>(null)
const photoPreviewUrl  = ref<string | null>(null)
const uploadingPhoto   = ref(false)

interface Period { id: number; name: string }
interface Path { id: number; code: string; name: string }
interface Program { id: number; code: string; name: string; degree: string }

const periods  = ref<Period[]>([])
const paths    = ref<Path[]>([])
const programs = ref<Program[]>([])
const existingData = ref<any>(null)

const form = reactive({
  pmb_period_id: '',
  pmb_path_id: '',
  // Data pribadi
  full_name: '', gender: 'L', birth_place: '', birth_date: '',
  religion: '', nik: '', phone: '', email: '',
  address: '', province: '', city: '', district: '', village: '', postal_code: '',
  // Orang tua
  father_name: '', father_occupation: '', father_phone: '',
  mother_name: '', mother_occupation: '', mother_phone: '',
  guardian_name: '', guardian_occupation: '', guardian_phone: '',
  // Pendidikan
  school_name: '', school_address: '', graduation_year: '', diploma_number: '',
  // Pilihan prodi
  choice_1: '', choice_2: '', choice_3: '',
  // Prestasi
  achievement_description: '',
  // Dokumen (link)
  diploma_link: '', family_card_link: '', identity_link: '',
})

const stepLabels = [
  'Periode & Jalur', 'Data Pribadi', 'Orang Tua/Wali',
  'Riwayat Pendidikan', 'Pilihan Prodi', 'Dokumen',
]

onMounted(async () => {
  if (!auth.token) { await router.push('/pmb/login'); return }

  try {
    const [periodRes, pathRes, progRes, regRes] = await Promise.all([
      api.get('/pmb/active-period'),
      api.get('/pmb/paths'),
      api.get('/pmb/programs'),
      api.get('/pmb/my/registration'),
    ])

    if (periodRes.data) periods.value = [periodRes.data]
    paths.value    = pathRes.data ?? []
    programs.value = progRes.data ?? []

    // regRes.data bisa null/undefined/empty saat user baru
    const regData = regRes.data && typeof regRes.data === 'object' && regRes.data.id ? regRes.data : null
    existingData.value = regData

    // Pre-fill form jika sudah ada data
    if (regData) {
      const d = regData
      Object.keys(form).forEach((key) => {
        if (d[key] !== undefined && d[key] !== null) {
          ;(form as any)[key] = d[key]
        }
      })
      // Set period dari data existing
      form.pmb_period_id = d.pmb_period_id?.toString() ?? ''
      form.pmb_path_id   = d.pmb_path_id?.toString() ?? ''
      form.choice_1      = d.choice_1?.toString() ?? ''
      form.choice_2      = d.choice_2?.toString() ?? ''
      form.choice_3      = d.choice_3?.toString() ?? ''

      // Set photo preview
      if (d.photo_path) {
        const base = (import.meta.env.VITE_API_URL ?? 'http://localhost:8000/api').replace(/\/api\/?$/, '')
        photoPreviewUrl.value = `${base}/storage/${d.photo_path}`
      }
    } else if (periodRes.data) {
      form.pmb_period_id = periodRes.data.id.toString()
      form.email = auth.user?.email ?? ''
      form.full_name = auth.user?.name ?? ''
    }
  } catch (e) {
    // silent — formulir tetap ditampilkan
  } finally {
    loading.value = false
  }
})

const isSubmitted = computed(() => {
  if (!existingData.value || !existingData.value.id) return false
  return existingData.value.status !== 'DRAFT'
})

async function saveForm() {
  saving.value = true
  try {
    const { data } = await api.post('/pmb/my/form', form)
    existingData.value = data.data
    toast.success('Formulir berhasil disimpan.')
  } catch (err: any) {
    const msgs = err?.response?.data?.errors
    if (msgs) {
      const first = Object.values(msgs).flat()[0]
      toast.error(first as string)
    } else {
      toast.error(err?.response?.data?.message ?? 'Gagal menyimpan.')
    }
  } finally {
    saving.value = false
  }
}

async function submitForm() {
  if (!confirm('Yakin submit formulir? Setelah submit, formulir tidak dapat diubah lagi.')) return
  submitting.value = true
  try {
    await saveForm()
    const { data } = await api.post('/pmb/my/submit')
    existingData.value = data.data
    toast.success(data.message)
  } catch (err: any) {
    toast.error(err?.response?.data?.message ?? 'Gagal submit.')
  } finally {
    submitting.value = false
  }
}

function nextStep() { if (step.value < totalSteps) step.value++ }
function prevStep() { if (step.value > 1) step.value-- }

async function onPhotoSelected(e: Event) {
  const file = (e.target as HTMLInputElement).files?.[0]
  if (!file) return

  // Harus sudah simpan form dulu (perlu ada registrant di backend)
  if (!existingData.value) {
    toast.info('Simpan formulir terlebih dahulu sebelum upload foto.')
    return
  }

  uploadingPhoto.value = true
  try {
    const fd = new FormData()
    fd.append('photo', file)
    const { data } = await api.post('/pmb/my/photo', fd, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    photoPreviewUrl.value = data.photo_url
    toast.success(data.message)
  } catch {
    toast.error('Gagal upload foto.')
  } finally {
    uploadingPhoto.value = false
    if (photoFileInput.value) photoFileInput.value.value = ''
  }
}
</script>

<template>
  <div v-if="loading" class="flex items-center justify-center h-64">
    <p class="text-gray-400">Memuat formulir...</p>
  </div>

  <div v-else class="max-w-3xl mx-auto space-y-6">
    <!-- Status banner -->
    <div v-if="isSubmitted" class="bg-green-50 border border-green-200 rounded-xl p-4 flex items-center gap-3">
      <CheckCircleIcon class="w-6 h-6 text-green-600 shrink-0" />
      <div>
        <p class="text-sm font-medium text-green-800">Formulir sudah disubmit</p>
        <p class="text-xs text-green-600">Status: {{ existingData.status?.replace(/_/g, ' ') }}</p>
      </div>
      <RouterLink to="/pmb/status" class="ml-auto px-4 py-2 text-sm bg-green-600 hover:bg-green-700 text-white rounded-lg">
        Lihat Status
      </RouterLink>
    </div>

    <!-- Header -->
    <div v-if="!isSubmitted">
      <h1 class="text-xl font-bold text-gray-900">Formulir Pendaftaran</h1>
      <p class="text-sm text-gray-500 mt-0.5">Lengkapi semua data berikut untuk mendaftar sebagai calon mahasiswa baru</p>
    </div>

    <!-- Step indicator -->
    <div v-if="!isSubmitted" class="flex items-center gap-1">
      <template v-for="(label, i) in stepLabels" :key="i">
        <button
          :class="[
            'flex items-center gap-1 px-2.5 py-1.5 rounded-lg text-xs font-medium transition-colors',
            step === i + 1 ? 'bg-blue-600 text-white' : i + 1 < step ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'
          ]"
          @click="step = i + 1"
        >
          <span class="w-4 h-4 rounded-full flex items-center justify-center text-[10px] font-bold"
                :class="step === i + 1 ? 'bg-white/20' : ''">
            {{ i + 1 }}
          </span>
          <span class="hidden sm:inline">{{ label }}</span>
        </button>
        <div v-if="i < stepLabels.length - 1" class="w-3 h-px bg-gray-300" />
      </template>
    </div>

    <!-- Form content -->
    <div v-if="!isSubmitted" class="bg-white rounded-xl border border-gray-200 p-6">

      <!-- Step 1: Periode & Jalur -->
      <div v-show="step === 1" class="space-y-4">
        <h2 class="font-semibold text-gray-800">Periode & Jalur Pendaftaran</h2>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Periode <span class="text-red-500">*</span></label>
          <select v-model="form.pmb_period_id" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            <option value="">-- Pilih Periode --</option>
            <option v-for="p in periods" :key="p.id" :value="p.id">{{ p.name }}</option>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Jalur Pendaftaran</label>
          <select v-model="form.pmb_path_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            <option value="">-- Pilih Jalur --</option>
            <option v-for="p in paths" :key="p.id" :value="p.id">{{ p.name }}</option>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Deskripsi Prestasi (jika jalur prestasi/khusus)</label>
          <textarea v-model="form.achievement_description" rows="3" placeholder="Tuliskan prestasi yang relevan..." class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
        </div>
      </div>

      <!-- Step 2: Data Pribadi -->
      <div v-show="step === 2" class="space-y-4">
        <h2 class="font-semibold text-gray-800">Data Pribadi</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div class="md:col-span-2"><label class="block text-sm font-medium text-gray-700 mb-1">Nama Lengkap <span class="text-red-500">*</span></label><input v-model="form.full_name" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" /></div>
          <div><label class="block text-sm font-medium text-gray-700 mb-1">Jenis Kelamin <span class="text-red-500">*</span></label><select v-model="form.gender" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"><option value="L">Laki-laki</option><option value="P">Perempuan</option></select></div>
          <div><label class="block text-sm font-medium text-gray-700 mb-1">Agama</label><select v-model="form.religion" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"><option value="">-- Pilih --</option><option v-for="r in ['Islam','Kristen','Katolik','Hindu','Buddha','Konghucu']" :key="r" :value="r">{{ r }}</option></select></div>
          <div><label class="block text-sm font-medium text-gray-700 mb-1">Tempat Lahir <span class="text-red-500">*</span></label><input v-model="form.birth_place" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" /></div>
          <div><label class="block text-sm font-medium text-gray-700 mb-1">Tanggal Lahir <span class="text-red-500">*</span></label><input v-model="form.birth_date" required type="date" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" /></div>
          <div><label class="block text-sm font-medium text-gray-700 mb-1">NIK</label><input v-model="form.nik" maxlength="16" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" /></div>
          <div><label class="block text-sm font-medium text-gray-700 mb-1">No. HP</label><input v-model="form.phone" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" /></div>
          <div><label class="block text-sm font-medium text-gray-700 mb-1">Email</label><input v-model="form.email" type="email" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" /></div>
          <div class="md:col-span-2"><label class="block text-sm font-medium text-gray-700 mb-1">Alamat Lengkap</label><textarea v-model="form.address" rows="2" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" /></div>
          <div><label class="block text-sm font-medium text-gray-700 mb-1">Provinsi</label><input v-model="form.province" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" /></div>
          <div><label class="block text-sm font-medium text-gray-700 mb-1">Kota/Kabupaten</label><input v-model="form.city" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" /></div>
          <div><label class="block text-sm font-medium text-gray-700 mb-1">Kecamatan</label><input v-model="form.district" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" /></div>
          <div><label class="block text-sm font-medium text-gray-700 mb-1">Kode Pos</label><input v-model="form.postal_code" maxlength="5" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" /></div>
        </div>
      </div>

      <!-- Step 3: Orang Tua -->
      <div v-show="step === 3" class="space-y-4">
        <h2 class="font-semibold text-gray-800">Data Orang Tua / Wali</h2>
        <p class="text-xs text-gray-400 font-semibold uppercase">Ayah</p>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div><label class="block text-sm font-medium text-gray-700 mb-1">Nama Ayah</label><input v-model="form.father_name" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" /></div>
          <div><label class="block text-sm font-medium text-gray-700 mb-1">Pekerjaan</label><input v-model="form.father_occupation" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" /></div>
          <div><label class="block text-sm font-medium text-gray-700 mb-1">No. HP</label><input v-model="form.father_phone" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" /></div>
        </div>
        <p class="text-xs text-gray-400 font-semibold uppercase pt-2">Ibu</p>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div><label class="block text-sm font-medium text-gray-700 mb-1">Nama Ibu</label><input v-model="form.mother_name" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" /></div>
          <div><label class="block text-sm font-medium text-gray-700 mb-1">Pekerjaan</label><input v-model="form.mother_occupation" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" /></div>
          <div><label class="block text-sm font-medium text-gray-700 mb-1">No. HP</label><input v-model="form.mother_phone" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" /></div>
        </div>
        <p class="text-xs text-gray-400 font-semibold uppercase pt-2">Wali (opsional)</p>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div><label class="block text-sm font-medium text-gray-700 mb-1">Nama Wali</label><input v-model="form.guardian_name" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" /></div>
          <div><label class="block text-sm font-medium text-gray-700 mb-1">Pekerjaan</label><input v-model="form.guardian_occupation" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" /></div>
          <div><label class="block text-sm font-medium text-gray-700 mb-1">No. HP</label><input v-model="form.guardian_phone" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" /></div>
        </div>
      </div>

      <!-- Step 4: Pendidikan -->
      <div v-show="step === 4" class="space-y-4">
        <h2 class="font-semibold text-gray-800">Riwayat Pendidikan</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div class="md:col-span-2"><label class="block text-sm font-medium text-gray-700 mb-1">Nama Sekolah Asal</label><input v-model="form.school_name" placeholder="SMA Negeri 1..." class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" /></div>
          <div class="md:col-span-2"><label class="block text-sm font-medium text-gray-700 mb-1">Alamat Sekolah</label><input v-model="form.school_address" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" /></div>
          <div><label class="block text-sm font-medium text-gray-700 mb-1">Tahun Lulus</label><input v-model="form.graduation_year" type="number" min="2000" max="2030" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" /></div>
          <div><label class="block text-sm font-medium text-gray-700 mb-1">Nomor Ijazah</label><input v-model="form.diploma_number" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" /></div>
        </div>
      </div>

      <!-- Step 5: Pilihan Prodi -->
      <div v-show="step === 5" class="space-y-4">
        <h2 class="font-semibold text-gray-800">Pilihan Program Studi</h2>
        <p class="text-sm text-gray-500">Pilih hingga 3 program studi sesuai prioritas.</p>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Pilihan 1 <span class="text-red-500">*</span></label>
          <select v-model="form.choice_1" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            <option value="">-- Pilih Program Studi --</option>
            <option v-for="p in programs" :key="p.id" :value="p.id">{{ p.code }} - {{ p.name }} ({{ p.degree }})</option>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Pilihan 2</label>
          <select v-model="form.choice_2" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            <option value="">-- Opsional --</option>
            <option v-for="p in programs" :key="p.id" :value="p.id">{{ p.code }} - {{ p.name }} ({{ p.degree }})</option>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Pilihan 3</label>
          <select v-model="form.choice_3" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            <option value="">-- Opsional --</option>
            <option v-for="p in programs" :key="p.id" :value="p.id">{{ p.code }} - {{ p.name }} ({{ p.degree }})</option>
          </select>
        </div>
      </div>

      <!-- Step 6: Dokumen -->
      <div v-show="step === 6" class="space-y-4">
        <h2 class="font-semibold text-gray-800">Upload Dokumen</h2>
        <p class="text-sm text-gray-500">Upload pas foto dan berikan link Google Drive untuk dokumen lainnya.</p>

        <!-- Upload Pas Foto -->
        <div class="border border-gray-200 rounded-lg p-4 space-y-3">
          <p class="text-sm font-medium text-gray-700">Pas Foto <span class="text-red-500">*</span></p>
          <div class="flex items-center gap-4">
            <div class="w-24 h-32 bg-gray-100 rounded-lg border border-dashed border-gray-300 overflow-hidden flex items-center justify-center">
              <img v-if="photoPreviewUrl" :src="photoPreviewUrl" class="w-full h-full object-cover" />
              <span v-else class="text-xs text-gray-400 text-center px-2">3x4 JPG/PNG</span>
            </div>
            <div>
              <input ref="photoFileInput" type="file" accept="image/jpeg,image/png" class="hidden" @change="onPhotoSelected" />
              <button type="button" :disabled="uploadingPhoto" class="px-4 py-2 text-sm bg-blue-600 hover:bg-blue-700 disabled:bg-blue-400 text-white rounded-lg" @click="photoFileInput?.click()">
                {{ uploadingPhoto ? 'Mengupload...' : (photoPreviewUrl ? 'Ganti Foto' : 'Upload Foto') }}
              </button>
              <p class="text-xs text-gray-400 mt-1">Format JPG/PNG, maks 2MB. Simpan formulir dulu untuk upload.</p>
            </div>
          </div>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Link Ijazah (Google Drive)</label>
          <input v-model="form.diploma_link" placeholder="https://drive.google.com/..." class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Link Kartu Keluarga (Google Drive)</label>
          <input v-model="form.family_card_link" placeholder="https://drive.google.com/..." class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Link KTP / Identitas (Google Drive)</label>
          <input v-model="form.identity_link" placeholder="https://drive.google.com/..." class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
        </div>
      </div>

      <!-- Navigation buttons -->
      <div class="flex items-center justify-between pt-6 border-t border-gray-100 mt-6">
        <button v-if="step > 1" class="px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded-lg" @click="prevStep">
          ← Sebelumnya
        </button>
        <div v-else />

        <div class="flex items-center gap-2">
          <button :disabled="saving" class="px-4 py-2 text-sm text-blue-600 border border-blue-200 hover:bg-blue-50 rounded-lg" @click="saveForm">
            {{ saving ? 'Menyimpan...' : 'Simpan Draft' }}
          </button>

          <button v-if="step < totalSteps" class="px-4 py-2 text-sm bg-blue-600 hover:bg-blue-700 text-white rounded-lg" @click="nextStep">
            Selanjutnya →
          </button>

          <button v-else :disabled="submitting" class="px-4 py-2 text-sm bg-green-600 hover:bg-green-700 text-white rounded-lg font-medium" @click="submitForm">
            {{ submitting ? 'Mengirim...' : 'Submit Formulir' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
