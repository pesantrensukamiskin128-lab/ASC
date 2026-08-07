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

// Dokumen pendukung
const uploadedDocs = ref<any[]>([])
const pendingFiles = ref<File[]>([])

function onDocFilesSelected(e: Event) {
  const files = (e.target as HTMLInputElement).files
  if (!files) return
  pendingFiles.value.push(...Array.from(files))
  // Tambah ke list preview
  for (const f of files) {
    uploadedDocs.value.push({ name: f.getClientOriginalName ?? f.name, size: f.size, pending: true })
  }
  (e.target as HTMLInputElement).value = ''
}

function removeDoc(idx: number) {
  const doc = uploadedDocs.value[idx]
  if (doc.pending) {
    // Hapus dari pending files
    const pendingIdx = pendingFiles.value.findIndex(f => f.name === doc.name && f.size === doc.size)
    if (pendingIdx >= 0) pendingFiles.value.splice(pendingIdx, 1)
  }
  uploadedDocs.value.splice(idx, 1)
}

function formatFileSize(bytes: number): string {
  if (bytes < 1024) return bytes + ' B'
  if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB'
  return (bytes / (1024 * 1024)).toFixed(1) + ' MB'
}

async function uploadPendingDocs(letterId: number | string) {
  if (!pendingFiles.value.length) return
  const fd = new FormData()
  pendingFiles.value.forEach(f => fd.append('documents[]', f))
  await api.post(`/outgoing-letters/${letterId}/documents`, fd, {
    headers: { 'Content-Type': 'multipart/form-data' },
  })
  pendingFiles.value = []
}

function applyTemplate(tpl: any) {
  if (form.body && !confirm('Isi surat saat ini akan ditimpa oleh template. Lanjutkan?')) return
  if (tpl.letter_type_id) form.letter_type_id = tpl.letter_type_id
  if (tpl.subject) form.subject = tpl.subject
  if (tpl.recipient) form.recipient = tpl.recipient
  if (tpl.attachment_note) form.attachment_note = tpl.attachment_note
  if (tpl.city) form.city = tpl.city
  form.body = tpl.body
  if (tpl.appendix_body) form.appendix_body = tpl.appendix_body
  showTemplateModal.value = false
  toast.success(`Template "${tpl.name}" diterapkan.`)
}

const letterTypes = ref<any[]>([])
const signers = ref<any[]>([])
const allUsers = ref<any[]>([])
const templates = ref<any[]>([])
const showTemplateModal = ref(false)
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
  const [typesRes, signersRes, usersRes, tplRes] = await Promise.all([
    api.get('/outgoing-letters/letter-types'),
    api.get('/outgoing-letters/signers'),
    api.get('/user-list'),
    api.get('/letter-templates', { params: { per_page: 50 } }),
  ])
  letterTypes.value = typesRes.data
  signers.value = signersRes.data
  allUsers.value = usersRes.data
  templates.value = tplRes.data.data ?? tplRes.data

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
    // Load existing documents
    if (data.supporting_documents?.length) {
      uploadedDocs.value = data.supporting_documents.map((d: any) => ({ ...d, pending: false }))
    }
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

    // Upload dokumen pendukung jika ada
    await uploadPendingDocs(letter.id)

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
      <!-- Template picker -->
      <div v-if="templates.length && !isEdit" class="bg-amber-50 border border-amber-200 rounded-xl p-4 flex items-center justify-between">
        <div>
          <p class="text-sm font-medium text-amber-800">💡 Gunakan Template</p>
          <p class="text-xs text-amber-600 mt-0.5">Isi form otomatis dari template yang sudah disimpan</p>
        </div>
        <button type="button" class="px-3 py-1.5 bg-amber-600 hover:bg-amber-700 text-white text-xs font-medium rounded-lg" @click="showTemplateModal = true">
          Pilih Template
        </button>
      </div>

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

      <!-- Dokumen Pendukung -->
      <div class="bg-white rounded-xl border border-gray-200 p-6 space-y-4">
        <h2 class="text-sm font-semibold text-gray-800 uppercase tracking-wide">Dokumen Pendukung (opsional)</h2>
        <p class="text-xs text-gray-400">Upload file pendukung surat: PDF, Word, Excel, atau gambar (maks. 10MB per file)</p>

        <!-- List uploaded docs -->
        <div v-if="uploadedDocs.length" class="space-y-2">
          <div v-for="(doc, idx) in uploadedDocs" :key="idx" class="flex items-center gap-3 p-2.5 bg-gray-50 rounded-lg border border-gray-200">
            <span class="text-lg">📎</span>
            <div class="flex-1 min-w-0">
              <p class="text-sm text-gray-800 truncate">{{ doc.name }}</p>
              <p class="text-[10px] text-gray-400">{{ formatFileSize(doc.size) }}</p>
            </div>
            <button type="button" class="text-red-400 hover:text-red-600 text-xs" @click="removeDoc(idx)">✕</button>
          </div>
        </div>

        <!-- Upload area -->
        <div class="flex items-center gap-3">
          <label class="flex items-center gap-2 px-4 py-2 border border-dashed border-gray-300 rounded-lg cursor-pointer hover:border-blue-400 hover:bg-blue-50/50 transition-colors">
            <span class="text-sm text-gray-600">+ Pilih File</span>
            <input type="file" multiple accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png" class="hidden" @change="onDocFilesSelected" />
          </label>
          <span v-if="pendingFiles.length" class="text-xs text-blue-600">{{ pendingFiles.length }} file siap diupload</span>
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

    <!-- Template Picker Modal -->
    <div v-if="showTemplateModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" @click.self="showTemplateModal = false">
      <div class="bg-white rounded-xl p-6 w-full max-w-lg max-h-[80vh] overflow-y-auto">
        <h3 class="text-lg font-bold text-gray-900 mb-4">Pilih Template</h3>
        <div v-if="!templates.length" class="text-center text-gray-400 py-6">Belum ada template tersimpan.</div>
        <div v-else class="space-y-2">
          <div v-for="tpl in templates" :key="tpl.id"
            class="flex items-center justify-between p-3 rounded-lg border border-gray-200 hover:border-blue-300 hover:bg-blue-50/50 cursor-pointer transition-colors"
            @click="applyTemplate(tpl)">
            <div>
              <p class="text-sm font-medium text-gray-900">{{ tpl.name }}</p>
              <p v-if="tpl.description" class="text-xs text-gray-500 mt-0.5">{{ tpl.description }}</p>
              <p v-if="tpl.letter_type" class="text-[10px] text-gray-400 mt-0.5">{{ tpl.letter_type.code }} - {{ tpl.letter_type.name }}</p>
            </div>
            <span class="text-blue-600 text-xs font-medium">Gunakan →</span>
          </div>
        </div>
        <div class="mt-4 text-right">
          <button class="px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded-lg" @click="showTemplateModal = false">Tutup</button>
        </div>
      </div>
    </div>
  </div>
</template>
