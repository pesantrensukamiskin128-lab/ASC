<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ArrowLeftIcon, ArrowDownTrayIcon, LockClosedIcon, BookOpenIcon } from '@heroicons/vue/24/outline'
import { useToast } from 'vue-toastification'
import { useAuthStore } from '@/stores/auth'
import api from '@/services/api'

const route  = useRoute()
const router = useRouter()
const toast  = useToast()
const auth   = useAuthStore()

const item    = ref<any>(null)
const loading = ref(false)

// source: penelitian | skripsi | karya_dosen
const source = computed(() => route.params.source as string)
const id     = computed(() => route.params.id as string)

const sourceLabel: Record<string, string> = {
  penelitian: 'Penelitian & Pengabdian',
  skripsi:    'Skripsi',
  karya_dosen:'Karya Dosen',
}

const sourceBg: Record<string, string> = {
  penelitian:  'from-blue-600 to-blue-500',
  skripsi:     'from-green-600 to-green-500',
  karya_dosen: 'from-purple-600 to-purple-500',
}

onMounted(load)

async function load() {
  loading.value = true
  try {
    const endpointMap: Record<string, string> = {
      penelitian:  `/repository/penelitian/${id.value}`,
      skripsi:     `/repository/skripsi/${id.value}`,
      karya_dosen: `/repository/karya-dosen/${id.value}`,
    }
    const endpoint = endpointMap[source.value]
    if (!endpoint) { router.push('/repository'); return }

    const { data } = await api.get(endpoint)
    item.value = data
  } catch {
    toast.error('Karya tidak ditemukan.')
    router.push('/repository')
  } finally { loading.value = false }
}

async function downloadFile(fileType: string, label: string) {
  if (!auth.isAuthenticated) {
    toast.warning('Silakan login terlebih dahulu untuk mengunduh file.')
    router.push('/login')
    return
  }

  try {
    const url = `${import.meta.env.VITE_API_URL ?? 'http://localhost:8000/api'}/repository/download/${source.value}/${id.value}/${fileType}`
    const resp = await api.get(`/repository/download/${source.value}/${id.value}/${fileType}`, {
      responseType: 'blob',
    })

    const blob    = new Blob([resp.data])
    const link    = document.createElement('a')
    link.href     = URL.createObjectURL(blob)
    link.download = `${label}-${id.value}.pdf`
    link.click()
    URL.revokeObjectURL(link.href)
    toast.success(`${label} berhasil diunduh.`)
  } catch {
    toast.error('Gagal mengunduh file.')
  }
}

function formatDate(d: string | null) {
  if (!d) return '-'
  return new Date(d).toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' })
}
</script>

<template>
  <div class="space-y-6 max-w-4xl mx-auto">
    <!-- Back -->
    <button class="flex items-center gap-2 text-sm text-gray-500 hover:text-gray-800 transition-colors" @click="router.back()">
      <ArrowLeftIcon class="w-4 h-4" />
      Kembali ke Repository
    </button>

    <div v-if="loading" class="text-center py-16 text-gray-400">
      <svg class="animate-spin w-8 h-8 mx-auto mb-3" fill="none" viewBox="0 0 24 24">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z" />
      </svg>
      Memuat...
    </div>

    <template v-else-if="item">
      <!-- Header Card -->
      <div :class="['rounded-2xl p-6 text-white bg-gradient-to-r', sourceBg[source] ?? 'from-blue-600 to-blue-500']">
        <div class="flex items-start gap-5">
          <!-- Cover -->
          <div class="w-24 h-32 rounded-xl overflow-hidden bg-white/20 shrink-0 flex items-center justify-center">
            <img v-if="item.cover_url" :src="item.cover_url" :alt="item.title" class="w-full h-full object-cover" />
            <BookOpenIcon v-else class="w-10 h-10 text-white/60" />
          </div>

          <div class="flex-1 min-w-0">
            <div class="flex flex-wrap gap-2 mb-2">
              <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-medium bg-white/25">
                {{ sourceLabel[source] }}
              </span>
              <span v-if="item.type_label" class="inline-flex px-2.5 py-1 rounded-full text-xs font-medium bg-white/25">
                {{ item.type_label }}
              </span>
              <span v-if="item.year" class="inline-flex px-2.5 py-1 rounded-full text-xs font-medium bg-white/25">
                {{ item.year }}
              </span>
            </div>

            <h1 class="text-xl font-bold leading-snug mb-2">{{ item.title }}</h1>

            <!-- Author info -->
            <div class="space-y-0.5 text-sm text-white/80">
              <p v-if="item.ketua">
                <span class="font-medium text-white">Ketua:</span> {{ item.ketua }}
              </p>
              <p v-else-if="item.author">
                <span class="font-medium text-white">Penulis:</span> {{ item.author }}
                <span v-if="item.nim" class="text-white/60"> ({{ item.nim }})</span>
              </p>
              <p v-if="item.study_program">
                <span class="font-medium text-white">Program Studi:</span> {{ item.study_program }}
              </p>
              <p v-if="item.period">
                <span class="font-medium text-white">Periode:</span> {{ item.period }}
              </p>
              <p>
                <span class="font-medium text-white">Dipublikasikan:</span> {{ formatDate(item.published_at) }}
              </p>
            </div>
          </div>
        </div>
      </div>

      <!-- Content Grid -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Main Info -->
        <div class="lg:col-span-2 space-y-5">
          <!-- Abstrak / Deskripsi -->
          <div class="bg-white rounded-xl border border-gray-200 p-5">
            <h2 class="font-semibold text-gray-900 mb-3">
              {{ source === 'karya_dosen' ? 'Deskripsi' : 'Abstrak' }}
            </h2>
            <p class="text-sm text-gray-700 leading-relaxed whitespace-pre-line">
              {{ item.abstract || item.description || 'Tidak tersedia.' }}
            </p>
          </div>

          <!-- Tim Anggota (Penelitian) -->
          <div v-if="item.members?.length" class="bg-white rounded-xl border border-gray-200 p-5">
            <h2 class="font-semibold text-gray-900 mb-3">Tim Peneliti</h2>
            <div class="space-y-2">
              <div v-for="m in item.members" :key="m.name" class="flex items-center gap-2 text-sm">
                <span class="w-2 h-2 rounded-full bg-blue-400 shrink-0" />
                <span class="text-gray-700">{{ m.name }}</span>
                <span class="text-xs text-gray-400 capitalize">({{ m.type }})</span>
              </div>
            </div>
          </div>

          <!-- Pembimbing (Skripsi) -->
          <div v-if="item.supervisors?.length" class="bg-white rounded-xl border border-gray-200 p-5">
            <h2 class="font-semibold text-gray-900 mb-3">Pembimbing</h2>
            <div class="space-y-2">
              <div v-for="s in item.supervisors" :key="s.name" class="flex items-center gap-2 text-sm">
                <span class="w-2 h-2 rounded-full bg-green-400 shrink-0" />
                <span class="text-gray-700">{{ s.name }}</span>
                <span class="text-xs text-gray-400">({{ s.role === 'pembimbing_1' ? 'Pembimbing 1' : 'Pembimbing 2' }})</span>
              </div>
            </div>
          </div>

          <!-- Kata Kunci & Daftar Pustaka -->
          <div v-if="item.keywords || item.bibliography" class="bg-white rounded-xl border border-gray-200 p-5 space-y-4">
            <div v-if="item.keywords">
              <h2 class="font-semibold text-gray-900 mb-2">Kata Kunci</h2>
              <div class="flex flex-wrap gap-2">
                <span
                  v-for="kw in item.keywords.split(',').map((k: string) => k.trim()).filter(Boolean)"
                  :key="kw"
                  class="inline-flex px-2.5 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-700"
                >
                  {{ kw }}
                </span>
              </div>
            </div>
            <div v-if="item.bibliography">
              <h2 class="font-semibold text-gray-900 mb-2">Daftar Pustaka</h2>
              <p class="text-sm text-gray-600 whitespace-pre-line leading-relaxed">{{ item.bibliography }}</p>
            </div>
          </div>
        </div>

        <!-- Sidebar: Info & Download -->
        <div class="space-y-5">
          <!-- Info Detail -->
          <div class="bg-white rounded-xl border border-gray-200 p-5 space-y-3">
            <h2 class="font-semibold text-gray-900">Informasi</h2>
            <div v-for="info in [
              { label: 'Publisher', value: item.publisher },
              { label: 'ISBN/ISSN', value: item.isbn_issn },
              { label: 'No. HKI', value: item.hki_number },
              { label: 'Tgl Terbit', value: item.published_date ? formatDate(item.published_date) : null },
              { label: 'Repository URL', value: item.repository_url, isLink: true },
            ].filter(i => i.value)" :key="info.label" class="text-sm">
              <p class="text-xs text-gray-400 font-medium">{{ info.label }}</p>
              <a v-if="info.isLink" :href="info.value" target="_blank" rel="noopener noreferrer"
                class="text-blue-600 hover:underline text-sm break-all">
                {{ info.value }}
              </a>
              <p v-else class="text-gray-700">{{ info.value }}</p>
            </div>
          </div>

          <!-- Download -->
          <div class="bg-white rounded-xl border border-gray-200 p-5">
            <h2 class="font-semibold text-gray-900 mb-3">File Tersedia</h2>

            <!-- Not logged in notice -->
            <div v-if="!auth.isAuthenticated" class="flex items-start gap-2 p-3 bg-amber-50 border border-amber-200 rounded-lg mb-3">
              <LockClosedIcon class="w-4 h-4 text-amber-600 shrink-0 mt-0.5" />
              <p class="text-xs text-amber-700">Login diperlukan untuk mengunduh file.</p>
            </div>

            <div class="space-y-2">
              <!-- Penelitian files -->
              <template v-if="source === 'penelitian'">
                <button v-if="item.has_laporan_final"
                  class="w-full flex items-center gap-2 px-4 py-2.5 rounded-lg border transition-colors text-sm font-medium"
                  :class="auth.isAuthenticated ? 'border-blue-600 text-blue-700 hover:bg-blue-50' : 'border-gray-200 text-gray-400 cursor-not-allowed'"
                  @click="downloadFile('laporan_final', 'Laporan Final')">
                  <ArrowDownTrayIcon class="w-4 h-4 shrink-0" />
                  Download Laporan Final
                </button>
                <button v-if="item.has_paper_final"
                  class="w-full flex items-center gap-2 px-4 py-2.5 rounded-lg border transition-colors text-sm font-medium"
                  :class="auth.isAuthenticated ? 'border-blue-600 text-blue-700 hover:bg-blue-50' : 'border-gray-200 text-gray-400 cursor-not-allowed'"
                  @click="downloadFile('paper_final', 'Paper Final')">
                  <ArrowDownTrayIcon class="w-4 h-4 shrink-0" />
                  Download Paper Final
                </button>
              </template>

              <!-- Skripsi files -->
              <template v-else-if="source === 'skripsi'">
                <button v-if="item.has_final_pdf"
                  class="w-full flex items-center gap-2 px-4 py-2.5 rounded-lg border transition-colors text-sm font-medium"
                  :class="auth.isAuthenticated ? 'border-green-600 text-green-700 hover:bg-green-50' : 'border-gray-200 text-gray-400 cursor-not-allowed'"
                  @click="downloadFile('skripsi_final', 'Skripsi Final')">
                  <ArrowDownTrayIcon class="w-4 h-4 shrink-0" />
                  Download Skripsi
                </button>
              </template>

              <!-- Karya Dosen files -->
              <template v-else-if="source === 'karya_dosen'">
                <button v-if="item.has_main_file"
                  class="w-full flex items-center gap-2 px-4 py-2.5 rounded-lg border transition-colors text-sm font-medium"
                  :class="auth.isAuthenticated ? 'border-purple-600 text-purple-700 hover:bg-purple-50' : 'border-gray-200 text-gray-400 cursor-not-allowed'"
                  @click="downloadFile('main_file', 'File Utama')">
                  <ArrowDownTrayIcon class="w-4 h-4 shrink-0" />
                  Download File Utama
                </button>
                <button v-if="item.has_support_file"
                  class="w-full flex items-center gap-2 px-4 py-2.5 rounded-lg border transition-colors text-sm font-medium"
                  :class="auth.isAuthenticated ? 'border-purple-600 text-purple-700 hover:bg-purple-50' : 'border-gray-200 text-gray-400 cursor-not-allowed'"
                  @click="downloadFile('support_file', 'File Pendukung')">
                  <ArrowDownTrayIcon class="w-4 h-4 shrink-0" />
                  Download File Pendukung
                </button>
              </template>

              <!-- No files -->
              <p v-if="!item.has_laporan_final && !item.has_paper_final && !item.has_final_pdf && !item.has_main_file && !item.has_support_file"
                class="text-sm text-gray-400 text-center py-2">
                Tidak ada file untuk diunduh
              </p>
            </div>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>
