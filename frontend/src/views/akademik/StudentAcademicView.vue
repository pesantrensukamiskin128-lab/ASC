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
  } catch { /* silent */ }
  finally { loading.value = false }
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

async function downloadKhs() {
  try {
    const { data } = await api.get('/grades/khs', { params: { student_id: 'self' }, responseType: 'blob' as any })
    // KHS is JSON, not PDF yet — show info
    toast.info('Fitur cetak KHS PDF sedang dalam pengembangan. Data KHS tersedia di sistem.')
  } catch { toast.error('Gagal memuat KHS.') }
}

async function downloadTranskrip() {
  try {
    const { data } = await api.get('/grades/transcript', { params: { student_id: 'self' }, responseType: 'blob' as any })
    toast.info('Fitur cetak Transkrip PDF sedang dalam pengembangan. Data transkrip tersedia di sistem.')
  } catch { toast.error('Gagal memuat transkrip.') }
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
        <h1 class="text-xl font-bold text-gray-900">KHS & Transkrip Nilai</h1>
        <p class="text-sm text-gray-500 mt-0.5">Lihat dan cetak Kartu Hasil Studi dan Transkrip Akademik</p>
      </div>
      <div v-if="loading" class="text-center text-gray-400 py-12">Memuat...</div>
      <div v-else class="space-y-4">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div class="bg-white rounded-xl border border-gray-200 p-5 text-center hover:shadow-md transition-shadow">
            <BookOpenIcon class="w-10 h-10 text-blue-500 mx-auto mb-3" />
            <h3 class="font-semibold text-gray-900">KHS Semester Ini</h3>
            <p class="text-xs text-gray-500 mt-1 mb-3">Kartu Hasil Studi semester berjalan</p>
            <button class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm rounded-lg" @click="downloadKhs">Download KHS (PDF)</button>
          </div>
          <div class="bg-white rounded-xl border border-gray-200 p-5 text-center hover:shadow-md transition-shadow">
            <DocumentTextIcon class="w-10 h-10 text-green-500 mx-auto mb-3" />
            <h3 class="font-semibold text-gray-900">Transkrip Nilai</h3>
            <p class="text-xs text-gray-500 mt-1 mb-3">Transkrip lengkap semua semester</p>
            <button class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white text-sm rounded-lg" @click="downloadTranskrip">Download Transkrip (PDF)</button>
          </div>
        </div>
      </div>
    </template>

    <!-- Praktikum / KKN -->
    <template v-else-if="section === 'praktikum'">
      <div>
        <h1 class="text-xl font-bold text-gray-900">Praktikum / KKN / Magang</h1>
        <p class="text-sm text-gray-500 mt-0.5">Daftar dan ikuti program praktikum, KKN, atau magang</p>
      </div>
      <div class="bg-white rounded-xl border border-gray-200 p-8 text-center">
        <AcademicCapIcon class="w-12 h-12 text-gray-300 mx-auto mb-3" />
        <p class="text-sm text-gray-500">Fitur pendaftaran praktikum/KKN tersedia di halaman khusus.</p>
        <button class="mt-3 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm rounded-lg" @click="router.push('/praktikum')">Buka Halaman Praktikum</button>
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
