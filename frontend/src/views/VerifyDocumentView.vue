<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import axios from 'axios'

const route = useRoute()

const loading  = ref(true)
const result   = ref<any>(null)
const isValid  = ref(false)
const errorMsg = ref('')

// Deteksi jenis dokumen dari path: /verify/krs/:id atau /verify/rpkps/:code
const docType = route.params.type as string   // 'krs' atau 'rpkps'
const docId   = route.params.id as string     // id KRS atau verification_code RPKPS
const signer  = (route.query.signer as string) || ''

const BASE_URL = import.meta.env.VITE_API_URL ?? 'http://localhost:8000/api'

onMounted(async () => {
  try {
    const url = `${BASE_URL}/verify/${docType}/${docId}`
    const { data } = await axios.get(url, {
      params: signer ? { signer } : {},
    })
    result.value = data
    isValid.value = data.valid === true
  } catch (e: any) {
    const msg = e?.response?.data?.message
    errorMsg.value = msg ?? 'Terjadi kesalahan saat memverifikasi dokumen.'
    isValid.value = false
  } finally {
    loading.value = false }
})

function statusColor(status: string): string {
  const map: Record<string, string> = {
    DRAFT: 'bg-gray-100 text-gray-600',
    DIAJUKAN: 'bg-blue-100 text-blue-700',
    APPROVED: 'bg-green-100 text-green-700',
    DISETUJUI: 'bg-green-100 text-green-700',
    DIKUNCI: 'bg-emerald-100 text-emerald-700',
    SUBMITTED: 'bg-blue-100 text-blue-700',
    REVISI: 'bg-yellow-100 text-yellow-700',
    DITOLAK: 'bg-red-100 text-red-700',
    REJECTED: 'bg-red-100 text-red-700',
  }
  return map[status] ?? 'bg-gray-100 text-gray-600'
}

function docTypeLabel(type: string): string {
  return type === 'krs' ? 'KRS' : 'RPS/RPKPS'
}
</script>

<template>
  <div class="min-h-screen bg-gray-50 flex items-start justify-center pt-10 px-4">
    <div class="w-full max-w-lg">
      <!-- Header -->
      <div class="text-center mb-6">
        <div class="inline-flex items-center justify-center w-14 h-14 rounded-full mb-3"
          :class="loading ? 'bg-gray-100' : isValid ? 'bg-green-100' : 'bg-red-100'">
          <!-- Loading spinner -->
          <svg v-if="loading" class="w-7 h-7 text-gray-400 animate-spin" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/>
          </svg>
          <!-- Valid -->
          <svg v-else-if="isValid" class="w-7 h-7 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/>
          </svg>
          <!-- Invalid -->
          <svg v-else class="w-7 h-7 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
          </svg>
        </div>

        <h1 class="text-xl font-bold text-gray-900">Verifikasi Dokumen</h1>
        <p v-if="!loading" class="text-sm mt-1"
          :class="isValid ? 'text-green-600' : 'text-red-600'">
          {{ isValid ? '✓ Dokumen Terverifikasi' : '✗ Dokumen Tidak Valid' }}
        </p>
        <p v-else class="text-sm text-gray-400 mt-1">Sedang memverifikasi dokumen...</p>
      </div>

      <!-- Error -->
      <div v-if="!loading && !isValid" class="bg-red-50 border border-red-200 rounded-xl p-5 text-center">
        <p class="text-red-700 font-medium">{{ errorMsg || result?.message || 'Dokumen tidak ditemukan.' }}</p>
        <p class="text-red-500 text-sm mt-1">QR Code mungkin tidak valid atau dokumen telah dihapus.</p>
      </div>

      <!-- KRS Result -->
      <div v-if="!loading && isValid && docType === 'krs'" class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        <!-- Doc header -->
        <div class="bg-green-600 px-5 py-4 text-white">
          <p class="text-xs font-medium opacity-80 uppercase tracking-wide">{{ result.document }}</p>
          <p class="text-lg font-bold mt-0.5">{{ result.student?.name }}</p>
          <p class="text-sm opacity-90">{{ result.student?.nim }} · {{ result.student?.study_program }}</p>
        </div>
        <div class="p-5 space-y-4">
          <!-- Status -->
          <div class="flex items-center justify-between">
            <span class="text-xs text-gray-400">Status KRS</span>
            <span :class="['text-xs px-2.5 py-1 rounded-full font-semibold', statusColor(result.status)]">{{ result.status }}</span>
          </div>
          <!-- Info grid -->
          <div class="grid grid-cols-2 gap-3 text-sm">
            <div class="bg-gray-50 rounded-lg p-3">
              <p class="text-xs text-gray-400">Semester</p>
              <p class="font-medium text-gray-800">{{ result.semester }}</p>
            </div>
            <div class="bg-gray-50 rounded-lg p-3">
              <p class="text-xs text-gray-400">Total SKS</p>
              <p class="font-medium text-gray-800">{{ result.total_credits }} SKS</p>
            </div>
            <div class="bg-gray-50 rounded-lg p-3">
              <p class="text-xs text-gray-400">Jumlah MK</p>
              <p class="font-medium text-gray-800">{{ result.courses_count }} Mata Kuliah</p>
            </div>
            <div class="bg-gray-50 rounded-lg p-3">
              <p class="text-xs text-gray-400">Status Tanda Tangan</p>
              <p class="font-medium" :class="result.is_fully_signed ? 'text-green-600' : 'text-yellow-600'">
                {{ result.is_fully_signed ? '✓ Lengkap' : '⏳ Belum Lengkap' }}
              </p>
            </div>
          </div>
          <!-- Timeline tanda tangan -->
          <div class="border-t border-gray-100 pt-3 space-y-2">
            <p class="text-xs font-semibold text-gray-500 uppercase">Riwayat Tanda Tangan</p>
            <div class="space-y-2">
              <div class="flex items-center gap-3 text-sm">
                <div :class="['w-6 h-6 rounded-full flex items-center justify-center text-xs shrink-0', result.submitted_at ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-400']">✓</div>
                <div>
                  <p class="font-medium text-gray-700">Diajukan Mahasiswa</p>
                  <p class="text-xs text-gray-400">{{ result.submitted_at ?? 'Belum' }}</p>
                </div>
              </div>
              <div class="flex items-center gap-3 text-sm">
                <div :class="['w-6 h-6 rounded-full flex items-center justify-center text-xs shrink-0', result.approved_at ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-400']">✓</div>
                <div>
                  <p class="font-medium text-gray-700">Disetujui Dosen Wali</p>
                  <p class="text-xs text-gray-400">{{ result.approved_at ?? 'Belum' }}</p>
                </div>
              </div>
              <div class="flex items-center gap-3 text-sm">
                <div :class="['w-6 h-6 rounded-full flex items-center justify-center text-xs shrink-0', result.signed_kaprodi ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-400']">✓</div>
                <div>
                  <p class="font-medium text-gray-700">Ditandatangani Kaprodi</p>
                  <p class="text-xs text-gray-400">{{ result.signed_kaprodi ?? 'Belum' }}</p>
                </div>
              </div>
            </div>
          </div>
          <!-- Info tanda tangan spesifik (dari signer param) -->
          <div v-if="result.signer_info" class="bg-blue-50 border border-blue-200 rounded-lg p-3">
            <p class="text-xs font-semibold text-blue-600 mb-1">Tanda Tangan: {{ result.signer_info.label }}</p>
            <div class="flex items-center gap-2">
              <span :class="['text-xs px-2 py-0.5 rounded-full font-medium', result.signer_info.signed ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700']">
                {{ result.signer_info.signed ? '✓ Sudah Ditandatangani' : '⏳ Belum Ditandatangani' }}
              </span>
              <span v-if="result.signer_info.signed_at" class="text-xs text-gray-500">{{ result.signer_info.signed_at }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- RPKPS Result -->
      <div v-if="!loading && isValid && docType === 'rpkps'" class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        <div :class="['px-5 py-4 text-white', result.is_valid ? 'bg-emerald-600' : 'bg-yellow-600']">
          <p class="text-xs font-medium opacity-80 uppercase tracking-wide">{{ result.document }}</p>
          <p class="text-lg font-bold mt-0.5">{{ result.course?.name }}</p>
          <p class="text-sm opacity-90">{{ result.course?.code }} · {{ result.course?.credits }} SKS</p>
        </div>
        <div class="p-5 space-y-4">
          <div class="flex items-center justify-between">
            <span class="text-xs text-gray-400">Status RPS</span>
            <span :class="['text-xs px-2.5 py-1 rounded-full font-semibold', statusColor(result.status)]">{{ result.status }}</span>
          </div>
          <div class="grid grid-cols-2 gap-3 text-sm">
            <div class="bg-gray-50 rounded-lg p-3">
              <p class="text-xs text-gray-400">Program Studi</p>
              <p class="font-medium text-gray-800 text-xs">{{ result.course?.study_program }}</p>
            </div>
            <div class="bg-gray-50 rounded-lg p-3">
              <p class="text-xs text-gray-400">Dosen Pengampu</p>
              <p class="font-medium text-gray-800 text-xs">{{ result.lecturer }}</p>
            </div>
            <div class="bg-gray-50 rounded-lg p-3">
              <p class="text-xs text-gray-400">Tahun Akademik</p>
              <p class="font-medium text-gray-800">{{ result.academic_year }}</p>
            </div>
            <div class="bg-gray-50 rounded-lg p-3">
              <p class="text-xs text-gray-400">Versi</p>
              <p class="font-medium text-gray-800">v{{ result.version }}</p>
            </div>
          </div>
          <div v-if="result.approved_at" class="border-t border-gray-100 pt-3 text-sm">
            <p class="text-xs font-semibold text-gray-500 uppercase mb-1">Disetujui</p>
            <p class="text-gray-700">{{ result.approved_by ?? '-' }}</p>
            <p class="text-xs text-gray-400">{{ result.approved_at }}</p>
          </div>
          <div v-if="result.signer_info" class="bg-blue-50 border border-blue-200 rounded-lg p-3">
            <p class="text-xs font-semibold text-blue-600 mb-1">Tanda Tangan: {{ result.signer_info.label }}</p>
            <div class="flex items-center gap-2">
              <span v-if="result.signer_info.name" class="text-sm font-medium text-gray-800">{{ result.signer_info.name }}</span>
              <span :class="['text-xs px-2 py-0.5 rounded-full font-medium', result.signer_info.signed ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700']">
                {{ result.signer_info.signed ? '✓ Valid' : '⏳ Belum Ditandatangani' }}
              </span>
            </div>
          </div>
          <div :class="['rounded-lg p-3 text-sm text-center font-medium', result.is_valid ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-yellow-50 text-yellow-700 border border-yellow-200']">
            {{ result.is_valid ? '✓ Dokumen ini sah dan telah diverifikasi' : '⚠ Dokumen belum final (masih dalam proses)' }}
          </div>
        </div>
      </div>

      <!-- Footer -->
      <div class="mt-6 text-center text-xs text-gray-400">
        <p>Halaman ini dapat diakses oleh siapapun untuk verifikasi keaslian dokumen.</p>
        <p class="mt-1">Sistem Informasi Akademik Terpadu</p>
      </div>
    </div>
  </div>
</template>
