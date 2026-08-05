<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  ArrowLeftIcon, DocumentTextIcon, LinkIcon, PhotoIcon,
  AcademicCapIcon, ClockIcon, CurrencyDollarIcon,
} from '@heroicons/vue/24/outline'
import api from '@/services/api'

const route   = useRoute()
const router  = useRouter()
const loading = ref(true)
const data    = ref<any>(null)

onMounted(async () => {
  try {
    const res = await api.get(`/students/${route.params.id}`)
    data.value = res.data
  } finally { loading.value = false }
})

function formatDate(d: string) {
  return d ? new Date(d).toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' }) : '-'
}

const statusColor: Record<string, string> = {
  Aktif: 'bg-green-100 text-green-700', Cuti: 'bg-yellow-100 text-yellow-700',
  Lulus: 'bg-blue-100 text-blue-700', DO: 'bg-red-100 text-red-700',
  'Mengundurkan Diri': 'bg-gray-100 text-gray-600', Nonaktif: 'bg-gray-100 text-gray-600',
}

const photoUrl = computed(() => {
  const path = data.value?.profile?.photo_path || data.value?.pmb_registrant?.photo_path
  if (!path) return null
  const base = (import.meta.env.VITE_API_URL ?? 'http://localhost:8000/api').replace(/\/api\/?$/, '')
  return `${base}/storage/${path}`
})

const pmb = computed(() => data.value?.pmb_registrant)
</script>

<template>
  <div v-if="loading" class="flex items-center justify-center h-64">
    <p class="text-gray-400">Memuat data...</p>
  </div>

  <div v-else-if="data" class="max-w-4xl mx-auto space-y-6">
    <!-- Header -->
    <div class="flex items-center gap-3">
      <button class="p-2 rounded-lg hover:bg-gray-100" @click="router.back()">
        <ArrowLeftIcon class="w-5 h-5 text-gray-500" />
      </button>
      <div class="flex items-center gap-3 flex-1">
        <div class="w-14 h-14 rounded-xl overflow-hidden bg-gray-100 border border-gray-200 shrink-0 flex items-center justify-center">
          <img v-if="photoUrl" :src="photoUrl" class="w-full h-full object-cover" />
          <span v-else class="text-xl font-bold text-gray-300">{{ data.name?.charAt(0) }}</span>
        </div>
        <div>
          <h1 class="text-xl font-bold text-gray-900">{{ data.name }}</h1>
          <p class="text-sm text-gray-500 font-mono">{{ data.nim }}</p>
        </div>
      </div>
      <span :class="['px-3 py-1 rounded-full text-sm font-medium', statusColor[data.status] ?? 'bg-gray-100 text-gray-600']">
        {{ data.status }}
      </span>
    </div>

    <!-- Informasi Akademik -->
    <div class="bg-white rounded-xl border border-gray-200 p-5 space-y-3">
      <h2 class="text-sm font-semibold text-gray-800 uppercase tracking-wide flex items-center gap-2">
        <AcademicCapIcon class="w-4 h-4" /> Informasi Akademik
      </h2>
      <div class="grid grid-cols-2 md:grid-cols-3 gap-4 text-sm">
        <div><span class="text-gray-400 text-xs">Program Studi</span><p class="text-gray-800 font-medium">{{ data.study_program?.name ?? '-' }}</p><p class="text-xs text-gray-500">{{ data.study_program?.faculty?.name ?? '' }}</p></div>
        <div><span class="text-gray-400 text-xs">Semester Aktif</span><p class="text-gray-800 font-medium text-lg">{{ data.current_semester }}</p></div>
        <div><span class="text-gray-400 text-xs">Tahun Masuk</span><p class="text-gray-800 font-medium">{{ data.entry_year ?? '-' }}</p></div>
        <div><span class="text-gray-400 text-xs">Dosen Wali</span><p class="text-gray-800">{{ data.advisor?.full_name ?? data.advisor?.name ?? 'Belum ditentukan' }}</p></div>
        <div><span class="text-gray-400 text-xs">Tahun Akademik Masuk</span><p class="text-gray-800">{{ data.academic_year?.name ?? '-' }}</p></div>
        <div v-if="pmb"><span class="text-gray-400 text-xs">No. Pendaftaran PMB</span><p class="text-gray-800 font-mono text-xs">{{ pmb.registration_number }}</p></div>
      </div>
    </div>

    <!-- Data Pribadi -->
    <div class="bg-white rounded-xl border border-gray-200 p-5 space-y-3">
      <h2 class="text-sm font-semibold text-gray-800 uppercase tracking-wide">Data Pribadi</h2>
      <div class="grid grid-cols-2 md:grid-cols-3 gap-4 text-sm">
        <div><span class="text-gray-400 text-xs">Jenis Kelamin</span><p class="text-gray-800">{{ data.gender === 'L' ? 'Laki-laki' : (data.gender === 'P' ? 'Perempuan' : '-') }}</p></div>
        <div><span class="text-gray-400 text-xs">Tempat, Tgl Lahir</span><p class="text-gray-800">{{ data.birth_place ?? '-' }}, {{ formatDate(data.birth_date) }}</p></div>
        <div><span class="text-gray-400 text-xs">Agama</span><p class="text-gray-800">{{ data.profile?.religion ?? '-' }}</p></div>
        <div><span class="text-gray-400 text-xs">NIK</span><p class="text-gray-800 font-mono text-xs">{{ data.profile?.nik ?? '-' }}</p></div>
        <div><span class="text-gray-400 text-xs">NISN</span><p class="text-gray-800 font-mono text-xs">{{ data.profile?.nisn ?? '-' }}</p></div>
        <div><span class="text-gray-400 text-xs">Kewarganegaraan</span><p class="text-gray-800">{{ data.profile?.nationality ?? 'Indonesia' }}</p></div>
        <div><span class="text-gray-400 text-xs">Email</span><p class="text-gray-800">{{ data.email ?? '-' }}</p></div>
        <div><span class="text-gray-400 text-xs">No. HP</span><p class="text-gray-800">{{ data.phone ?? '-' }}</p></div>
        <div><span class="text-gray-400 text-xs">Golongan Darah</span><p class="text-gray-800">{{ data.profile?.blood_type ?? '-' }}</p></div>
      </div>
    </div>

    <!-- Alamat -->
    <div v-if="data.addresses?.length" class="bg-white rounded-xl border border-gray-200 p-5 space-y-3">
      <h2 class="text-sm font-semibold text-gray-800 uppercase tracking-wide">Alamat</h2>
      <div v-for="addr in data.addresses" :key="addr.id" class="text-sm border-b border-gray-50 pb-3 last:border-0 last:pb-0">
        <span class="inline-flex px-2 py-0.5 rounded text-xs font-medium bg-blue-50 text-blue-700 mb-1">{{ addr.type }}</span>
        <p class="text-gray-800">{{ [addr.address, addr.village, addr.district, addr.city, addr.province, addr.postal_code].filter(Boolean).join(', ') || '-' }}</p>
      </div>
    </div>

    <!-- Orang Tua / Wali -->
    <div v-if="data.parents?.length" class="bg-white rounded-xl border border-gray-200 p-5 space-y-3">
      <h2 class="text-sm font-semibold text-gray-800 uppercase tracking-wide">Data Orang Tua / Wali</h2>
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div v-for="parent in data.parents" :key="parent.id" class="text-sm space-y-1">
          <p class="text-xs font-semibold text-gray-400 uppercase">{{ parent.relation }}</p>
          <p class="text-gray-800 font-medium">{{ parent.name }}</p>
          <p class="text-gray-500 text-xs">{{ parent.occupation ?? '-' }}</p>
          <p class="text-gray-500 text-xs">{{ parent.phone ?? '-' }}</p>
          <p v-if="parent.education" class="text-gray-500 text-xs">Pendidikan: {{ parent.education }}</p>
          <p v-if="parent.income" class="text-gray-500 text-xs">Penghasilan: {{ parent.income }}</p>
        </div>
      </div>
    </div>

    <!-- Riwayat Pendidikan -->
    <div v-if="data.education_histories?.length" class="bg-white rounded-xl border border-gray-200 p-5 space-y-3">
      <h2 class="text-sm font-semibold text-gray-800 uppercase tracking-wide">Riwayat Pendidikan</h2>
      <div v-for="edu in data.education_histories" :key="edu.id" class="flex items-start gap-3 text-sm border-b border-gray-50 pb-3 last:border-0 last:pb-0">
        <span class="inline-flex px-2 py-0.5 rounded text-xs font-medium bg-indigo-50 text-indigo-700 shrink-0">{{ edu.level }}</span>
        <div>
          <p class="text-gray-800 font-medium">{{ edu.institution_name }}</p>
          <p class="text-gray-500 text-xs">{{ edu.institution_address ?? '' }}</p>
          <p class="text-gray-500 text-xs">
            {{ edu.major ? `Jurusan: ${edu.major} · ` : '' }}
            Lulus: {{ edu.graduation_year ?? '-' }}
            {{ edu.diploma_number ? ` · No. Ijazah: ${edu.diploma_number}` : '' }}
          </p>
        </div>
      </div>
    </div>

    <!-- History Status -->
    <div v-if="data.status_histories?.length" class="bg-white rounded-xl border border-gray-200 p-5 space-y-3">
      <h2 class="text-sm font-semibold text-gray-800 uppercase tracking-wide flex items-center gap-2">
        <ClockIcon class="w-4 h-4" /> Riwayat Status
      </h2>
      <table class="w-full text-sm">
        <thead>
          <tr class="text-left text-xs text-gray-400 border-b">
            <th class="pb-2">Status</th>
            <th class="pb-2">Semester</th>
            <th class="pb-2">Mulai</th>
            <th class="pb-2">Selesai</th>
            <th class="pb-2">Keterangan</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="sh in data.status_histories" :key="sh.id" class="border-b border-gray-50">
            <td class="py-2">
              <span :class="['inline-flex px-2 py-0.5 rounded-full text-xs font-medium', statusColor[sh.status] ?? 'bg-gray-100 text-gray-600']">{{ sh.status }}</span>
            </td>
            <td class="py-2 text-gray-600 text-xs">{{ sh.semester?.name ?? '-' }}</td>
            <td class="py-2 text-gray-600 text-xs">{{ formatDate(sh.start_date) }}</td>
            <td class="py-2 text-gray-600 text-xs">{{ sh.end_date ? formatDate(sh.end_date) : 'Sekarang' }}</td>
            <td class="py-2 text-gray-500 text-xs">{{ sh.reason ?? '-' }}</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Dokumen -->
    <div v-if="data.documents?.length" class="bg-white rounded-xl border border-gray-200 p-5 space-y-3">
      <h2 class="text-sm font-semibold text-gray-800 uppercase tracking-wide flex items-center gap-2">
        <DocumentTextIcon class="w-4 h-4" /> Dokumen
      </h2>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
        <div v-for="doc in data.documents" :key="doc.id" class="flex items-center gap-2 p-3 bg-gray-50 rounded-lg">
          <PhotoIcon v-if="doc.type === 'FOTO'" class="w-5 h-5 text-gray-400 shrink-0" />
          <LinkIcon v-else class="w-5 h-5 text-gray-400 shrink-0" />
          <div class="min-w-0 flex-1">
            <p class="text-xs text-gray-700 font-medium">{{ doc.name }}</p>
            <p class="text-xs text-gray-400">{{ doc.document_number ?? doc.type }}</p>
          </div>
          <span v-if="doc.is_verified" class="text-green-600 text-xs font-medium">✓</span>
          <a v-if="doc.file_url" :href="doc.file_url" target="_blank" class="text-blue-600 text-xs underline shrink-0">Buka</a>
          <a v-else-if="doc.file_path" :href="`${('' as any).replace?.('', '') || ''}` " target="_blank" class="text-blue-600 text-xs underline shrink-0">Lihat</a>
        </div>
      </div>
    </div>

    <!-- Keuangan -->
    <div v-if="data.financial_records?.length" class="bg-white rounded-xl border border-gray-200 p-5 space-y-3">
      <h2 class="text-sm font-semibold text-gray-800 uppercase tracking-wide flex items-center gap-2">
        <CurrencyDollarIcon class="w-4 h-4" /> Keuangan
      </h2>
      <table class="w-full text-sm">
        <thead><tr class="text-left text-xs text-gray-400 border-b"><th class="pb-2">Jenis</th><th class="pb-2">Semester</th><th class="pb-2 text-right">Tagihan</th><th class="pb-2 text-right">Dibayar</th><th class="pb-2">Status</th></tr></thead>
        <tbody>
          <tr v-for="fr in data.financial_records" :key="fr.id" class="border-b border-gray-50">
            <td class="py-2 text-gray-700">{{ fr.type }}</td>
            <td class="py-2 text-gray-500 text-xs">{{ fr.semester?.name ?? '-' }}</td>
            <td class="py-2 text-gray-700 text-right font-mono text-xs">{{ Number(fr.amount).toLocaleString('id-ID') }}</td>
            <td class="py-2 text-gray-700 text-right font-mono text-xs">{{ Number(fr.paid_amount).toLocaleString('id-ID') }}</td>
            <td class="py-2">
              <span :class="['text-xs font-medium', fr.status === 'LUNAS' ? 'text-green-600' : fr.status === 'BELUM_BAYAR' ? 'text-red-500' : 'text-yellow-600']">
                {{ fr.status.replace('_', ' ') }}
              </span>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Akun Sistem -->
    <div v-if="data.user" class="bg-white rounded-xl border border-gray-200 p-5 space-y-3">
      <h2 class="text-sm font-semibold text-gray-800 uppercase tracking-wide">Akun Sistem</h2>
      <div class="grid grid-cols-2 md:grid-cols-3 gap-4 text-sm">
        <div><span class="text-gray-400 text-xs">Username</span><p class="text-gray-800 font-mono text-xs">{{ data.user.username ?? '-' }}</p></div>
        <div><span class="text-gray-400 text-xs">Email Akun</span><p class="text-gray-800">{{ data.user.email }}</p></div>
        <div><span class="text-gray-400 text-xs">Status Akun</span><p :class="data.user.is_active ? 'text-green-600 font-medium' : 'text-red-500'">{{ data.user.is_active ? 'Aktif' : 'Nonaktif' }}</p></div>
      </div>
    </div>
  </div>
</template>
