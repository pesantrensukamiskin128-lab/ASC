<script setup lang="ts">
import { ref, reactive, onMounted, computed } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useToast } from 'vue-toastification'
import RichTextEditor from '@/components/ui/RichTextEditor.vue'
import api from '@/services/api'

const router = useRouter()
const route = useRoute()
const toast = useToast()

const editId = route.params.id as string | undefined
const isEdit = !!editId
const saving = ref(false)
const sending = ref(false)

const letterTypes = ref<any[]>([])
const signers = ref<any[]>([])
const allUsers = ref<any[]>([])

const form = reactive({
  letter_type_id: '',
  subject: '',
  recipient: '',
  attachment_note: '',
  city: 'Bandung',
  letter_date: new Date().toISOString().split('T')[0],
  body: '',
  appendix_body: '',
  reviewer_id: '',
  signer_id: '',
  external_recipients: '',
})

onMounted(async () => {
  const [typesRes, signersRes, usersRes] = await Promise.all([
    api.get('/outgoing-letters/letter-types'),
    api.get('/outgoing-letters/signers'),
    api.get('/user-list'),
  ])
  letterTypes.value = typesRes.data
  signers.value = signersRes.data
  allUsers.value = usersRes.data

  if (isEdit) {
    const { data } = await api.get(`/outgoing-letters/${editId}`)
    Object.assign(form, {
      letter_type_id: data.letter_type_id,
      subject: data.subject,
      recipient: data.recipient,
      attachment_note: data.attachment_note ?? '',
      city: data.city ?? 'Bandung',
      letter_date: data.letter_date,
      body: data.body,
      appendix_body: data.appendix_body ?? '',
      reviewer_id: data.reviewer_id ?? '',
      signer_id: data.signer_id,
      external_recipients: data.external_recipients ?? '',
    })
  }
})

// Reviewers = Kepala TU + jabatan struktural (signers juga bisa jadi reviewer)
const reviewers = computed(() => {
  const tuUsers = allUsers.value.filter((u: any) => u.roles?.some((r: any) => r.name === 'KEPALA_TU'))
  const signerUsers = signers.value.map((s: any) => ({ id: s.user_id, name: `${s.name} (${s.position_name})` }))
  return [...tuUsers.map((u: any) => ({ id: u.id, name: `${u.name} (Kepala TU)` })), ...signerUsers]
})

async function handleSave(andSend = false) {
  saving.value = true
  try {
    const payload = { ...form }
    if (!payload.reviewer_id) delete (payload as any).reviewer_id

    let letter: any
    if (isEdit) {
      const { data } = await api.put(`/outgoing-letters/${editId}`, payload)
      letter = data.data
      toast.success('Surat berhasil diupdate.')
    } else {
      const { data } = await api.post('/outgoing-letters', payload)
      letter = data.data
      toast.success('Surat berhasil dibuat.')
    }

    if (andSend) {
      sending.value = true
      await api.post(`/outgoing-letters/${letter.id}/send`)
      toast.success('Surat berhasil dikirim untuk pemeriksaan/penandatanganan.')
    }

    router.push('/persuratan/surat-keluar')
  } catch (err: any) {
    toast.error(err?.response?.data?.message ?? 'Gagal menyimpan surat.')
  } finally {
    saving.value = false
    sending.value = false
  }
}
</script>

<template>
  <div class="space-y-5 max-w-4xl">
    <div>
      <h1 class="text-xl font-bold text-gray-900">{{ isEdit ? 'Edit Surat Keluar' : 'Buat Surat Keluar' }}</h1>
      <p class="text-sm text-gray-500 mt-0.5">Isi formulir surat, lalu simpan sebagai draft atau kirim langsung</p>
    </div>

    <form class="space-y-5" @submit.prevent="handleSave(false)">
      <div class="bg-white rounded-xl border border-gray-200 p-6 space-y-4">
        <h2 class="text-sm font-semibold text-gray-800 uppercase tracking-wide">Informasi Surat</h2>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Jenis Surat <span class="text-red-500">*</span></label>
            <select v-model="form.letter_type_id" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
              <option value="">-- Pilih Jenis --</option>
              <option v-for="t in letterTypes" :key="t.id" :value="t.id">{{ t.code }} - {{ t.name }}</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Tanggal Surat <span class="text-red-500">*</span></label>
            <input v-model="form.letter_date" type="date" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
          </div>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Perihal <span class="text-red-500">*</span></label>
          <input v-model="form.subject" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Perihal surat..." />
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Kepada Yth. <span class="text-red-500">*</span></label>
          <textarea v-model="form.recipient" required rows="2" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Tujuan surat (bisa multi-baris)" />
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Tempat Terbit</label>
            <input v-model="form.city" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Lampiran</label>
            <input v-model="form.attachment_note" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="cth: 1 (Satu Lembar)" />
          </div>
        </div>
      </div>

      <div class="bg-white rounded-xl border border-gray-200 p-6 space-y-4">
        <h2 class="text-sm font-semibold text-gray-800 uppercase tracking-wide">Isi Surat</h2>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Isi Surat <span class="text-red-500">*</span></label>
          <RichTextEditor v-model="form.body" placeholder="Tulis isi surat di sini..." min-height="300px" />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Isi Lampiran (opsional)</label>
          <RichTextEditor v-model="form.appendix_body" placeholder="Isi lampiran jika ada..." min-height="150px" />
        </div>
      </div>

      <div class="bg-white rounded-xl border border-gray-200 p-6 space-y-4">
        <h2 class="text-sm font-semibold text-gray-800 uppercase tracking-wide">Penandatangan & Pemeriksa</h2>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Penandatangan <span class="text-red-500">*</span></label>
          <select v-model="form.signer_id" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            <option value="">-- Pilih Penandatangan --</option>
            <option v-for="s in signers" :key="s.user_id" :value="s.user_id">{{ s.name }} — {{ s.position_name }}</option>
          </select>
          <p class="text-xs text-gray-400 mt-1">Pejabat yang akan menandatangani surat ini</p>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Pemeriksa (opsional)</label>
          <select v-model="form.reviewer_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            <option value="">-- Tanpa Pemeriksa (langsung ke penandatangan) --</option>
            <option v-for="r in reviewers" :key="r.id" :value="r.id">{{ r.name }}</option>
          </select>
          <p class="text-xs text-gray-400 mt-1">Jika dipilih, surat akan diperiksa dulu sebelum ditandatangani</p>
        </div>
      </div>

      <div class="bg-white rounded-xl border border-gray-200 p-6 space-y-4">
        <h2 class="text-sm font-semibold text-gray-800 uppercase tracking-wide">Penerima Eksternal (opsional)</h2>
        <div>
          <textarea v-model="form.external_recipients" rows="2" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Nama penerima eksternal (bisa multi-baris)" />
        </div>
      </div>

      <!-- Actions -->
      <div class="flex items-center gap-3">
        <button type="button" class="px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-100 rounded-lg border border-gray-300" @click="router.back()">Batal</button>
        <button type="submit" :disabled="saving" class="px-4 py-2.5 bg-gray-600 hover:bg-gray-700 disabled:bg-gray-400 text-white text-sm font-medium rounded-lg">
          {{ saving ? 'Menyimpan...' : 'Simpan Draft' }}
        </button>
        <button type="button" :disabled="saving || sending" class="px-4 py-2.5 bg-blue-600 hover:bg-blue-700 disabled:bg-blue-400 text-white text-sm font-medium rounded-lg" @click="handleSave(true)">
          {{ sending ? 'Mengirim...' : 'Simpan & Kirim' }}
        </button>
      </div>
    </form>
  </div>
</template>
