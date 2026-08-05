<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useToast } from 'vue-toastification'
import { useAuthStore } from '@/stores/auth'
import { PencilIcon, ArrowLeftIcon, LinkIcon } from '@heroicons/vue/24/outline'
import api from '@/services/api'

const route  = useRoute()
const router = useRouter()
const toast  = useToast()
const auth   = useAuthStore()

const isAdmin    = auth.hasRole('SUPER_ADMIN') || auth.hasRole('ADMIN_AKADEMIK')
const isLp2m     = auth.hasRole('LP2M') || isAdmin
const isDosen    = auth.hasRole('DOSEN')
const isKeuangan = auth.hasRole('KEUANGAN') || isAdmin

const item      = ref<any>(null)
const loading   = ref(true)
const acting    = ref(false)
const lecturers = ref<any[]>([])

// Form states untuk berbagai aksi
const submitLink      = ref('')
const kaprodiNote     = ref('')
const kaprodiAction   = ref('diketahui')
const reviewerIds     = ref<number[]>([])
const seleksiResult   = ref('lolos')
const seleksiNote     = ref('')
const revisiProposal  = ref('')
const reviewForm      = ref({ score_orisinalitas: 0, score_metodologi: 0, score_manfaat: 0, score_kelayakan: 0, catatan: '', rekomendasi: 'lolos' })
const kontrakForm     = ref({ contract_number: '', total_dana: '', contract_link: '', contract_date: '' })
const fundingForm     = ref({ stage: 1, amount: '', keterangan: '' })
const laporanKemajuan = ref('')
const revisiKemajuan  = ref('')
const monevResult     = ref('lanjut')
const monevNote       = ref('')
const laporanAkhir    = ref({ laporan_akhir_link: '', paper_link: '', abstract: '', bibliography: '' })
const seminarDate     = ref('')
const seminarResult   = ref('diterima')
const seminarNote     = ref('')
const laporanFinal    = ref<File | null>(null)
const paperFinal      = ref<File | null>(null)
const lpjLink         = ref('')
const lpjRevisiLink   = ref('')
const lpjAction       = ref('terima')
const lpjNote         = ref('')
const monevReviewerIds = ref<number[]>([])

const STATUS_LABELS: Record<string, string> = {
  draft: 'Draft', review_kaprodi: 'Review Ka.Prodi', submitted: 'Dikembalikan',
  seleksi_reviewer: 'Seleksi Reviewer', tidak_lolos: 'Tidak Lolos', kontrak: 'Kontrak',
  pelaksanaan_1: 'Pelaksanaan I', monev: 'Monev', revisi_kemajuan: 'Revisi Kemajuan',
  pelaksanaan_2: 'Pelaksanaan II', seminar: 'Seminar', revisi_seminar: 'Revisi Seminar',
  lpj: 'LPJ', revisi_lpj: 'Revisi LPJ', selesai: 'Selesai',
}
const STATUS_COLORS: Record<string, string> = {
  draft: 'bg-gray-100 text-gray-600', review_kaprodi: 'bg-yellow-100 text-yellow-700',
  submitted: 'bg-orange-100 text-orange-700', seleksi_reviewer: 'bg-blue-100 text-blue-700',
  tidak_lolos: 'bg-red-100 text-red-700', kontrak: 'bg-purple-100 text-purple-700',
  pelaksanaan_1: 'bg-indigo-100 text-indigo-700', monev: 'bg-cyan-100 text-cyan-700',
  revisi_kemajuan: 'bg-orange-100 text-orange-700', pelaksanaan_2: 'bg-indigo-100 text-indigo-700',
  seminar: 'bg-teal-100 text-teal-700', revisi_seminar: 'bg-orange-100 text-orange-700',
  lpj: 'bg-violet-100 text-violet-700', revisi_lpj: 'bg-orange-100 text-orange-700',
  selesai: 'bg-green-100 text-green-700',
}

const status = computed(() => item.value?.status ?? '')

const isKetua = computed(() => {
  if (!item.value || !auth.user) return false
  return item.value.ketua?.user_id === auth.user.id
})

const isTeamMember = computed(() => {
  if (isKetua.value || isAdmin) return true
  if (!item.value?.members) return false
  return item.value.members.some((m: any) =>
    m.member_type === 'dosen' && m.lecturer?.user_id === auth.user?.id
  )
})

const myReviewAssignment = computed(() => {
  if (!item.value?.reviewers) return null
  return item.value.reviewers.find((r: any) =>
    r.lecturer?.user_id === auth.user?.id && r.stage === 'seleksi'
  )
})

const totalDana = computed(() =>
  item.value?.total_dana ? Number(item.value.total_dana).toLocaleString('id-ID') : '-'
)

onMounted(async () => {
  await load()
  try {
    const { data } = await api.get('/lecturers/all')
    lecturers.value = data
  } catch {}
})

async function load() {
  loading.value = true
  try {
    const { data } = await api.get(`/penelitian/${route.params.id}`)
    item.value = data
  } catch { router.push('/penelitian') }
  finally { loading.value = false }
}

async function act(endpoint: string, payload: any, successMsg: string) {
  acting.value = true
  try {
    await api.post(`/penelitian/${route.params.id}/${endpoint}`, payload)
    toast.success(successMsg)
    await load()
  } catch (e: any) {
    toast.error(e?.response?.data?.message ?? 'Gagal.')
  } finally { acting.value = false }
}

async function actForm(endpoint: string, formData: FormData, successMsg: string) {
  acting.value = true
  try {
    await api.post(`/penelitian/${route.params.id}/${endpoint}`, formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    toast.success(successMsg)
    await load()
  } catch (e: any) {
    toast.error(e?.response?.data?.message ?? 'Gagal.')
  } finally { acting.value = false }
}

// Aksi-aksi per tahap
const doSubmitKaprodi = () => act('submit-kaprodi', { proposal_link: submitLink.value }, 'Proposal diajukan ke Ka.Prodi.')
const doReviewKaprodi = () => act('review-kaprodi', { action: kaprodiAction.value, kaprodi_note: kaprodiNote.value }, 'Review Ka.Prodi berhasil.')
const doAssignReviewers = () => act('assign-reviewers', { reviewer_ids: reviewerIds.value, stage: 'seleksi' }, 'Reviewer ditugaskan.')
const doAssignMonevReviewers = () => act('assign-monev-reviewer', { reviewer_ids: monevReviewerIds.value }, 'Reviewer monev ditugaskan.')
const doSubmitReview = () => act('submit-review', { ...reviewForm.value, stage: 'seleksi' }, 'Review berhasil disimpan.')
const doRevisiProposal = () => act('upload-revisi-proposal', { proposal_revision_link: revisiProposal.value }, 'Link revisi disimpan.')
const doSeleksiResult = () => act('seleksi-result', { result: seleksiResult.value, lp2m_note: seleksiNote.value }, 'Hasil seleksi ditetapkan.')
const doSaveKontrak = () => act('save-kontrak', kontrakForm.value, 'Kontrak disimpan.')
const doAllocateFunding = () => act('allocate-funding', fundingForm.value, `Dana Tahap ${fundingForm.value.stage} dialokasikan.`)
const doDisburseFunding = async (stage: number) => {
  acting.value = true
  try {
    await api.post(`/penelitian/${route.params.id}/disburse-funding/${stage}`, {})
    toast.success(`Dana Tahap ${stage} dicairkan.`)
    await load()
  } catch (e: any) { toast.error(e?.response?.data?.message ?? 'Gagal.') }
  finally { acting.value = false }
}
const doLaporanKemajuan = () => act('upload-laporan-kemajuan', { laporan_kemajuan_link: laporanKemajuan.value }, 'Laporan kemajuan diupload.')
const doRevisiKemajuan = () => act('upload-revisi-kemajuan', { laporan_kemajuan_revision_link: revisiKemajuan.value }, 'Revisi kemajuan diupload.')
const doMonevResult = () => act('monev-result', { result: monevResult.value, lp2m_note: monevNote.value }, 'Hasil monev disimpan.')
const doLaporanAkhir = () => act('upload-laporan-akhir', laporanAkhir.value, 'Laporan akhir diupload.')
const doSeminarDate = () => act('seminar-date', { seminar_date: seminarDate.value }, 'Jadwal seminar disimpan.')
const doSeminarResult = () => act('seminar-result', { result: seminarResult.value, lp2m_note: seminarNote.value }, 'Hasil seminar disimpan.')
const doLpj = () => act('upload-lpj', { lpj_link: lpjLink.value }, 'LPJ diupload.')
const doRevisiLpj = () => act('upload-revisi-lpj', { lpj_revision_link: lpjRevisiLink.value }, 'Revisi LPJ diupload.')
const doReviewLpj = () => act('review-lpj', { action: lpjAction.value, lp2m_note: lpjNote.value }, 'Review LPJ berhasil.')
const doPublish = () => act('publish', {}, 'Penelitian dipublikasikan ke repository.')

async function doUploadFinal() {
  if (!laporanFinal.value) { toast.error('File laporan final wajib dipilih.'); return }
  const fd = new FormData()
  fd.append('laporan_final', laporanFinal.value)
  if (paperFinal.value) fd.append('paper_final', paperFinal.value)
  actForm('upload-laporan-final', fd, 'Laporan final diupload.')
}

function toggleReviewer(arr: number[], id: number) {
  const idx = arr.indexOf(id)
  if (idx === -1) arr.push(id)
  else arr.splice(idx, 1)
}

function fundingForStage(stage: number) {
  return item.value?.fundings?.find((f: any) => f.stage === stage)
}
</script>

<template>
  <div v-if="loading" class="text-center py-20 text-gray-400">Memuat...</div>
  <div v-else-if="item" class="space-y-6 max-w-4xl mx-auto">

    <!-- Header -->
    <div class="flex items-start gap-4">
      <button class="p-2 rounded-lg hover:bg-gray-100 text-gray-500 shrink-0" @click="router.back()">
        <ArrowLeftIcon class="w-5 h-5" />
      </button>
      <div class="flex-1 min-w-0">
        <div class="flex items-start justify-between gap-3">
          <div>
            <div class="flex items-center gap-2 mb-1">
              <span class="text-lg">{{ item.type === 'penelitian' ? '🔬' : '🤝' }}</span>
              <span class="text-xs px-2 py-0.5 rounded bg-gray-100 text-gray-600">
                {{ item.type === 'penelitian' ? 'Penelitian' : 'Pengabdian kepada Masyarakat' }}
              </span>
              <span v-if="item.period" class="text-xs text-gray-400">{{ item.period.name }}</span>
            </div>
            <h1 class="text-xl font-bold text-gray-900">{{ item.title }}</h1>
            <p class="text-sm text-gray-500 mt-0.5">
              Ketua: {{ item.ketua?.full_name }}
              <span v-if="item.study_program"> · {{ item.study_program.name }}</span>
            </p>
          </div>
          <div class="flex items-center gap-2 shrink-0">
            <span :class="['text-sm px-3 py-1 rounded-full font-medium', STATUS_COLORS[status] ?? 'bg-gray-100 text-gray-600']">
              {{ STATUS_LABELS[status] ?? status }}
            </span>
            <button v-if="isTeamMember && ['draft','submitted'].includes(status)"
              class="p-2 rounded-lg text-gray-500 hover:bg-gray-100"
              @click="router.push(`/penelitian/${item.id}/edit`)">
              <PencilIcon class="w-4 h-4" />
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Info Kontrak & Dana -->
    <div v-if="item.contract_number" class="bg-green-50 border border-green-200 rounded-xl p-4 grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
      <div><p class="text-green-600 text-xs">No. Kontrak</p><p class="font-semibold text-gray-800">{{ item.contract_number }}</p></div>
      <div><p class="text-green-600 text-xs">Total Dana</p><p class="font-semibold text-gray-800">Rp {{ totalDana }}</p></div>
      <div><p class="text-green-600 text-xs">Tgl. Kontrak</p><p class="font-semibold text-gray-800">{{ item.contract_date ?? '-' }}</p></div>
      <div>
        <p class="text-green-600 text-xs">Pencairan</p>
        <p class="font-semibold text-gray-800">
          <span v-for="s in [1,2,3]" :key="s"
            :class="['inline-block mr-1 px-1.5 py-0.5 rounded text-xs', fundingForStage(s)?.status === 'cair' ? 'bg-green-200 text-green-800' : 'bg-gray-200 text-gray-600']">
            T{{ s }}{{ fundingForStage(s)?.status === 'cair' ? ' ✓' : '' }}
          </span>
        </p>
      </div>
    </div>

    <!-- Tim Peneliti -->
    <div class="bg-white border border-gray-200 rounded-xl p-4">
      <h3 class="font-semibold text-gray-800 mb-3">Tim Peneliti</h3>
      <div class="flex flex-wrap gap-2">
        <span class="px-3 py-1.5 bg-blue-50 text-blue-700 rounded-lg text-sm font-medium">
          👑 {{ item.ketua?.full_name }} (Ketua)
        </span>
        <span v-for="m in item.members" :key="m.id"
          class="px-3 py-1.5 bg-gray-50 text-gray-700 rounded-lg text-sm">
          {{ m.member_type === 'dosen' ? (m.lecturer?.full_name ?? '-') : (m.student?.name ?? '-') }}
          <span class="text-xs text-gray-400">({{ m.member_type }})</span>
        </span>
      </div>
    </div>

    <!-- Dokumen -->
    <div class="bg-white border border-gray-200 rounded-xl p-4">
      <h3 class="font-semibold text-gray-800 mb-3">Dokumen</h3>
      <div class="space-y-2 text-sm">
        <template v-for="(label, key) in {
          proposal_link: 'Proposal', proposal_revision_link: 'Revisi Proposal',
          laporan_kemajuan_link: 'Laporan Kemajuan', laporan_kemajuan_revision_link: 'Revisi Laporan Kemajuan',
          laporan_akhir_link: 'Laporan Akhir', paper_link: 'Paper',
          lpj_link: 'LPJ', lpj_revision_link: 'Revisi LPJ', contract_link: 'Kontrak'
        }" :key="key">
          <a v-if="(item as any)[key]" :href="(item as any)[key]" target="_blank"
            class="flex items-center gap-2 text-blue-600 hover:underline">
            <LinkIcon class="w-4 h-4 shrink-0" /> {{ label }}
          </a>
        </template>
        <p v-if="item.laporan_final_path" class="text-gray-600">📄 Laporan Final (PDF tersimpan)</p>
        <p v-if="item.paper_final_path" class="text-gray-600">📄 Paper Final (PDF tersimpan)</p>
      </div>
    </div>

    <!-- Reviewer & Nilai -->
    <div v-if="item.reviewers?.length" class="bg-white border border-gray-200 rounded-xl p-4">
      <h3 class="font-semibold text-gray-800 mb-3">Reviewer</h3>
      <div class="space-y-2">
        <div v-for="r in item.reviewers" :key="r.id"
          class="flex items-center justify-between text-sm p-2 rounded-lg bg-gray-50">
          <div>
            <span class="font-medium">{{ r.lecturer?.full_name }}</span>
            <span class="ml-2 text-xs text-gray-400">({{ r.stage }})</span>
          </div>
          <div v-if="r.score_total !== null" class="flex items-center gap-2">
            <span class="font-semibold text-blue-700">{{ r.score_total }}/100</span>
            <span :class="['text-xs px-2 py-0.5 rounded-full', r.rekomendasi === 'lolos' ? 'bg-green-100 text-green-700' : r.rekomendasi === 'revisi' ? 'bg-yellow-100 text-yellow-700' : 'bg-red-100 text-red-700']">
              {{ r.rekomendasi }}
            </span>
          </div>
          <span v-else class="text-xs text-gray-400">Belum dinilai</span>
        </div>
      </div>
    </div>

    <!-- Abstrak -->
    <div v-if="item.abstract" class="bg-white border border-gray-200 rounded-xl p-4">
      <h3 class="font-semibold text-gray-800 mb-2">Abstrak</h3>
      <p class="text-sm text-gray-600 leading-relaxed">{{ item.abstract }}</p>
      <p v-if="item.keywords" class="text-xs text-gray-400 mt-2">Kata kunci: {{ item.keywords }}</p>
    </div>

    <!-- Catatan LP2M / Ka.Prodi -->
    <div v-if="item.kaprodi_note || item.lp2m_note" class="bg-amber-50 border border-amber-200 rounded-xl p-4 text-sm space-y-1">
      <p v-if="item.kaprodi_note"><span class="font-medium">Catatan Ka.Prodi:</span> {{ item.kaprodi_note }}</p>
      <p v-if="item.lp2m_note"><span class="font-medium">Catatan LP2M:</span> {{ item.lp2m_note }}</p>
    </div>

    <!-- ============================================================ -->
    <!-- PANEL AKSI PER TAHAP -->
    <!-- ============================================================ -->

    <!-- T2: Dosen submit ke Ka.Prodi -->
    <div v-if="isTeamMember && ['draft','submitted'].includes(status)"
      class="bg-white border border-blue-200 rounded-xl p-4 space-y-3">
      <h3 class="font-semibold text-gray-800">Ajukan ke Ka.Prodi</h3>
      <input v-model="submitLink" type="url" placeholder="Link Google Drive proposal (wajib)"
        class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
      <button :disabled="acting || !submitLink" @click="doSubmitKaprodi"
        class="px-4 py-2 bg-blue-600 hover:bg-blue-700 disabled:opacity-50 text-white text-sm font-medium rounded-lg">
        {{ acting ? 'Memproses...' : 'Ajukan ke Ka.Prodi' }}
      </button>
    </div>

    <!-- T3: Ka.Prodi review -->
    <div v-if="(isAdmin || isLp2m) && status === 'review_kaprodi'"
      class="bg-white border border-yellow-200 rounded-xl p-4 space-y-3">
      <h3 class="font-semibold text-gray-800">Review Ka.Prodi</h3>
      <div class="flex gap-3">
        <label :class="['flex items-center gap-2 px-3 py-2 rounded-lg border cursor-pointer text-sm', kaprodiAction === 'diketahui' ? 'border-green-500 bg-green-50 text-green-700' : 'border-gray-300']">
          <input type="radio" v-model="kaprodiAction" value="diketahui" class="hidden" /> ✓ Diketahui
        </label>
        <label :class="['flex items-center gap-2 px-3 py-2 rounded-lg border cursor-pointer text-sm', kaprodiAction === 'ditolak' ? 'border-red-500 bg-red-50 text-red-700' : 'border-gray-300']">
          <input type="radio" v-model="kaprodiAction" value="ditolak" class="hidden" /> ✗ Ditolak
        </label>
      </div>
      <textarea v-model="kaprodiNote" rows="2" placeholder="Catatan (opsional)"
        class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
      <button :disabled="acting" @click="doReviewKaprodi"
        class="px-4 py-2 bg-yellow-500 hover:bg-yellow-600 disabled:opacity-50 text-white text-sm font-medium rounded-lg">
        {{ acting ? 'Memproses...' : 'Simpan Review' }}
      </button>
    </div>

    <!-- T4a: LP2M tugaskan reviewer -->
    <div v-if="isLp2m && status === 'seleksi_reviewer'"
      class="bg-white border border-blue-200 rounded-xl p-4 space-y-3">
      <h3 class="font-semibold text-gray-800">Tugaskan Reviewer Seleksi (2–3 orang)</h3>
      <div class="max-h-48 overflow-y-auto border border-gray-200 rounded-lg divide-y divide-gray-100">
        <label v-for="lec in lecturers" :key="lec.id"
          class="flex items-center gap-3 px-3 py-2 hover:bg-gray-50 cursor-pointer">
          <input type="checkbox" :checked="reviewerIds.includes(lec.id)"
            @change="toggleReviewer(reviewerIds, lec.id)" class="rounded border-gray-300 text-blue-600" />
          <span class="text-sm">{{ lec.full_name }}</span>
        </label>
      </div>
      <p class="text-xs text-gray-500">{{ reviewerIds.length }} reviewer dipilih</p>
      <div class="flex gap-2">
        <button :disabled="acting || reviewerIds.length < 2" @click="doAssignReviewers"
          class="px-4 py-2 bg-blue-600 hover:bg-blue-700 disabled:opacity-50 text-white text-sm font-medium rounded-lg">
          {{ acting ? 'Memproses...' : 'Tugaskan Reviewer' }}
        </button>
      </div>
      <!-- Dosen: upload revisi proposal jika ada catatan revisi reviewer -->
      <div v-if="isTeamMember" class="pt-3 border-t border-gray-100 space-y-2">
        <p class="text-sm font-medium text-gray-700">Upload Revisi Proposal (jika diminta reviewer)</p>
        <input v-model="revisiProposal" type="url" placeholder="Link Google Drive revisi proposal"
          class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 text-sm" />
        <button :disabled="acting || !revisiProposal" @click="doRevisiProposal"
          class="px-4 py-2 bg-gray-600 hover:bg-gray-700 disabled:opacity-50 text-white text-sm font-medium rounded-lg">
          Simpan Revisi Proposal
        </button>
      </div>
      <!-- LP2M: tetapkan hasil seleksi -->
      <div class="pt-3 border-t border-gray-100 space-y-2">
        <p class="text-sm font-medium text-gray-700">Tetapkan Hasil Seleksi</p>
        <div class="flex gap-3">
          <label :class="['flex items-center gap-2 px-3 py-2 rounded-lg border cursor-pointer text-sm', seleksiResult === 'lolos' ? 'border-green-500 bg-green-50 text-green-700' : 'border-gray-300']">
            <input type="radio" v-model="seleksiResult" value="lolos" class="hidden" /> ✓ Lolos
          </label>
          <label :class="['flex items-center gap-2 px-3 py-2 rounded-lg border cursor-pointer text-sm', seleksiResult === 'tidak_lolos' ? 'border-red-500 bg-red-50 text-red-700' : 'border-gray-300']">
            <input type="radio" v-model="seleksiResult" value="tidak_lolos" class="hidden" /> ✗ Tidak Lolos
          </label>
        </div>
        <textarea v-model="seleksiNote" rows="2" placeholder="Catatan (opsional)"
          class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 text-sm" />
        <button :disabled="acting" @click="doSeleksiResult"
          class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white text-sm font-medium rounded-lg">
          {{ acting ? 'Memproses...' : 'Tetapkan Hasil Seleksi' }}
        </button>
      </div>
    </div>

    <!-- T4b: Reviewer isi penilaian -->
    <div v-if="myReviewAssignment && !myReviewAssignment.reviewed_at && status === 'seleksi_reviewer'"
      class="bg-white border border-purple-200 rounded-xl p-4 space-y-3">
      <h3 class="font-semibold text-gray-800">Form Penilaian Reviewer</h3>
      <div class="grid grid-cols-2 gap-3">
        <div v-for="(label, key) in { score_orisinalitas: 'Orisinalitas', score_metodologi: 'Metodologi', score_manfaat: 'Manfaat', score_kelayakan: 'Kelayakan' }" :key="key">
          <label class="block text-xs font-medium text-gray-600 mb-1">{{ label }} (0–25)</label>
          <input type="number" min="0" max="25" v-model.number="(reviewForm as any)[key]"
            class="w-full px-3 py-2 rounded-lg border border-gray-300 text-sm" />
        </div>
      </div>
      <p class="text-sm text-gray-600">Total: <span class="font-semibold text-blue-700">{{ reviewForm.score_orisinalitas + reviewForm.score_metodologi + reviewForm.score_manfaat + reviewForm.score_kelayakan }}/100</span></p>
      <textarea v-model="reviewForm.catatan" rows="2" placeholder="Catatan reviewer (opsional)"
        class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 text-sm" />
      <div class="flex gap-3">
        <label v-for="opt in [{ val: 'lolos', label: '✓ Lolos', color: 'green' }, { val: 'revisi', label: '↺ Revisi', color: 'yellow' }, { val: 'tidak_lolos', label: '✗ Tidak Lolos', color: 'red' }]"
          :key="opt.val"
          :class="['flex items-center gap-2 px-3 py-2 rounded-lg border cursor-pointer text-sm',
            reviewForm.rekomendasi === opt.val
              ? `border-${opt.color}-500 bg-${opt.color}-50 text-${opt.color}-700`
              : 'border-gray-300']">
          <input type="radio" v-model="reviewForm.rekomendasi" :value="opt.val" class="hidden" />
          {{ opt.label }}
        </label>
      </div>
      <button :disabled="acting" @click="doSubmitReview"
        class="px-4 py-2 bg-purple-600 hover:bg-purple-700 disabled:opacity-50 text-white text-sm font-medium rounded-lg">
        {{ acting ? 'Menyimpan...' : 'Simpan Review' }}
      </button>
    </div>

    <!-- T5: LP2M isi kontrak -->
    <div v-if="isLp2m && status === 'kontrak'"
      class="bg-white border border-purple-200 rounded-xl p-4 space-y-3">
      <h3 class="font-semibold text-gray-800">Penandatanganan Kontrak</h3>
      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="block text-xs font-medium text-gray-600 mb-1">Nomor Kontrak <span class="text-red-500">*</span></label>
          <input v-model="kontrakForm.contract_number" type="text" placeholder="Contoh: 001/LP2M/2026"
            class="w-full px-3 py-2 rounded-lg border border-gray-300 text-sm" />
        </div>
        <div>
          <label class="block text-xs font-medium text-gray-600 mb-1">Total Dana (Rp) <span class="text-red-500">*</span></label>
          <input v-model="kontrakForm.total_dana" type="number" min="0" placeholder="10000000"
            class="w-full px-3 py-2 rounded-lg border border-gray-300 text-sm" />
        </div>
        <div>
          <label class="block text-xs font-medium text-gray-600 mb-1">Tanggal Kontrak</label>
          <input v-model="kontrakForm.contract_date" type="date"
            class="w-full px-3 py-2 rounded-lg border border-gray-300 text-sm" />
        </div>
        <div>
          <label class="block text-xs font-medium text-gray-600 mb-1">Link Kontrak (Drive)</label>
          <input v-model="kontrakForm.contract_link" type="url" placeholder="https://drive.google.com/..."
            class="w-full px-3 py-2 rounded-lg border border-gray-300 text-sm" />
        </div>
      </div>
      <button :disabled="acting || !kontrakForm.contract_number || !kontrakForm.total_dana" @click="doSaveKontrak"
        class="px-4 py-2 bg-purple-600 hover:bg-purple-700 disabled:opacity-50 text-white text-sm font-medium rounded-lg">
        {{ acting ? 'Menyimpan...' : 'Simpan Kontrak' }}
      </button>
    </div>

    <!-- T6/9/13: Keuangan alokasi & cairkan dana -->
    <div v-if="isKeuangan && ['pelaksanaan_1','pelaksanaan_2','selesai'].includes(status)"
      class="bg-white border border-green-200 rounded-xl p-4 space-y-4">
      <h3 class="font-semibold text-gray-800">Pencairan Dana</h3>
      <!-- Tabel status pencairan -->
      <div class="grid grid-cols-3 gap-3">
        <div v-for="s in [1,2,3]" :key="s"
          class="border border-gray-200 rounded-lg p-3 text-sm text-center">
          <p class="font-medium text-gray-700">Tahap {{ s }}</p>
          <p class="text-xs text-gray-400 mt-0.5">{{ s === 1 ? '50%' : s === 2 ? '30%' : '20%' }}</p>
          <div v-if="fundingForStage(s)">
            <p class="font-semibold text-green-700 mt-1">Rp {{ Number(fundingForStage(s).amount).toLocaleString('id-ID') }}</p>
            <span :class="['text-xs px-2 py-0.5 rounded-full mt-1 inline-block', fundingForStage(s).status === 'cair' ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700']">
              {{ fundingForStage(s).status === 'cair' ? 'Cair ✓' : 'Dialokasikan' }}
            </span>
            <button v-if="fundingForStage(s).status === 'alokasi'" :disabled="acting"
              @click="doDisburseFunding(s)"
              class="mt-2 w-full px-2 py-1 bg-green-600 hover:bg-green-700 disabled:opacity-50 text-white text-xs rounded-lg">
              Cairkan
            </button>
          </div>
          <p v-else class="text-xs text-gray-400 mt-1">Belum dialokasikan</p>
        </div>
      </div>
      <!-- Form alokasi -->
      <div class="border-t border-gray-100 pt-3 space-y-3">
        <p class="text-sm font-medium text-gray-700">Alokasi Dana Baru</p>
        <div class="grid grid-cols-3 gap-3">
          <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Tahap</label>
            <select v-model="fundingForm.stage" class="w-full px-3 py-2 rounded-lg border border-gray-300 text-sm">
              <option :value="1">Tahap 1 (50%)</option>
              <option :value="2">Tahap 2 (30%)</option>
              <option :value="3">Tahap 3 (20%)</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Jumlah (Rp)</label>
            <input v-model="fundingForm.amount" type="number" min="0" placeholder="5000000"
              class="w-full px-3 py-2 rounded-lg border border-gray-300 text-sm" />
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Keterangan</label>
            <input v-model="fundingForm.keterangan" type="text" placeholder="Opsional"
              class="w-full px-3 py-2 rounded-lg border border-gray-300 text-sm" />
          </div>
        </div>
        <button :disabled="acting || !fundingForm.amount" @click="doAllocateFunding"
          class="px-4 py-2 bg-green-600 hover:bg-green-700 disabled:opacity-50 text-white text-sm font-medium rounded-lg">
          {{ acting ? 'Memproses...' : 'Alokasi Dana' }}
        </button>
      </div>
    </div>

    <!-- T7: Dosen upload laporan kemajuan -->
    <div v-if="isTeamMember && status === 'pelaksanaan_1'"
      class="bg-white border border-indigo-200 rounded-xl p-4 space-y-3">
      <h3 class="font-semibold text-gray-800">Upload Laporan Kemajuan</h3>
      <input v-model="laporanKemajuan" type="url" placeholder="Link Google Drive laporan kemajuan"
        class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 text-sm" />
      <button :disabled="acting || !laporanKemajuan" @click="doLaporanKemajuan"
        class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white text-sm font-medium rounded-lg">
        {{ acting ? 'Menyimpan...' : 'Upload Laporan Kemajuan' }}
      </button>
    </div>

    <!-- T8: LP2M catat hasil monev -->
    <div v-if="isLp2m && status === 'monev'"
      class="bg-white border border-cyan-200 rounded-xl p-4 space-y-3">
      <h3 class="font-semibold text-gray-800">Monitoring & Evaluasi (Monev)</h3>
      <!-- Tugaskan reviewer monev (opsional) -->
      <details class="text-sm">
        <summary class="cursor-pointer text-gray-600 hover:text-gray-900 font-medium">Tugaskan Reviewer Monev (opsional)</summary>
        <div class="mt-2 space-y-2">
          <div class="max-h-36 overflow-y-auto border border-gray-200 rounded-lg divide-y divide-gray-100">
            <label v-for="lec in lecturers" :key="lec.id"
              class="flex items-center gap-3 px-3 py-2 hover:bg-gray-50 cursor-pointer">
              <input type="checkbox" :checked="monevReviewerIds.includes(lec.id)"
                @change="toggleReviewer(monevReviewerIds, lec.id)" class="rounded border-gray-300 text-blue-600" />
              <span class="text-sm">{{ lec.full_name }}</span>
            </label>
          </div>
          <button :disabled="acting || monevReviewerIds.length === 0" @click="doAssignMonevReviewers"
            class="px-3 py-1.5 bg-gray-600 hover:bg-gray-700 disabled:opacity-50 text-white text-xs font-medium rounded-lg">
            Tugaskan
          </button>
        </div>
      </details>
      <!-- Hasil monev -->
      <div class="flex gap-3">
        <label :class="['flex items-center gap-2 px-3 py-2 rounded-lg border cursor-pointer text-sm', monevResult === 'lanjut' ? 'border-green-500 bg-green-50 text-green-700' : 'border-gray-300']">
          <input type="radio" v-model="monevResult" value="lanjut" class="hidden" /> ✓ Lanjut
        </label>
        <label :class="['flex items-center gap-2 px-3 py-2 rounded-lg border cursor-pointer text-sm', monevResult === 'revisi' ? 'border-orange-500 bg-orange-50 text-orange-700' : 'border-gray-300']">
          <input type="radio" v-model="monevResult" value="revisi" class="hidden" /> ↺ Revisi
        </label>
      </div>
      <textarea v-model="monevNote" rows="2" placeholder="Catatan monev (opsional)"
        class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 text-sm" />
      <button :disabled="acting" @click="doMonevResult"
        class="px-4 py-2 bg-cyan-600 hover:bg-cyan-700 disabled:opacity-50 text-white text-sm font-medium rounded-lg">
        {{ acting ? 'Memproses...' : 'Simpan Hasil Monev' }}
      </button>
    </div>

    <!-- T8: Dosen revisi laporan kemajuan -->
    <div v-if="isTeamMember && status === 'revisi_kemajuan'"
      class="bg-white border border-orange-200 rounded-xl p-4 space-y-3">
      <h3 class="font-semibold text-gray-800">Upload Revisi Laporan Kemajuan</h3>
      <p v-if="item.lp2m_note" class="text-sm text-orange-700 bg-orange-50 rounded-lg px-3 py-2">
        Catatan LP2M: {{ item.lp2m_note }}
      </p>
      <input v-model="revisiKemajuan" type="url" placeholder="Link Google Drive revisi laporan kemajuan"
        class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 text-sm" />
      <button :disabled="acting || !revisiKemajuan" @click="doRevisiKemajuan"
        class="px-4 py-2 bg-orange-600 hover:bg-orange-700 disabled:opacity-50 text-white text-sm font-medium rounded-lg">
        {{ acting ? 'Menyimpan...' : 'Upload Revisi Kemajuan' }}
      </button>
    </div>

    <!-- T10: Dosen upload laporan akhir & paper -->
    <div v-if="isTeamMember && status === 'pelaksanaan_2'"
      class="bg-white border border-indigo-200 rounded-xl p-4 space-y-3">
      <h3 class="font-semibold text-gray-800">Upload Laporan Akhir & Paper</h3>
      <div class="space-y-3">
        <div>
          <label class="block text-xs font-medium text-gray-600 mb-1">Link Laporan Akhir (Google Drive) <span class="text-red-500">*</span></label>
          <input v-model="laporanAkhir.laporan_akhir_link" type="url" placeholder="https://drive.google.com/..."
            class="w-full px-3 py-2 rounded-lg border border-gray-300 text-sm" />
        </div>
        <div>
          <label class="block text-xs font-medium text-gray-600 mb-1">Link Paper (opsional)</label>
          <input v-model="laporanAkhir.paper_link" type="url" placeholder="https://drive.google.com/..."
            class="w-full px-3 py-2 rounded-lg border border-gray-300 text-sm" />
        </div>
        <div>
          <label class="block text-xs font-medium text-gray-600 mb-1">Abstrak (opsional)</label>
          <textarea v-model="laporanAkhir.abstract" rows="3"
            class="w-full px-3 py-2 rounded-lg border border-gray-300 text-sm" />
        </div>
        <div>
          <label class="block text-xs font-medium text-gray-600 mb-1">Daftar Pustaka (opsional)</label>
          <textarea v-model="laporanAkhir.bibliography" rows="2"
            class="w-full px-3 py-2 rounded-lg border border-gray-300 text-sm" />
        </div>
      </div>
      <button :disabled="acting || !laporanAkhir.laporan_akhir_link" @click="doLaporanAkhir"
        class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white text-sm font-medium rounded-lg">
        {{ acting ? 'Menyimpan...' : 'Upload Laporan Akhir' }}
      </button>
    </div>

    <!-- T11a: LP2M tetapkan jadwal seminar -->
    <div v-if="isLp2m && status === 'seminar'"
      class="bg-white border border-teal-200 rounded-xl p-4 space-y-3">
      <h3 class="font-semibold text-gray-800">Seminar Hasil</h3>
      <div v-if="!item.seminar_date" class="space-y-2">
        <label class="block text-xs font-medium text-gray-600">Tanggal Seminar <span class="text-red-500">*</span></label>
        <input v-model="seminarDate" type="date"
          class="w-full px-3 py-2 rounded-lg border border-gray-300 text-sm" />
        <button :disabled="acting || !seminarDate" @click="doSeminarDate"
          class="px-4 py-2 bg-teal-600 hover:bg-teal-700 disabled:opacity-50 text-white text-sm font-medium rounded-lg">
          Tetapkan Jadwal Seminar
        </button>
      </div>
      <div v-else class="space-y-3">
        <p class="text-sm text-teal-700 bg-teal-50 rounded-lg px-3 py-2">
          Jadwal seminar: {{ item.seminar_date }}
        </p>
        <div class="flex gap-3">
          <label :class="['flex items-center gap-2 px-3 py-2 rounded-lg border cursor-pointer text-sm', seminarResult === 'diterima' ? 'border-green-500 bg-green-50 text-green-700' : 'border-gray-300']">
            <input type="radio" v-model="seminarResult" value="diterima" class="hidden" /> ✓ Diterima
          </label>
          <label :class="['flex items-center gap-2 px-3 py-2 rounded-lg border cursor-pointer text-sm', seminarResult === 'revisi' ? 'border-orange-500 bg-orange-50 text-orange-700' : 'border-gray-300']">
            <input type="radio" v-model="seminarResult" value="revisi" class="hidden" /> ↺ Revisi
          </label>
        </div>
        <textarea v-model="seminarNote" rows="2" placeholder="Catatan seminar (opsional)"
          class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 text-sm" />
        <button :disabled="acting" @click="doSeminarResult"
          class="px-4 py-2 bg-teal-600 hover:bg-teal-700 disabled:opacity-50 text-white text-sm font-medium rounded-lg">
          {{ acting ? 'Memproses...' : 'Catat Hasil Seminar' }}
        </button>
      </div>
    </div>

    <!-- T11b: Dosen upload laporan final pasca revisi seminar (file PDF) -->
    <div v-if="isTeamMember && status === 'revisi_seminar'"
      class="bg-white border border-orange-200 rounded-xl p-4 space-y-3">
      <h3 class="font-semibold text-gray-800">Upload Laporan Final (Pasca Revisi Seminar)</h3>
      <p class="text-xs text-gray-500">Upload file PDF langsung, bukan link. Maks. laporan 20 MB, paper 10 MB.</p>
      <p v-if="item.lp2m_note" class="text-sm text-orange-700 bg-orange-50 rounded-lg px-3 py-2">
        Catatan: {{ item.lp2m_note }}
      </p>
      <div class="space-y-2">
        <div>
          <label class="block text-xs font-medium text-gray-600 mb-1">Laporan Final (PDF, wajib)</label>
          <input type="file" accept=".pdf" @change="laporanFinal = ($event.target as HTMLInputElement).files?.[0] ?? null"
            class="w-full text-sm text-gray-600 file:mr-3 file:px-3 file:py-1.5 file:rounded-lg file:border-0 file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100" />
        </div>
        <div>
          <label class="block text-xs font-medium text-gray-600 mb-1">Paper Final (PDF, opsional)</label>
          <input type="file" accept=".pdf" @change="paperFinal = ($event.target as HTMLInputElement).files?.[0] ?? null"
            class="w-full text-sm text-gray-600 file:mr-3 file:px-3 file:py-1.5 file:rounded-lg file:border-0 file:bg-gray-50 file:text-gray-700 hover:file:bg-gray-100" />
        </div>
      </div>
      <button :disabled="acting || !laporanFinal" @click="doUploadFinal"
        class="px-4 py-2 bg-orange-600 hover:bg-orange-700 disabled:opacity-50 text-white text-sm font-medium rounded-lg">
        {{ acting ? 'Mengupload...' : 'Upload Laporan Final' }}
      </button>
    </div>

    <!-- T12a: Dosen upload LPJ -->
    <div v-if="isTeamMember && ['lpj','pelaksanaan_2'].includes(status)"
      class="bg-white border border-violet-200 rounded-xl p-4 space-y-3">
      <h3 class="font-semibold text-gray-800">Upload LPJ (Laporan Pertanggungjawaban Keuangan)</h3>
      <input v-model="lpjLink" type="url" placeholder="Link Google Drive dokumen LPJ"
        class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 text-sm" />
      <button :disabled="acting || !lpjLink" @click="doLpj"
        class="px-4 py-2 bg-violet-600 hover:bg-violet-700 disabled:opacity-50 text-white text-sm font-medium rounded-lg">
        {{ acting ? 'Menyimpan...' : 'Upload LPJ' }}
      </button>
    </div>

    <!-- T12b: LP2M review LPJ -->
    <div v-if="isLp2m && status === 'lpj'"
      class="bg-white border border-violet-200 rounded-xl p-4 space-y-3">
      <h3 class="font-semibold text-gray-800">Review LPJ</h3>
      <div class="flex gap-3">
        <label :class="['flex items-center gap-2 px-3 py-2 rounded-lg border cursor-pointer text-sm', lpjAction === 'terima' ? 'border-green-500 bg-green-50 text-green-700' : 'border-gray-300']">
          <input type="radio" v-model="lpjAction" value="terima" class="hidden" /> ✓ Terima LPJ
        </label>
        <label :class="['flex items-center gap-2 px-3 py-2 rounded-lg border cursor-pointer text-sm', lpjAction === 'revisi' ? 'border-orange-500 bg-orange-50 text-orange-700' : 'border-gray-300']">
          <input type="radio" v-model="lpjAction" value="revisi" class="hidden" /> ↺ Revisi LPJ
        </label>
      </div>
      <textarea v-model="lpjNote" rows="2" placeholder="Catatan revisi (wajib jika revisi)"
        class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 text-sm" />
      <button :disabled="acting" @click="doReviewLpj"
        class="px-4 py-2 bg-violet-600 hover:bg-violet-700 disabled:opacity-50 text-white text-sm font-medium rounded-lg">
        {{ acting ? 'Memproses...' : 'Simpan Review LPJ' }}
      </button>
    </div>

    <!-- T12c: Dosen upload revisi LPJ -->
    <div v-if="isTeamMember && status === 'revisi_lpj'"
      class="bg-white border border-orange-200 rounded-xl p-4 space-y-3">
      <h3 class="font-semibold text-gray-800">Upload Revisi LPJ</h3>
      <p v-if="item.lp2m_note" class="text-sm text-orange-700 bg-orange-50 rounded-lg px-3 py-2">
        Catatan LP2M: {{ item.lp2m_note }}
      </p>
      <input v-model="lpjRevisiLink" type="url" placeholder="Link Google Drive LPJ yang sudah diperbaiki"
        class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 text-sm" />
      <button :disabled="acting || !lpjRevisiLink" @click="doRevisiLpj"
        class="px-4 py-2 bg-orange-600 hover:bg-orange-700 disabled:opacity-50 text-white text-sm font-medium rounded-lg">
        {{ acting ? 'Menyimpan...' : 'Upload Revisi LPJ' }}
      </button>
    </div>

    <!-- T14: LP2M publikasi ke repository -->
    <div v-if="isLp2m && status === 'selesai' && !item.is_published"
      class="bg-white border border-green-200 rounded-xl p-4 space-y-3">
      <h3 class="font-semibold text-gray-800">Publikasikan ke Repository</h3>
      <p class="text-sm text-gray-500">Penelitian akan dapat diakses publik setelah dipublikasikan.</p>
      <button :disabled="acting" @click="doPublish"
        class="px-4 py-2 bg-green-600 hover:bg-green-700 disabled:opacity-50 text-white text-sm font-medium rounded-lg">
        {{ acting ? 'Memproses...' : '🌐 Publikasikan ke Repository' }}
      </button>
    </div>

    <!-- Badge selesai & dipublikasikan -->
    <div v-if="status === 'selesai'" class="bg-green-50 border border-green-200 rounded-xl p-4 text-center">
      <p class="text-2xl mb-1">🎉</p>
      <p class="font-semibold text-green-800">Penelitian Selesai</p>
      <p v-if="item.is_published" class="text-sm text-green-600 mt-1">
        Dipublikasikan ke repository
        <a v-if="item.repository_url" :href="item.repository_url" target="_blank" class="underline ml-1">Lihat →</a>
      </p>
    </div>

  </div>
</template>
