<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useToast } from 'vue-toastification'
import {
  BookOpenIcon, AcademicCapIcon, DocumentTextIcon, 
  ArrowDownTrayIcon, ArrowRightOnRectangleIcon,
} from '@heroicons/vue/24/outline'
import api from '@/services/api'

const props = defineProps<{ section: string }>()
const router = useRouter()
const toast = useToast()
const loading = ref(true)

// Data
const myClasses = ref<any[]>([])
const myRps = ref<any[]>([])
const academicHistory = ref<any>(null)
const transcriptData = ref<any>(null)
const khsData = ref<any>(null)
const selectedSemester = ref('')
const khsLoading = ref(false)

// KKN data
const kknLoading = ref(true)
const myKknPrograms = ref<any[]>([])
const kknTypeColor: Record<string, string> = { KKN: 'bg-green-100 text-green-700', PPL: 'bg-blue-100 text-blue-700', MAGANG: 'bg-purple-100 text-purple-700', PRAKTIKUM: 'bg-orange-100 text-orange-700', PKL: 'bg-teal-100 text-teal-700' }
const kknStatusColor: Record<string, string> = { TERDAFTAR: 'bg-gray-100 text-gray-600', AKTIF: 'bg-green-100 text-green-700', SELESAI: 'bg-blue-100 text-blue-700', MENGUNDURKAN_DIRI: 'bg-yellow-100 text-yellow-700', GAGAL: 'bg-red-100 text-red-600' }

onMounted(async () => {
  try {
    if (props.section === 'kelas' || props.section === 'rps') {
      // Ambil kelas dari KRS yang approved
      const { data } = await api.get('/krs', { params: { status: 'APPROVED' } })
      const krsList = data.data ?? data ?? []
      if (krsList.length > 0) {
        const { data: detail } = await api.get(`/krs/${krsList[0].id}`)
        myClasses.value = detail.details ?? []
      }
    }
    if (props.section === 'praktikum') {
      const { data } = await api.get('/practical-my-programs')
      myKknPrograms.value = data ?? []
      kknLoading.value = false
    }
    if (props.section === 'khs') {
      await loadAcademicHistory()
    }
  } catch { /* silent */ }
  finally { loading.value = false; kknLoading.value = false }
})

async function downloadRps(courseId: number, courseName: string) {
  try {
    // Cari RPKPS untuk course ini
    const { data } = await api.get('/rpkps', { params: { course_id: courseId, status: 'DIKUNCI' } })
    const list = data.data ?? data ?? []
    if (!list.length) {
      const { data: data2 } = await api.get('/rpkps', { params: { course_id: courseId, status: 'DISETUJUI' } })
      const list2 = data2.data ?? data2 ?? []
      if (!list2.length) { toast.info('RPS untuk mata kuliah ini belum tersedia.'); return }
      // Download PDF
      const res = await api.get(`/rpkps/${list2[0].id}/pdf`, { responseType: 'blob' })
      downloadBlob(res.data, `RPS-${courseName}.pdf`)
    } else {
      const res = await api.get(`/rpkps/${list[0].id}/pdf`, { responseType: 'blob' })
      downloadBlob(res.data, `RPS-${courseName}.pdf`)
    }
  } catch { toast.error('Gagal download RPS.') }
}

function downloadBlob(blobData: any, filename: string) {
  const url = URL.createObjectURL(new Blob([blobData], { type: 'application/pdf' }))
  const link = document.createElement('a')
  link.href = url; link.download = filename
  document.body.appendChild(link); link.click(); link.remove()
  URL.revokeObjectURL(url)
}

async function loadAcademicHistory() {
  try {
    const [historyResponse, transcriptResponse] = await Promise.all([
      api.get('/students/me/academic-history'),
      api.get('/grades/transcript'),
    ])
    academicHistory.value = historyResponse.data
    transcriptData.value = transcriptResponse.data
    const summaries = historyResponse.data?.summaries ?? []
    if (summaries.length) {
      selectedSemester.value = String(summaries[0].semester_id)
      await loadKhs()
    }
  } catch {
    toast.error('Gagal memuat riwayat akademik.')
  }
}

async function loadKhs() {
  if (!selectedSemester.value) {
    khsData.value = null
    return
  }
  khsLoading.value = true
  try {
    const { data } = await api.get('/grades/khs', { params: { semester_id: selectedSemester.value } })
    khsData.value = data
  } catch {
    khsData.value = null
    toast.error('Gagal memuat KHS semester.')
  } finally {
    khsLoading.value = false
  }
}

function formatNumber(value: string | number | null | undefined, decimals = 0) {
  if (value === null || value === undefined || value === '') return '-'
  return Number(value).toLocaleString('id-ID', {
    minimumFractionDigits: decimals,
    maximumFractionDigits: decimals,
  })
}
</script>

<template>
  <div class="space-y-6">

    <!-- RPS Mata Kuliah -->
    <template v-if="section === 'rps'">
      <div>
        <h1 class="text-xl font-bold text-gray-900">RPS Mata Kuliah Saya</h1>
        <p class="text-sm text-gray-500 mt-0.5">Lihat dan unduh RPS mata kuliah yang Anda ambil</p>
      </div>
      <div v-if="loading" class="text-center text-gray-400 py-12">Memuat...</div>
      <div v-else-if="!myClasses.length" class="bg-gray-50 border rounded-xl p-8 text-center text-gray-400">
        <p>Belum ada mata kuliah di KRS Anda. Silakan isi KRS terlebih dahulu.</p>
      </div>
      <div v-else class="space-y-3">
        <div v-for="d in myClasses" :key="d.id" class="bg-white rounded-xl border border-gray-200 p-4 flex items-center justify-between">
          <div>
            <p class="font-medium text-gray-900">{{ d.course?.name }}</p>
            <p class="text-xs text-gray-500">{{ d.course?.code }} · {{ d.course?.credits }} SKS · {{ d.class_?.lecturer?.full_name ?? d.class_?.lecturer?.name ?? '-' }}</p>
          </div>
          <button class="flex items-center gap-1.5 px-3 py-1.5 bg-red-50 hover:bg-red-100 text-red-700 border border-red-200 text-xs font-medium rounded-lg" @click="downloadRps(d.course?.id, d.course?.name)">
            <ArrowDownTrayIcon class="w-4 h-4" /> Download RPS
          </button>
        </div>
      </div>
    </template>

    <!-- Kelas Saya -->
    <template v-else-if="section === 'kelas'">
      <div>
        <h1 class="text-xl font-bold text-gray-900">Kelas Saya</h1>
        <p class="text-sm text-gray-500 mt-0.5">Masuk ke kelas untuk melihat materi, tugas, dan presensi</p>
      </div>
      <div v-if="loading" class="text-center text-gray-400 py-12">Memuat...</div>
      <div v-else-if="!myClasses.length" class="bg-gray-50 border rounded-xl p-8 text-center text-gray-400">
        <p>Belum ada kelas. Pastikan KRS sudah disetujui.</p>
      </div>
      <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div v-for="d in myClasses" :key="d.id" class="bg-white rounded-xl border border-gray-200 p-5 hover:border-blue-300 hover:shadow-md transition-all cursor-pointer" @click="router.push(`/perkuliahan/${d.class_?.id}`)">
          <div class="flex items-center gap-3 mb-2">
            <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
              <BookOpenIcon class="w-5 h-5 text-blue-600" />
            </div>
            <div class="flex-1 min-w-0">
              <p class="font-medium text-gray-900 truncate">{{ d.course?.name }}</p>
              <p class="text-xs text-gray-500">{{ d.course?.code }} · {{ d.class_?.name }}</p>
            </div>
          </div>
          <div class="text-xs text-gray-500 space-y-0.5">
            <p>Dosen: {{ d.class_?.lecturer?.full_name ?? d.class_?.lecturer?.name ?? '-' }}</p>
            <p>Jadwal: {{ d.class_?.schedules?.[0] ? d.class_.schedules[0].day + ' ' + d.class_.schedules[0].start_time?.slice(0,5) + '-' + d.class_.schedules[0].end_time?.slice(0,5) : '-' }}</p>
            <p>Ruangan: {{ d.class_?.room?.name ?? d.class_?.schedules?.[0]?.room?.name ?? '-' }}</p>
          </div>
          <div class="mt-3 flex items-center gap-1 text-xs text-blue-600 font-medium">
            <ArrowRightOnRectangleIcon class="w-3.5 h-3.5" /> Masuk Kelas
          </div>
        </div>
      </div>
    </template>

    <!-- KHS / Transkrip -->
    <template v-else-if="section === 'khs'">
      <div>
        <h1 class="text-xl font-bold text-gray-900">Riwayat Akademik, KHS & Transkrip</h1>
        <p class="text-sm text-gray-500 mt-0.5">Lihat perkembangan IP, IPK, SKS, dan nilai setiap semester</p>
      </div>
      <div v-if="loading" class="text-center text-gray-400 py-12">Memuat...</div>
      <div v-else-if="!academicHistory" class="bg-white rounded-xl border border-gray-200 p-8 text-center text-gray-400">
        Riwayat akademik belum dapat dimuat.
      </div>
      <div v-else class="space-y-6">
        <div class="bg-white rounded-xl border border-gray-200 p-5">
          <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-2">
            <div>
              <p class="text-lg font-semibold text-gray-900">{{ academicHistory.student?.name }}</p>
              <p class="text-xs text-gray-500 font-mono">{{ academicHistory.student?.nim }} · {{ academicHistory.study_program?.name ?? '-' }}</p>
            </div>
            <span class="self-start md:self-auto px-3 py-1 rounded-full bg-green-100 text-green-700 text-xs font-semibold">{{ academicHistory.student?.status }}</span>
          </div>
        </div>

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
          <div class="bg-blue-50 border border-blue-100 rounded-xl p-4"><p class="text-xs text-blue-600">IP Semester Terakhir</p><p class="text-2xl font-bold text-blue-800 mt-1">{{ formatNumber(academicHistory.summaries?.[0]?.semester_gpa, 2) }}</p></div>
          <div class="bg-indigo-50 border border-indigo-100 rounded-xl p-4"><p class="text-xs text-indigo-600">IPK Terakhir</p><p class="text-2xl font-bold text-indigo-800 mt-1">{{ formatNumber(academicHistory.summaries?.[0]?.cumulative_gpa, 2) }}</p></div>
          <div class="bg-emerald-50 border border-emerald-100 rounded-xl p-4"><p class="text-xs text-emerald-600">Total SKS</p><p class="text-2xl font-bold text-emerald-800 mt-1">{{ formatNumber(academicHistory.summaries?.[0]?.total_credits) }}</p></div>
          <div class="bg-amber-50 border border-amber-100 rounded-xl p-4"><p class="text-xs text-amber-600">Semester Tercatat</p><p class="text-2xl font-bold text-amber-800 mt-1">{{ academicHistory.summaries?.length ?? 0 }}</p></div>
        </div>

        <div class="bg-white rounded-xl border border-gray-200 p-5 space-y-3">
          <h2 class="font-semibold text-gray-900 flex items-center gap-2"><AcademicCapIcon class="w-5 h-5 text-blue-600" /> Riwayat Akademik per Semester</h2>
          <div v-if="!academicHistory.summaries?.length" class="text-sm text-center text-gray-400 py-6">Belum ada riwayat akademik.</div>
          <div v-else class="overflow-x-auto">
            <table class="w-full min-w-[850px] text-sm">
              <thead><tr class="text-left text-xs text-gray-400 border-b"><th class="pb-2">Semester</th><th class="pb-2">Status</th><th class="pb-2 text-right">IP</th><th class="pb-2 text-right">IPK</th><th class="pb-2 text-right">Batas SKS</th><th class="pb-2 text-right">Diambil</th><th class="pb-2 text-right">Wajib</th><th class="pb-2 text-right">Pilihan</th><th class="pb-2 text-right">Total</th></tr></thead>
              <tbody>
                <tr v-for="summary in academicHistory.summaries" :key="summary.id" class="border-b border-gray-50">
                  <td class="py-2.5 whitespace-nowrap text-gray-700">{{ summary.semester?.name ?? '-' }}</td><td class="py-2.5"><span class="px-2 py-0.5 rounded-full bg-gray-100 text-gray-600 text-xs">{{ summary.status }}</span></td>
                  <td class="py-2.5 text-right font-mono">{{ formatNumber(summary.semester_gpa, 2) }}</td><td class="py-2.5 text-right font-mono font-semibold">{{ formatNumber(summary.cumulative_gpa, 2) }}</td>
                  <td class="py-2.5 text-right">{{ formatNumber(summary.credit_limit) }}</td><td class="py-2.5 text-right">{{ formatNumber(summary.credits_taken) }}</td><td class="py-2.5 text-right">{{ formatNumber(summary.required_credits) }}</td><td class="py-2.5 text-right">{{ formatNumber(summary.elective_credits) }}</td><td class="py-2.5 text-right font-semibold">{{ formatNumber(summary.total_credits) }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <div class="bg-white rounded-xl border border-gray-200 p-5 space-y-4">
          <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div><h2 class="font-semibold text-gray-900 flex items-center gap-2"><BookOpenIcon class="w-5 h-5 text-blue-600" /> Kartu Hasil Studi</h2><p class="text-xs text-gray-500 mt-0.5">Pilih semester untuk melihat nilai mata kuliah</p></div>
            <select v-model="selectedSemester" class="border border-gray-300 rounded-lg px-3 py-2 text-sm" @change="loadKhs">
              <option v-for="summary in academicHistory.summaries" :key="summary.semester_id" :value="String(summary.semester_id)">{{ summary.semester?.name }}</option>
            </select>
          </div>
          <div v-if="khsLoading" class="text-center text-gray-400 py-6">Memuat KHS...</div>
          <div v-else-if="!khsData?.grades?.length" class="text-center text-gray-400 py-6">Belum ada nilai pada semester ini.</div>
          <div v-else class="overflow-x-auto">
            <table class="w-full min-w-[650px] text-sm">
              <thead><tr class="text-left text-xs text-gray-400 border-b"><th class="pb-2">Kode</th><th class="pb-2">Mata Kuliah</th><th class="pb-2 text-center">SKS</th><th class="pb-2 text-center">Nilai</th><th class="pb-2 text-center">Bobot</th></tr></thead>
              <tbody><tr v-for="grade in khsData.grades" :key="grade.id" class="border-b border-gray-50"><td class="py-2.5 font-mono text-xs">{{ grade.course?.code }}</td><td class="py-2.5">{{ grade.course?.name }}</td><td class="py-2.5 text-center">{{ grade.course?.credits }}</td><td class="py-2.5 text-center font-bold">{{ grade.letter_grade ?? '-' }}</td><td class="py-2.5 text-center">{{ formatNumber(grade.grade_point, 2) }}</td></tr></tbody>
              <tfoot><tr class="bg-blue-50 font-semibold"><td colspan="2" class="px-3 py-2.5">Ringkasan KHS</td><td class="px-3 py-2.5 text-center">{{ khsData.total_credits }} SKS</td><td colspan="2" class="px-3 py-2.5 text-right">IP: {{ formatNumber(khsData.ips, 2) }}</td></tr></tfoot>
            </table>
          </div>
        </div>

        <div class="bg-white rounded-xl border border-gray-200 p-5 space-y-3">
          <div class="flex items-center justify-between"><h2 class="font-semibold text-gray-900 flex items-center gap-2"><DocumentTextIcon class="w-5 h-5 text-green-600" /> Transkrip Nilai</h2><div class="text-right"><p class="text-xl font-bold text-green-700">{{ formatNumber(transcriptData?.ipk, 2) }}</p><p class="text-[10px] text-gray-500">IPK · {{ transcriptData?.total_credits ?? 0 }} SKS bernilai</p></div></div>
          <div v-if="!transcriptData?.grades?.length" class="text-center text-gray-400 py-6">Belum ada data transkrip.</div>
          <div v-else class="overflow-x-auto">
            <table class="w-full min-w-[750px] text-sm">
              <thead><tr class="text-left text-xs text-gray-400 border-b"><th class="pb-2">Semester</th><th class="pb-2">Kode</th><th class="pb-2">Mata Kuliah</th><th class="pb-2 text-center">SKS</th><th class="pb-2 text-center">Nilai</th><th class="pb-2 text-center">Bobot</th></tr></thead>
              <tbody><tr v-for="grade in transcriptData.grades" :key="grade.id" class="border-b border-gray-50"><td class="py-2.5 text-xs whitespace-nowrap">{{ grade.semester?.name }}</td><td class="py-2.5 font-mono text-xs">{{ grade.course?.code }}</td><td class="py-2.5">{{ grade.course?.name }}</td><td class="py-2.5 text-center">{{ grade.course?.credits }}</td><td class="py-2.5 text-center font-bold">{{ grade.letter_grade ?? '-' }}</td><td class="py-2.5 text-center">{{ formatNumber(grade.grade_point, 2) }}</td></tr></tbody>
            </table>
          </div>
        </div>
      </div>
    </template>

    <!-- Praktikum / KKN -->
    <template v-else-if="section === 'praktikum'">
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-xl font-bold text-gray-900">Praktikum / KKN / Magang</h1>
          <p class="text-sm text-gray-500 mt-0.5">Program yang sedang dan pernah Anda ikuti</p>
        </div>
        <button class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg" @click="router.push('/praktikum')">Cari Program Baru</button>
      </div>

      <div v-if="kknLoading" class="text-center py-12 text-gray-400">Memuat...</div>

      <div v-else-if="!myKknPrograms.length" class="bg-white rounded-xl border border-gray-200 p-8 text-center">
        <AcademicCapIcon class="w-12 h-12 text-gray-300 mx-auto mb-3" />
        <p class="text-sm text-gray-500 font-medium">Anda belum terdaftar di program KKN/Praktikum apapun</p>
        <p class="text-xs text-gray-400 mt-1">Klik tombol di atas untuk mencari dan mendaftar ke program yang tersedia</p>
      </div>

      <div v-else class="space-y-4">
        <div v-for="p in myKknPrograms" :key="p.id" class="bg-white rounded-xl border border-gray-200 overflow-hidden">
          <!-- Header program -->
          <div class="p-5 border-b border-gray-100">
            <div class="flex items-start justify-between">
              <div>
                <div class="flex items-center gap-2 mb-1">
                  <span :class="['text-xs px-2 py-0.5 rounded font-bold', kknTypeColor[p.program?.program_type] ?? 'bg-gray-100 text-gray-600']">{{ p.program?.program_type }}</span>
                  <span :class="['text-xs px-2 py-0.5 rounded-full font-medium', kknStatusColor[p.status] ?? 'bg-gray-100 text-gray-600']">{{ p.status }}</span>
                </div>
                <h3 class="text-base font-semibold text-gray-900">{{ p.program?.name }}</h3>
                <p class="text-xs text-gray-500 mt-0.5">{{ p.program?.semester?.name }} · {{ p.group?.name ?? 'Belum dikelompokkan' }} · {{ p.location?.name ?? 'Belum ada lokasi' }}</p>
                <p v-if="p.supervisor" class="text-xs text-gray-500 mt-0.5">Pembimbing 1: <strong>{{ p.supervisor.name }}</strong></p>
                <p v-if="p.supervisor2" class="text-xs text-gray-500 mt-0.5">Pembimbing 2: <strong>{{ p.supervisor2.name }}</strong></p>
              </div>
              <button class="px-3 py-1.5 text-xs bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg" @click="router.push(`/praktikum/peserta/${p.id}`)">
                Buka Detail
              </button>
            </div>
          </div>
          <!-- Quick actions -->
          <div class="px-5 py-3 bg-gray-50 flex flex-wrap gap-2">
            <button class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-lg border border-gray-200 bg-white hover:bg-blue-50 hover:border-blue-200 text-gray-700" @click="router.push(`/praktikum/peserta/${p.id}`)">
              📝 Logbook
            </button>
            <button class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-lg border border-gray-200 bg-white hover:bg-green-50 hover:border-green-200 text-gray-700" @click="router.push(`/praktikum/peserta/${p.id}`)">
              ✅ Presensi
            </button>
            <button class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-lg border border-gray-200 bg-white hover:bg-purple-50 hover:border-purple-200 text-gray-700" @click="router.push(`/praktikum/peserta/${p.id}`)">
              📄 Laporan
            </button>
          </div>
          <!-- Ringkasan Logbook & Presensi -->
          <div class="px-5 py-3 border-t border-gray-100 grid grid-cols-2 md:grid-cols-4 gap-3 text-center">
            <div class="p-2 bg-blue-50 rounded-lg">
              <p class="text-lg font-bold text-blue-700">{{ p.logbooks_count ?? 0 }}</p>
              <p class="text-[10px] text-blue-600">Logbook</p>
            </div>
            <div class="p-2 bg-yellow-50 rounded-lg">
              <p class="text-lg font-bold text-yellow-700">{{ p.logbooks_revision ?? 0 }}</p>
              <p class="text-[10px] text-yellow-600">Perlu Revisi</p>
            </div>
            <div class="p-2 bg-green-50 rounded-lg">
              <p class="text-lg font-bold text-green-700">{{ p.attendances_count ?? 0 }}</p>
              <p class="text-[10px] text-green-600">Presensi</p>
            </div>
            <div class="p-2 bg-emerald-50 rounded-lg">
              <p class="text-lg font-bold text-emerald-700">{{ p.logbooks_approved ?? 0 }}</p>
              <p class="text-[10px] text-emerald-600">Disetujui</p>
            </div>
          </div>
        </div>
      </div>
    </template>

    <!-- Skripsi / TA -->
    <template v-else-if="section === 'skripsi'">
      <div>
        <h1 class="text-xl font-bold text-gray-900">Skripsi / Tugas Akhir</h1>
        <p class="text-sm text-gray-500 mt-0.5">Ajukan judul, bimbingan, dan daftar sidang</p>
      </div>
      <div class="bg-white rounded-xl border border-gray-200 p-8 text-center">
        <DocumentTextIcon class="w-12 h-12 text-gray-300 mx-auto mb-3" />
        <p class="text-sm text-gray-500">Kelola skripsi/tugas akhir Anda.</p>
        <button class="mt-3 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm rounded-lg" @click="router.push('/skripsi')">Buka Halaman Skripsi</button>
      </div>
    </template>

    <!-- Cuti Akademik -->
    <template v-else-if="section === 'cuti'">
      <div>
        <h1 class="text-xl font-bold text-gray-900">Cuti Akademik</h1>
        <p class="text-sm text-gray-500 mt-0.5">Ajukan atau perpanjang cuti akademik</p>
      </div>
      <div class="bg-white rounded-xl border border-gray-200 p-8 text-center">
        <DocumentTextIcon class="w-12 h-12 text-gray-300 mx-auto mb-3" />
        <p class="text-sm text-gray-500">Ajukan cuti akademik Anda.</p>
        <button class="mt-3 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm rounded-lg" @click="router.push('/akademik/cuti')">Buka Halaman Cuti</button>
      </div>
    </template>

    <!-- Wisuda -->
    <template v-else-if="section === 'wisuda'">
      <div>
        <h1 class="text-xl font-bold text-gray-900">Pendaftaran Wisuda</h1>
        <p class="text-sm text-gray-500 mt-0.5">Daftar wisuda setelah memenuhi semua syarat kelulusan</p>
      </div>
      <div class="bg-white rounded-xl border border-gray-200 p-8 text-center">
        <AcademicCapIcon class="w-12 h-12 text-gray-300 mx-auto mb-3" />
        <p class="text-sm text-gray-500">Daftar wisuda jika Anda sudah memenuhi syarat.</p>
        <button class="mt-3 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm rounded-lg" @click="router.push('/wisuda')">Buka Halaman Wisuda</button>
      </div>
    </template>

  </div>
</template>
