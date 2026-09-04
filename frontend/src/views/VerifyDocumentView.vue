<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import axios from 'axios'

const route = useRoute()

const loading = ref(true)
const result = ref<any>(null)
const isValid = ref(false)
const errorMsg = ref('')

const docType = route.params.type as string
const docId = route.params.id as string
const signer = (route.query.signer as string) || ''

const BASE_URL = import.meta.env.VITE_API_URL ?? 'http://localhost:8000/api'

onMounted(async () => {
  try {
    const url = `${BASE_URL}/verify/${docType}/${docId}`
    const { data } = await axios.get(url, { params: signer ? { signer } : {} })
    result.value = data
    isValid.value = data.valid === true
  } catch (e: any) {
    const msg = e?.response?.data?.message
    errorMsg.value = msg ?? 'Terjadi kesalahan saat memverifikasi dokumen.'
    isValid.value = false
  } finally { loading.value = false }
})

function docTypeLabel(type: string): string {
  const map: Record<string, string> = {
    krs: 'Kartu Rencana Studi (KRS)',
    rpkps: 'RPS/RPKPS',
    'academic-calendar': 'Kalender Akademik',
    surat: 'Surat Keluar',
    khs: 'Kartu Hasil Studi (KHS)',
    transcript: 'Transkrip Nilai Akademik',
    'event-attendance': 'Daftar Hadir Agenda',
    'pmb-card': 'Kartu Peserta PMB',
  }
  return map[type] ?? type.toUpperCase()
}
</script>

<template>
  <div class="min-h-screen bg-gradient-to-br from-blue-900 via-blue-800 to-emerald-700 flex items-center justify-center p-4 relative overflow-hidden">
    <!-- Background decoration -->
    <div class="absolute inset-0 overflow-hidden pointer-events-none">
      <div class="absolute -top-40 -right-40 w-96 h-96 rounded-full bg-white/5 blur-3xl"></div>
      <div class="absolute -bottom-40 -left-40 w-96 h-96 rounded-full bg-white/5 blur-3xl"></div>
      <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[600px] h-[600px] rounded-full bg-white/[0.02] blur-3xl"></div>
    </div>

    <div class="w-full max-w-lg relative z-10">
      <!-- Loading State -->
      <div v-if="loading" class="bg-white rounded-3xl p-12 text-center shadow-2xl">
        <div class="w-16 h-16 border-4 border-blue-200 border-t-blue-600 rounded-full animate-spin mx-auto mb-4"></div>
        <p class="text-gray-500 font-medium">Memverifikasi dokumen...</p>
      </div>

      <!-- Invalid / Error State -->
      <div v-else-if="!isValid" class="bg-white rounded-3xl p-10 text-center shadow-2xl">
        <div class="w-20 h-20 rounded-full bg-red-100 flex items-center justify-center mx-auto mb-5">
          <svg class="w-10 h-10 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
          </svg>
        </div>
        <h1 class="text-2xl font-bold text-gray-900 mb-2">Dokumen Tidak Valid</h1>
        <p class="text-gray-500 text-sm leading-relaxed">
          {{ errorMsg || result?.message || 'Dokumen tidak ditemukan atau token tidak valid. Pastikan QR Code yang Anda scan berasal dari dokumen resmi.' }}
        </p>
        <div class="mt-6 p-4 bg-red-50 rounded-xl border border-red-100">
          <p class="text-xs text-red-600">
            ⚠️ Dokumen ini tidak dapat diverifikasi keasliannya melalui sistem Al-Jawami Smart Campus.
          </p>
        </div>
      </div>

      <!-- Valid State -->
      <div v-else class="bg-white rounded-3xl shadow-2xl overflow-hidden">
        <!-- Header with gradient -->
        <div class="bg-gradient-to-r from-blue-700 to-blue-600 px-8 py-8 text-center">
          <div class="w-20 h-20 rounded-full bg-white/20 flex items-center justify-center mx-auto mb-4">
            <svg class="w-10 h-10 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/>
            </svg>
          </div>
          <h1 class="text-2xl font-bold text-white mb-1">Dokumen Terverifikasi</h1>
          <p class="text-blue-200 text-sm">Dokumen ini asli dan telah ditandatangani secara elektronik</p>
        </div>

        <!-- Content -->
        <div class="p-6 space-y-5">
          <!-- Institution info -->
          <div class="flex items-start gap-3 p-4 bg-blue-50 rounded-xl border border-blue-100">
            <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center flex-shrink-0">
              <svg class="w-5 h-5 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
              </svg>
            </div>
            <div>
              <p class="text-xs text-gray-500 mb-0.5">Diterbitkan oleh</p>
              <p class="font-semibold text-blue-800">STAI YAPATA AL-JAWAMI BANDUNG</p>
            </div>
          </div>

          <!-- Document Info - Surat -->
          <div v-if="docType === 'surat'" class="space-y-3">
            <h3 class="text-sm font-semibold text-gray-700 uppercase tracking-wider">Informasi Dokumen</h3>
            <div class="space-y-3">
              <div class="flex items-start gap-3">
                <svg class="w-4 h-4 text-gray-400 mt-0.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                <div><p class="text-xs text-gray-400">Jenis Dokumen</p><p class="text-sm font-medium text-gray-800">{{ result.letter_type }}</p></div>
              </div>
              <div class="flex items-start gap-3">
                <svg class="w-4 h-4 text-gray-400 mt-0.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M7 20l4-16m2 16l4-16M6 9h14M4 15h14"/></svg>
                <div><p class="text-xs text-gray-400">Nomor Surat</p><p class="text-sm font-medium text-gray-800 font-mono">{{ result.letter_number }}</p></div>
              </div>
              <div class="flex items-start gap-3">
                <svg class="w-4 h-4 text-gray-400 mt-0.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                <div><p class="text-xs text-gray-400">Perihal</p><p class="text-sm font-medium text-gray-800">{{ result.subject }}</p></div>
              </div>
              <div class="flex items-start gap-3">
                <svg class="w-4 h-4 text-gray-400 mt-0.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                <div><p class="text-xs text-gray-400">Tanggal Surat</p><p class="text-sm font-medium text-gray-800">{{ result.letter_date }}</p></div>
              </div>
              <div class="flex items-start gap-3">
                <svg class="w-4 h-4 text-gray-400 mt-0.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                <div><p class="text-xs text-gray-400">Ditujukan Kepada</p><p class="text-sm font-medium text-gray-800">{{ result.recipient }}</p></div>
              </div>
            </div>
          </div>

          <!-- Document Info - KRS -->
          <div v-if="['khs', 'transcript'].includes(docType)" class="space-y-3">
            <h3 class="text-sm font-semibold text-gray-700 uppercase tracking-wider">Informasi Dokumen</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
              <div><p class="text-xs text-gray-400">Jenis Dokumen</p><p class="font-medium text-gray-800">{{ docTypeLabel(docType) }}</p></div>
              <div><p class="text-xs text-gray-400">NIM</p><p class="font-medium text-gray-800 font-mono">{{ result.student?.nim }}</p></div>
              <div><p class="text-xs text-gray-400">Mahasiswa</p><p class="font-medium text-gray-800">{{ result.student?.name }}</p></div>
              <div><p class="text-xs text-gray-400">Program Studi</p><p class="font-medium text-gray-800">{{ result.student?.study_program }}</p></div>
              <div v-if="result.semester"><p class="text-xs text-gray-400">Semester</p><p class="font-medium text-gray-800">{{ result.semester }}</p></div>
              <div><p class="text-xs text-gray-400">Mata Kuliah</p><p class="font-medium text-gray-800">{{ result.courses_count }} mata kuliah</p></div>
              <div><p class="text-xs text-gray-400">Total SKS</p><p class="font-medium text-gray-800">{{ result.total_credits }} SKS</p></div>
              <div><p class="text-xs text-gray-400">{{ docType === 'khs' ? 'IP Semester' : 'IP Kumulatif' }}</p><p class="font-semibold text-blue-700">{{ result.gpa }}</p></div>
            </div>
            <div class="flex items-start gap-3 p-3 bg-green-50 rounded-lg border border-green-100">
              <div><p class="text-xs text-gray-500">Ditandatangani secara elektronik oleh</p><p class="text-sm font-semibold text-gray-800">{{ result.signed_by ?? '-' }}</p><p class="text-xs text-gray-500">{{ result.signer_position }} · {{ result.issued_at }}</p></div>
            </div>
          </div>

          <div v-if="docType === 'event-attendance'" class="space-y-3">
            <h3 class="text-sm font-semibold text-gray-700 uppercase tracking-wider">Informasi Dokumen</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
              <div class="sm:col-span-2"><p class="text-xs text-gray-400">Agenda</p><p class="font-medium text-gray-800">{{ result.event?.title }}</p></div>
              <div><p class="text-xs text-gray-400">Tanggal</p><p class="font-medium text-gray-800">{{ result.event?.date }}</p></div>
              <div><p class="text-xs text-gray-400">Tempat</p><p class="font-medium text-gray-800">{{ result.event?.location ?? '-' }}</p></div>
              <div><p class="text-xs text-gray-400">Penyelenggara</p><p class="font-medium text-gray-800">{{ result.event?.organizer ?? '-' }}</p></div>
              <div><p class="text-xs text-gray-400">Total Hadir</p><p class="font-medium text-gray-800">{{ result.event?.attendances_count }} orang</p></div>
            </div>
          </div>

          <div v-if="docType === 'pmb-card'" class="space-y-3">
            <h3 class="text-sm font-semibold text-gray-700 uppercase tracking-wider">Informasi Peserta</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
              <div><p class="text-xs text-gray-400">Nomor Pendaftaran</p><p class="font-medium text-gray-800 font-mono">{{ result.registrant?.registration_number }}</p></div>
              <div><p class="text-xs text-gray-400">Nama</p><p class="font-medium text-gray-800">{{ result.registrant?.name }}</p></div>
              <div><p class="text-xs text-gray-400">Periode</p><p class="font-medium text-gray-800">{{ result.registrant?.period ?? '-' }}</p></div>
              <div><p class="text-xs text-gray-400">Jalur</p><p class="font-medium text-gray-800">{{ result.registrant?.path ?? '-' }}</p></div>
              <div><p class="text-xs text-gray-400">Pilihan Program Studi</p><p class="font-medium text-gray-800">{{ result.registrant?.study_program ?? '-' }}</p></div>
              <div><p class="text-xs text-gray-400">Status</p><p class="font-semibold text-blue-700">{{ result.registrant?.status }}</p></div>
            </div>
          </div>

          <!-- Document Info - KRS -->
          <div v-if="docType === 'krs'" class="space-y-3">
            <h3 class="text-sm font-semibold text-gray-700 uppercase tracking-wider">Informasi Dokumen</h3>
            <div class="space-y-3">
              <div class="flex items-start gap-3">
                <svg class="w-4 h-4 text-gray-400 mt-0.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                <div><p class="text-xs text-gray-400">Mahasiswa</p><p class="text-sm font-medium text-gray-800">{{ result.student?.name }}</p></div>
              </div>
              <div class="flex items-start gap-3">
                <svg class="w-4 h-4 text-gray-400 mt-0.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M7 20l4-16m2 16l4-16M6 9h14M4 15h14"/></svg>
                <div><p class="text-xs text-gray-400">NIM</p><p class="text-sm font-medium text-gray-800 font-mono">{{ result.student?.nim }}</p></div>
              </div>
              <div class="flex items-start gap-3">
                <svg class="w-4 h-4 text-gray-400 mt-0.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                <div><p class="text-xs text-gray-400">Program Studi</p><p class="text-sm font-medium text-gray-800">{{ result.student?.study_program }}</p></div>
              </div>
              <div class="flex items-start gap-3">
                <svg class="w-4 h-4 text-gray-400 mt-0.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                <div><p class="text-xs text-gray-400">Semester / Total SKS</p><p class="text-sm font-medium text-gray-800">Semester {{ result.semester }} · {{ result.total_credits }} SKS</p></div>
              </div>
            </div>
            <!-- Signature timeline -->
            <div class="pt-3 border-t border-gray-100 space-y-2">
              <h4 class="text-xs font-semibold text-gray-500 uppercase">Riwayat Tanda Tangan</h4>
              <div v-if="result.submitted_at" class="flex items-start gap-3 p-3 bg-green-50 rounded-lg">
                <svg class="w-5 h-5 text-green-600 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/></svg>
                <div><p class="text-sm font-medium text-gray-800">Diajukan Mahasiswa</p><p class="text-xs text-green-600">{{ result.submitted_at }}</p></div>
              </div>
              <div v-if="result.approved_at" class="flex items-start gap-3 p-3 bg-green-50 rounded-lg">
                <svg class="w-5 h-5 text-green-600 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/></svg>
                <div><p class="text-sm font-medium text-gray-800">Disetujui Dosen Wali</p><p class="text-xs text-green-600">{{ result.approved_at }}</p></div>
              </div>
              <div v-if="result.signed_kaprodi" class="flex items-start gap-3 p-3 bg-green-50 rounded-lg">
                <svg class="w-5 h-5 text-green-600 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/></svg>
                <div><p class="text-sm font-medium text-gray-800">Ditandatangani Kaprodi</p><p class="text-xs text-green-600">{{ result.signed_kaprodi }}</p></div>
              </div>
            </div>
          </div>

          <!-- Document Info - RPKPS -->
          <div v-if="docType === 'rpkps'" class="space-y-3">
            <h3 class="text-sm font-semibold text-gray-700 uppercase tracking-wider">Informasi Dokumen</h3>
            <div class="space-y-3">
              <div class="flex items-start gap-3">
                <svg class="w-4 h-4 text-gray-400 mt-0.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                <div><p class="text-xs text-gray-400">Mata Kuliah</p><p class="text-sm font-medium text-gray-800">{{ result.course?.name }} ({{ result.course?.code }})</p></div>
              </div>
              <div class="flex items-start gap-3">
                <svg class="w-4 h-4 text-gray-400 mt-0.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                <div><p class="text-xs text-gray-400">Dosen Pengampu</p><p class="text-sm font-medium text-gray-800">{{ result.lecturer }}</p></div>
              </div>
              <div class="flex items-start gap-3">
                <svg class="w-4 h-4 text-gray-400 mt-0.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                <div><p class="text-xs text-gray-400">Program Studi</p><p class="text-sm font-medium text-gray-800">{{ result.course?.study_program }}</p></div>
              </div>
              <div class="flex items-start gap-3">
                <svg class="w-4 h-4 text-gray-400 mt-0.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                <div><p class="text-xs text-gray-400">Tahun Akademik</p><p class="text-sm font-medium text-gray-800">{{ result.academic_year }} · v{{ result.version }}</p></div>
              </div>
            </div>
            <div v-if="result.approved_at" class="flex items-start gap-3 p-3 bg-green-50 rounded-lg border border-green-100">
              <svg class="w-5 h-5 text-green-600 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/></svg>
              <div><p class="text-sm font-semibold text-gray-800">{{ result.approved_by }}</p><p class="text-xs text-green-600">Disetujui: {{ result.approved_at }}</p></div>
            </div>
          </div>

          <!-- Document Info - Academic Calendar -->
          <div v-if="docType === 'academic-calendar'" class="space-y-3">
            <h3 class="text-sm font-semibold text-gray-700 uppercase tracking-wider">Informasi Dokumen</h3>
            <div class="space-y-3">
              <div class="flex items-start gap-3">
                <svg class="w-4 h-4 text-gray-400 mt-0.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                <div><p class="text-xs text-gray-400">Tahun Akademik</p><p class="text-sm font-medium text-gray-800">{{ result.academic_year }}</p></div>
              </div>
              <div class="flex items-start gap-3">
                <svg class="w-4 h-4 text-gray-400 mt-0.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                <div><p class="text-xs text-gray-400">Jumlah Kegiatan</p><p class="text-sm font-medium text-gray-800">{{ result.events_count }} kegiatan</p></div>
              </div>
            </div>
            <div v-if="result.signed_by" class="flex items-start gap-3 p-3 bg-green-50 rounded-lg border border-green-100">
              <svg class="w-5 h-5 text-green-600 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/></svg>
              <div>
                <p class="text-sm font-semibold text-gray-800">{{ result.signed_by }}</p>
                <p class="text-xs text-gray-500">{{ result.position }}</p>
              </div>
            </div>
          </div>

          <!-- Penandatangan section for Surat -->
          <div v-if="docType === 'surat'" class="space-y-3 pt-3 border-t border-gray-100">
            <h3 class="text-sm font-semibold text-gray-700 uppercase tracking-wider">Penandatangan</h3>
            <div class="flex items-start gap-3 p-3 bg-green-50 rounded-lg border border-green-100">
              <svg class="w-5 h-5 text-green-600 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/></svg>
              <div>
                <p class="text-sm font-semibold text-gray-800">{{ result.signed_by }}</p>
                <p class="text-xs text-gray-500">{{ result.signer_position }}</p>
                <p v-if="result.signed_at" class="text-xs text-green-600 mt-0.5">✓ Ditandatangani: {{ result.signed_at }}</p>
              </div>
            </div>
          </div>

          <!-- Signer info (specific from query param) -->
          <div v-if="result.signer_info" class="flex items-start gap-3 p-3 bg-blue-50 rounded-lg border border-blue-100">
            <svg class="w-5 h-5 text-blue-600 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
            <div>
              <p class="text-sm font-semibold text-gray-800">{{ result.signer_info.label }}</p>
              <p v-if="result.signer_info.name" class="text-xs text-gray-600 mb-1">{{ result.signer_info.name }}</p>
              <span :class="['text-xs px-2 py-0.5 rounded-full font-medium', result.signer_info.signed ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700']">
                {{ result.signer_info.signed ? '✓ Sudah Ditandatangani' : '⏳ Belum Ditandatangani' }}
              </span>
              <p v-if="result.signer_info.signed_at" class="text-xs text-gray-500 mt-0.5">{{ result.signer_info.signed_at }}</p>
            </div>
          </div>

          <!-- Verification footer -->
          <div class="border-t border-gray-100 pt-4">
            <div class="flex items-center gap-2 text-xs text-gray-400">
              <svg class="w-4 h-4 text-blue-500 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
              <p>Diverifikasi melalui Al-Jawami Smart Campus (ASC)</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Footer text -->
      <p class="text-center text-blue-200/70 text-xs mt-4">
        Al-Jawami Smart Campus — STAI YAPATA AL-JAWAMI BANDUNG
      </p>
    </div>
  </div>
</template>
