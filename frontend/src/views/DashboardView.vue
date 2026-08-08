<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import {
  UsersIcon, AcademicCapIcon, BookOpenIcon, CurrencyDollarIcon,
  ClockIcon, CheckCircleIcon, ExclamationTriangleIcon,
  ArrowTrendingUpIcon, BuildingLibraryIcon, BriefcaseIcon,
  CalendarDaysIcon, DocumentCheckIcon, UserGroupIcon,
  ClipboardDocumentListIcon, ChatBubbleLeftRightIcon, ChartBarIcon,
} from '@heroicons/vue/24/outline'
import BarChart from '@/components/ui/BarChart.vue'
import DonutChart from '@/components/ui/DonutChart.vue'
import api from '@/services/api'

const router = useRouter()
const auth = useAuthStore()
const loading = ref(true)
const data = ref<any>(null)
const error = ref('')

onMounted(async () => {
  try {
    const { data: res } = await api.get('/dashboard')
    data.value = res
  } catch (e: any) {
    error.value = e?.response?.data?.message ?? 'Gagal memuat dashboard.'
  } finally { loading.value = false }
})

const dashboardRole = computed(() => data.value?.role ?? 'ADMIN')

function formatCurrency(n: number) {
  return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(n ?? 0)
}
</script>

<template>
  <div class="space-y-6">
    <!-- Welcome -->
    <div>
      <h1 class="text-2xl font-bold text-gray-900">
        Assalamu'alaikum, {{ auth.user?.name }} 👋
      </h1>
      <p class="text-sm text-gray-500 mt-1">
        {{ auth.user?.roles[0]?.replace(/_/g, ' ') }} — 
        <span v-if="data?.semester">Semester {{ data.semester }}</span>
      </p>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="flex items-center justify-center h-48">
      <p class="text-gray-400">Memuat dashboard...</p>
    </div>

    <!-- Error -->
    <div v-else-if="error" class="bg-red-50 border border-red-200 rounded-xl p-5">
      <p class="text-sm text-red-700">{{ error }}</p>
    </div>

    <!-- ============================================ -->
    <!-- DASHBOARD ADMIN / PIMPINAN -->
    <!-- ============================================ -->
    <template v-else-if="dashboardRole === 'ADMIN'">
      <!-- Stats Grid -->
      <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
        <div class="bg-white rounded-xl border border-gray-200 p-4 hover:shadow-md transition-shadow cursor-pointer" @click="router.push('/sdm/students')">
          <UsersIcon class="w-5 h-5 text-blue-500 mb-2" />
          <p class="text-2xl font-bold text-gray-900">{{ data.stats.active_students }}</p>
          <p class="text-xs text-gray-500">Mahasiswa Aktif</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-4 hover:shadow-md transition-shadow cursor-pointer" @click="router.push('/sdm/lecturers')">
          <AcademicCapIcon class="w-5 h-5 text-green-500 mb-2" />
          <p class="text-2xl font-bold text-gray-900">{{ data.stats.total_lecturers }}</p>
          <p class="text-xs text-gray-500">Dosen Aktif</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-4">
          <BuildingLibraryIcon class="w-5 h-5 text-purple-500 mb-2" />
          <p class="text-2xl font-bold text-gray-900">{{ data.stats.total_prodi }}</p>
          <p class="text-xs text-gray-500">Program Studi</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-4">
          <BookOpenIcon class="w-5 h-5 text-indigo-500 mb-2" />
          <p class="text-2xl font-bold text-gray-900">{{ data.stats.active_classes }}</p>
          <p class="text-xs text-gray-500">Kelas Aktif</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-4 hover:shadow-md transition-shadow cursor-pointer" @click="router.push('/alumni/data')">
          <UserGroupIcon class="w-5 h-5 text-teal-500 mb-2" />
          <p class="text-2xl font-bold text-gray-900">{{ data.stats.total_alumni }}</p>
          <p class="text-xs text-gray-500">Alumni</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-4 hover:shadow-md transition-shadow cursor-pointer" @click="router.push('/keuangan')">
          <CurrencyDollarIcon class="w-5 h-5 text-orange-500 mb-2" />
          <p class="text-lg font-bold text-gray-900">{{ formatCurrency(data.finance.total_revenue) }}</p>
          <p class="text-xs text-gray-500">Pendapatan</p>
        </div>
      </div>

      <!-- Keuangan & Status -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="bg-gradient-to-br from-green-50 to-green-100 rounded-xl border border-green-200 p-5">
          <p class="text-xs text-green-600 font-medium">Total Pendapatan</p>
          <p class="text-xl font-bold text-green-800 mt-1">{{ formatCurrency(data.finance.total_revenue) }}</p>
        </div>
        <div class="bg-gradient-to-br from-red-50 to-red-100 rounded-xl border border-red-200 p-5">
          <p class="text-xs text-red-600 font-medium">Tunggakan</p>
          <p class="text-xl font-bold text-red-800 mt-1">{{ formatCurrency(data.finance.total_outstanding) }}</p>
        </div>
        <div class="bg-gradient-to-br from-yellow-50 to-yellow-100 rounded-xl border border-yellow-200 p-5">
          <p class="text-xs text-yellow-600 font-medium">Pembayaran Pending</p>
          <p class="text-xl font-bold text-yellow-800 mt-1">{{ data.finance.pending_payments }}</p>
        </div>
      </div>

      <!-- Mahasiswa Per Prodi -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="bg-white rounded-xl border border-gray-200 p-5">
          <BarChart
            :data="data.students_by_prodi?.map((item: any) => ({ label: item.study_program?.code ?? '-', value: item.count })) ?? []"
            title="Mahasiswa Aktif Per Program Studi"
            :height="180"
            orientation="vertical"
          />
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-5">
          <DonutChart
            :data="data.students_by_status?.map((item: any, i: number) => ({ label: item.status, value: item.count })) ?? []"
            title="Distribusi Status Mahasiswa"
            :size="180"
          />
        </div>
      </div>

      <!-- Mahasiswa Per Angkatan -->
      <div v-if="data.students_by_entry_year?.length" class="bg-white rounded-xl border border-gray-200 p-5">
        <BarChart
          :data="[...data.students_by_entry_year].reverse().map((item: any) => ({ label: String(item.entry_year), value: item.count }))"
          title="Mahasiswa Aktif Per Angkatan"
          :height="160"
          orientation="vertical"
        />
      </div>
    </template>

    <!-- ============================================ -->
    <!-- DASHBOARD DOSEN -->
    <!-- ============================================ -->
    <template v-else-if="dashboardRole === 'DOSEN'">
      <!-- Stats -->
      <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-white rounded-xl border border-gray-200 p-5 hover:shadow-md transition-shadow cursor-pointer" @click="router.push('/akademik/kelas')">
          <BookOpenIcon class="w-5 h-5 text-blue-500 mb-2" />
          <p class="text-2xl font-bold text-gray-900">{{ data.stats.my_classes }}</p>
          <p class="text-xs text-gray-500">Kelas Saya</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-5">
          <UsersIcon class="w-5 h-5 text-green-500 mb-2" />
          <p class="text-2xl font-bold text-gray-900">{{ data.stats.total_students_in_class }}</p>
          <p class="text-xs text-gray-500">Total Mahasiswa</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-5 hover:shadow-md transition-shadow cursor-pointer" @click="router.push('/akademik/krs')">
          <UserGroupIcon class="w-5 h-5 text-purple-500 mb-2" />
          <p class="text-2xl font-bold text-gray-900">{{ data.stats.my_advisees }}</p>
          <p class="text-xs text-gray-500">Mahasiswa Bimbingan</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-5 hover:shadow-md transition-shadow cursor-pointer" :class="data.stats.pending_krs > 0 ? 'border-orange-300 bg-orange-50' : ''" @click="router.push('/akademik/krs')">
          <ClockIcon class="w-5 h-5 text-orange-500 mb-2" />
          <p class="text-2xl font-bold" :class="data.stats.pending_krs > 0 ? 'text-orange-700' : 'text-gray-900'">{{ data.stats.pending_krs }}</p>
          <p class="text-xs text-gray-500">KRS Menunggu Approval</p>
        </div>
      </div>

      <!-- Quick Menu Shortcuts -->
      <div class="bg-white rounded-xl border border-gray-200 p-5">
        <h2 class="text-sm font-semibold text-gray-800 mb-4">Menu Cepat</h2>
        <div class="grid grid-cols-3 md:grid-cols-6 gap-3">
          <button class="flex flex-col items-center gap-2 p-3 rounded-xl border border-gray-100 hover:border-blue-200 hover:bg-blue-50 transition-all group" @click="router.push('/akademik/kelas')">
            <div class="w-10 h-10 rounded-lg bg-blue-100 flex items-center justify-center group-hover:bg-blue-200 transition-colors">
              <BookOpenIcon class="w-5 h-5 text-blue-600" />
            </div>
            <span class="text-[11px] font-medium text-gray-600 text-center leading-tight">Kelas Saya</span>
          </button>
          <button class="flex flex-col items-center gap-2 p-3 rounded-xl border border-gray-100 hover:border-green-200 hover:bg-green-50 transition-all group" @click="router.push('/akademik/krs')">
            <div class="w-10 h-10 rounded-lg bg-green-100 flex items-center justify-center group-hover:bg-green-200 transition-colors">
              <DocumentCheckIcon class="w-5 h-5 text-green-600" />
            </div>
            <span class="text-[11px] font-medium text-gray-600 text-center leading-tight">Perwalian KRS</span>
          </button>
          <button class="flex flex-col items-center gap-2 p-3 rounded-xl border border-gray-100 hover:border-purple-200 hover:bg-purple-50 transition-all group" @click="router.push('/rps')">
            <div class="w-10 h-10 rounded-lg bg-purple-100 flex items-center justify-center group-hover:bg-purple-200 transition-colors">
              <ClipboardDocumentListIcon class="w-5 h-5 text-purple-600" />
            </div>
            <span class="text-[11px] font-medium text-gray-600 text-center leading-tight">RPKPS / RPS</span>
          </button>
          <button class="flex flex-col items-center gap-2 p-3 rounded-xl border border-gray-100 hover:border-indigo-200 hover:bg-indigo-50 transition-all group" @click="router.push('/penilaian/nilai')">
            <div class="w-10 h-10 rounded-lg bg-indigo-100 flex items-center justify-center group-hover:bg-indigo-200 transition-colors">
              <ChartBarIcon class="w-5 h-5 text-indigo-600" />
            </div>
            <span class="text-[11px] font-medium text-gray-600 text-center leading-tight">Input Nilai</span>
          </button>
          <button class="flex flex-col items-center gap-2 p-3 rounded-xl border border-gray-100 hover:border-teal-200 hover:bg-teal-50 transition-all group" @click="router.push('/persuratan/surat-saya')">
            <div class="w-10 h-10 rounded-lg bg-teal-100 flex items-center justify-center group-hover:bg-teal-200 transition-colors">
              <ChatBubbleLeftRightIcon class="w-5 h-5 text-teal-600" />
            </div>
            <span class="text-[11px] font-medium text-gray-600 text-center leading-tight">Persuratan</span>
          </button>
          <button class="flex flex-col items-center gap-2 p-3 rounded-xl border border-gray-100 hover:border-orange-200 hover:bg-orange-50 transition-all group" @click="router.push('/agenda')">
            <div class="w-10 h-10 rounded-lg bg-orange-100 flex items-center justify-center group-hover:bg-orange-200 transition-colors">
              <CalendarDaysIcon class="w-5 h-5 text-orange-600" />
            </div>
            <span class="text-[11px] font-medium text-gray-600 text-center leading-tight">Agenda</span>
          </button>
        </div>
      </div>

      <!-- Kelas Saya -->
      <div class="bg-white rounded-xl border border-gray-200 p-5">
        <div class="flex items-center justify-between mb-4">
          <h2 class="text-sm font-semibold text-gray-800">Kelas yang Diampu</h2>
          <button class="text-xs text-blue-600 hover:text-blue-700 font-medium" @click="router.push('/akademik/kelas')">Lihat Semua →</button>
        </div>
        <div v-if="data.my_classes?.length" class="grid grid-cols-1 md:grid-cols-2 gap-3">
          <div v-for="cls in data.my_classes" :key="cls.id" class="flex items-center justify-between p-3 bg-gray-50 rounded-lg hover:bg-blue-50 cursor-pointer transition-colors" @click="router.push(`/perkuliahan/${cls.id}`)">
            <div class="min-w-0">
              <p class="text-sm font-medium text-gray-900 truncate">{{ cls.course_name }}</p>
              <p class="text-xs text-gray-500">{{ cls.course_code }} · {{ cls.class_name }} · {{ cls.credits }} SKS</p>
            </div>
            <span class="text-xs px-2 py-0.5 bg-blue-100 text-blue-700 rounded-full font-medium shrink-0">{{ cls.students }}/{{ cls.capacity }}</span>
          </div>
        </div>
        <p v-else class="text-sm text-gray-400 text-center py-4">Belum ada kelas semester ini.</p>
      </div>

      <!-- KRS Pending -->
      <div v-if="data.pending_approvals?.length" class="bg-white rounded-xl border border-orange-200 p-5">
        <h2 class="text-sm font-semibold text-orange-800 mb-3 flex items-center gap-2">
          <ExclamationTriangleIcon class="w-4 h-4" /> KRS Menunggu Persetujuan
        </h2>
        <div class="space-y-2">
          <div v-for="krs in data.pending_approvals" :key="krs.id" class="flex items-center justify-between p-3 bg-orange-50 rounded-lg">
            <div>
              <p class="text-sm font-medium text-gray-900">{{ krs.student_name }}</p>
              <p class="text-xs text-gray-500">{{ krs.student_nim }} · {{ krs.total_credits }} SKS</p>
            </div>
            <button class="px-3 py-1.5 text-xs bg-orange-600 hover:bg-orange-700 text-white rounded-lg font-medium" @click="router.push(`/akademik/krs/${krs.id}`)">Review</button>
          </div>
        </div>
      </div>

      <!-- Agenda Kegiatan Terbaru (Diundang) -->
      <div v-if="data.upcoming_events?.length" class="bg-white rounded-xl border border-gray-200 p-5">
        <div class="flex items-center justify-between mb-4">
          <h2 class="text-sm font-semibold text-gray-800 flex items-center gap-2">
            <CalendarDaysIcon class="w-4 h-4 text-orange-500" /> Undangan Kegiatan Terbaru
          </h2>
          <button class="text-xs text-blue-600 hover:text-blue-700 font-medium" @click="router.push('/agenda')">Lihat Semua →</button>
        </div>
        <div class="space-y-2">
          <div v-for="evt in data.upcoming_events" :key="evt.id" class="flex items-center gap-3 p-3 bg-orange-50 border border-orange-100 rounded-lg hover:bg-orange-100 cursor-pointer transition-colors" @click="router.push(`/agenda/${evt.id}`)">
            <div class="w-11 h-11 rounded-lg bg-orange-200 flex flex-col items-center justify-center flex-shrink-0">
              <span class="text-[10px] font-bold text-orange-700 uppercase">{{ new Date(evt.event_date).toLocaleDateString('id-ID', { month: 'short' }) }}</span>
              <span class="text-sm font-bold text-orange-800 -mt-0.5">{{ new Date(evt.event_date).getDate() }}</span>
            </div>
            <div class="flex-1 min-w-0">
              <p class="text-sm font-medium text-gray-900 truncate">{{ evt.title }}</p>
              <p class="text-xs text-gray-500">{{ evt.start_time }}{{ evt.end_time ? ' - ' + evt.end_time : '' }} · {{ evt.location }}</p>
            </div>
            <span v-if="evt.category" class="text-[10px] px-2 py-0.5 bg-orange-200 text-orange-700 rounded-full font-medium shrink-0">{{ evt.category }}</span>
          </div>
        </div>
      </div>

      <!-- Riwayat Kehadiran Agenda -->
      <div v-if="data.event_attendance_history?.length" class="bg-white rounded-xl border border-gray-200 p-5">
        <div class="flex items-center justify-between mb-4">
          <h2 class="text-sm font-semibold text-gray-800 flex items-center gap-2">
            <CheckCircleIcon class="w-4 h-4 text-green-500" /> Riwayat Kehadiran Agenda
          </h2>
        </div>
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="border-b border-gray-100">
                <th class="text-left py-2 px-2 text-xs font-medium text-gray-500">Kegiatan</th>
                <th class="text-left py-2 px-2 text-xs font-medium text-gray-500">Tanggal</th>
                <th class="text-left py-2 px-2 text-xs font-medium text-gray-500">Hadir</th>
                <th class="text-left py-2 px-2 text-xs font-medium text-gray-500">Metode</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="att in data.event_attendance_history" :key="att.id" class="border-b border-gray-50 hover:bg-gray-50 cursor-pointer" @click="router.push(`/agenda/${att.event_id}`)">
                <td class="py-2.5 px-2">
                  <p class="font-medium text-gray-800 truncate max-w-[200px]">{{ att.event_title }}</p>
                  <p class="text-xs text-gray-400">{{ att.location }}</p>
                </td>
                <td class="py-2.5 px-2 text-xs text-gray-600">{{ att.event_date }}</td>
                <td class="py-2.5 px-2 text-xs text-gray-600">{{ att.attended_at }}</td>
                <td class="py-2.5 px-2">
                  <span class="inline-flex items-center gap-1 text-xs px-2 py-0.5 rounded-full font-medium" :class="att.method === 'qr' ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-600'">
                    {{ att.method === 'qr' ? '📱 QR' : att.method === 'manual' ? '✍️ Manual' : att.method }}
                  </span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Surat Masuk Terbaru -->
      <div v-if="data.incoming_letters?.length" class="bg-white rounded-xl border border-gray-200 p-5">
        <div class="flex items-center justify-between mb-4">
          <h2 class="text-sm font-semibold text-gray-800 flex items-center gap-2">
            <ChatBubbleLeftRightIcon class="w-4 h-4 text-teal-500" /> Surat Masuk Terbaru
          </h2>
          <button class="text-xs text-blue-600 hover:text-blue-700 font-medium" @click="router.push('/persuratan/surat-saya')">Lihat Semua →</button>
        </div>
        <div class="space-y-2">
          <div v-for="letter in data.incoming_letters" :key="letter.id"
            class="flex items-center gap-3 p-3 rounded-lg border border-gray-100 hover:border-teal-200 hover:bg-teal-50/50 cursor-pointer transition-colors"
            @click="router.push(`/persuratan/surat-saya/${letter.id}`)">
            <div class="w-10 h-10 rounded-lg flex items-center justify-center flex-shrink-0" :class="letter.is_read ? 'bg-gray-100' : 'bg-teal-100'">
              <svg class="w-5 h-5" :class="letter.is_read ? 'text-gray-400' : 'text-teal-600'" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
              </svg>
            </div>
            <div class="flex-1 min-w-0">
              <div class="flex items-center gap-2">
                <p class="text-sm text-gray-900 truncate" :class="!letter.is_read ? 'font-semibold' : 'font-medium'">{{ letter.subject }}</p>
                <span v-if="!letter.is_read" class="w-2 h-2 bg-teal-500 rounded-full flex-shrink-0"></span>
              </div>
              <p class="text-xs text-gray-500 truncate">{{ letter.letter_type }} · {{ letter.letter_number }} · {{ letter.signer_name }}</p>
            </div>
            <span class="text-[10px] text-gray-400 flex-shrink-0">{{ letter.letter_date }}</span>
          </div>
        </div>
      </div>

      <!-- Kalender Akademik -->
      <div class="bg-white rounded-xl border border-gray-200 p-5">
        <div class="flex items-center justify-between mb-3">
          <h2 class="text-sm font-semibold text-gray-800 flex items-center gap-2">
            <CalendarDaysIcon class="w-4 h-4 text-blue-500" /> Kalender Akademik
          </h2>
          <button class="text-xs text-blue-600 hover:text-blue-700 font-medium" @click="router.push('/master-data/academic-calendar')">Lihat Semua →</button>
        </div>
        <div v-if="data.upcoming_calendar?.length" class="space-y-2">
          <div v-for="event in data.upcoming_calendar" :key="event.title" class="flex items-center gap-3 p-2.5 rounded-lg bg-gray-50">
            <div class="w-1.5 h-8 rounded-full shrink-0" :style="{ backgroundColor: event.color || '#3b82f6' }" />
            <div class="flex-1 min-w-0">
              <p class="text-sm font-medium text-gray-800 truncate">{{ event.title }}</p>
              <p class="text-xs text-gray-500">{{ event.start_date }}{{ event.end_date && event.end_date !== event.start_date ? ' — ' + event.end_date : '' }}</p>
            </div>
            <span v-if="event.category" class="text-[10px] px-1.5 py-0.5 bg-gray-200 text-gray-600 rounded shrink-0">{{ event.category }}</span>
          </div>
        </div>
        <p v-else class="text-sm text-gray-400 text-center py-4">Belum ada jadwal kalender akademik.</p>
      </div>

      <!-- ============================== -->
      <!-- SECTION JABATAN (KAPRODI/DEKAN/PIMPINAN) -->
      <!-- ============================== -->

      <!-- Jabatan Badge -->
      <div v-if="data.positions?.length" class="flex items-center gap-2 flex-wrap">
        <span v-for="pos in data.positions" :key="pos.code" class="inline-flex items-center gap-1 px-3 py-1.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-700 border border-indigo-200">
          <BriefcaseIcon class="w-3.5 h-3.5" />
          {{ pos.name }}
        </span>
      </div>

      <!-- KAPRODI Section -->
      <div v-if="data.position_dashboard?.type === 'KAPRODI'" class="space-y-4">
        <div class="bg-indigo-50 border border-indigo-200 rounded-xl p-5">
          <div class="flex items-center gap-2 mb-4">
            <BuildingLibraryIcon class="w-5 h-5 text-indigo-600" />
            <h2 class="text-sm font-semibold text-indigo-800">{{ data.position_dashboard.position_name }} — {{ data.position_dashboard.prodi?.name }}</h2>
          </div>
          <div class="grid grid-cols-3 md:grid-cols-6 gap-3">
            <div class="text-center p-2 bg-white rounded-lg">
              <p class="text-lg font-bold text-blue-700">{{ data.position_dashboard.stats.active_students }}</p>
              <p class="text-[10px] text-gray-500">Mhs Aktif</p>
            </div>
            <div class="text-center p-2 bg-white rounded-lg">
              <p class="text-lg font-bold text-yellow-700">{{ data.position_dashboard.stats.cuti_students }}</p>
              <p class="text-[10px] text-gray-500">Cuti</p>
            </div>
            <div class="text-center p-2 bg-white rounded-lg">
              <p class="text-lg font-bold text-green-700">{{ data.position_dashboard.stats.lecturers }}</p>
              <p class="text-[10px] text-gray-500">Dosen</p>
            </div>
            <div class="text-center p-2 bg-white rounded-lg">
              <p class="text-lg font-bold text-purple-700">{{ data.position_dashboard.stats.classes }}</p>
              <p class="text-[10px] text-gray-500">Kelas</p>
            </div>
            <div class="text-center p-2 bg-white rounded-lg">
              <p class="text-lg font-bold text-orange-700">{{ data.position_dashboard.stats.pending_krs }}</p>
              <p class="text-[10px] text-gray-500">KRS Pending</p>
            </div>
            <div class="text-center p-2 bg-white rounded-lg">
              <p class="text-lg font-bold" :class="data.position_dashboard.stats.ratio <= 25 ? 'text-green-700' : data.position_dashboard.stats.ratio <= 40 ? 'text-yellow-700' : 'text-red-700'">1:{{ data.position_dashboard.stats.ratio }}</p>
              <p class="text-[10px] text-gray-500">Rasio D:M</p>
            </div>
          </div>
          <!-- Mahasiswa per angkatan -->
          <div v-if="data.position_dashboard.students_by_entry_year?.length" class="mt-4 pt-4 border-t border-indigo-100">
            <BarChart
              :data="[...data.position_dashboard.students_by_entry_year].reverse().map((item: any) => ({ label: String(item.entry_year), value: item.count, color: 'bg-indigo-500' }))"
              title="Mahasiswa Aktif Per Angkatan"
              :height="140"
              orientation="vertical"
            />
          </div>
        </div>
      </div>

      <!-- DEKAN Section -->
      <div v-if="data.position_dashboard?.type === 'DEKAN'" class="bg-purple-50 border border-purple-200 rounded-xl p-5">
        <div class="flex items-center gap-2 mb-4">
          <BuildingLibraryIcon class="w-5 h-5 text-purple-600" />
          <h2 class="text-sm font-semibold text-purple-800">{{ data.position_dashboard.position_name }} — Ringkasan Fakultas</h2>
        </div>
        <div class="grid grid-cols-3 gap-4 mb-4">
          <div class="text-center p-3 bg-white rounded-lg">
            <p class="text-xl font-bold text-blue-700">{{ data.position_dashboard.stats.total_students }}</p>
            <p class="text-xs text-gray-500">Mahasiswa Aktif</p>
          </div>
          <div class="text-center p-3 bg-white rounded-lg">
            <p class="text-xl font-bold text-green-700">{{ data.position_dashboard.stats.total_lecturers }}</p>
            <p class="text-xs text-gray-500">Dosen</p>
          </div>
          <div class="text-center p-3 bg-white rounded-lg">
            <p class="text-xl font-bold text-purple-700">{{ data.position_dashboard.stats.total_prodi }}</p>
            <p class="text-xs text-gray-500">Program Studi</p>
          </div>
        </div>
        <div v-if="data.position_dashboard.students_by_prodi?.length" class="space-y-2">
          <div v-for="item in data.position_dashboard.students_by_prodi" :key="item.study_program_id" class="flex items-center justify-between p-2 bg-white rounded-lg">
            <span class="text-xs text-gray-700">{{ item.study_program?.name }}</span>
            <span class="text-xs font-bold text-purple-700 bg-purple-100 px-2 py-0.5 rounded-full">{{ item.count }}</span>
          </div>
        </div>
      </div>

      <!-- PIMPINAN Section -->
      <div v-if="data.position_dashboard?.type === 'PIMPINAN'" class="bg-amber-50 border border-amber-200 rounded-xl p-5">
        <div class="flex items-center gap-2 mb-4">
          <BuildingLibraryIcon class="w-5 h-5 text-amber-600" />
          <h2 class="text-sm font-semibold text-amber-800">Ringkasan Institusi</h2>
        </div>
        <div class="grid grid-cols-5 gap-3">
          <div class="text-center p-3 bg-white rounded-lg">
            <p class="text-xl font-bold text-blue-700">{{ data.position_dashboard.stats.total_students }}</p>
            <p class="text-[10px] text-gray-500">Mahasiswa</p>
          </div>
          <div class="text-center p-3 bg-white rounded-lg">
            <p class="text-xl font-bold text-green-700">{{ data.position_dashboard.stats.total_lecturers }}</p>
            <p class="text-[10px] text-gray-500">Dosen</p>
          </div>
          <div class="text-center p-3 bg-white rounded-lg">
            <p class="text-xl font-bold text-purple-700">{{ data.position_dashboard.stats.total_prodi }}</p>
            <p class="text-[10px] text-gray-500">Prodi</p>
          </div>
          <div class="text-center p-3 bg-white rounded-lg">
            <p class="text-xl font-bold text-teal-700">{{ data.position_dashboard.stats.total_alumni }}</p>
            <p class="text-[10px] text-gray-500">Alumni</p>
          </div>
          <div class="text-center p-3 bg-white rounded-lg">
            <p class="text-sm font-bold text-orange-700">{{ formatCurrency(data.position_dashboard.stats.revenue) }}</p>
            <p class="text-[10px] text-gray-500">Pendapatan</p>
          </div>
        </div>
      </div>
    </template>

    <!-- ============================================ -->
    <!-- DASHBOARD MAHASISWA -->
    <!-- ============================================ -->
    <template v-else-if="dashboardRole === 'MAHASISWA'">
      <!-- Info Mahasiswa -->
      <div class="bg-white rounded-xl border border-gray-200 p-5">
        <div class="flex items-center gap-4">
          <div class="w-14 h-14 rounded-full bg-blue-100 flex items-center justify-center">
            <AcademicCapIcon class="w-7 h-7 text-blue-600" />
          </div>
          <div>
            <h2 class="text-lg font-bold text-gray-900">{{ data.student.name }}</h2>
            <p class="text-sm text-gray-500">{{ data.student.nim }} · {{ data.student.prodi }}</p>
            <p class="text-xs text-gray-400">Angkatan {{ data.student.entry_year }} · Semester {{ data.student.current_semester ?? '-' }} · Dosen Wali: {{ data.student.advisor }}</p>
          </div>
          <span class="ml-auto px-3 py-1 rounded-full text-xs font-medium" :class="data.student.status === 'Aktif' ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700'">
            {{ data.student.status }}
          </span>
        </div>
      </div>

      <!-- Akademik Stats -->
      <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-gradient-to-br from-blue-50 to-blue-100 rounded-xl border border-blue-200 p-5 text-center">
          <p class="text-3xl font-bold text-blue-800">{{ data.academic.ipk }}</p>
          <p class="text-xs text-blue-600 font-medium mt-1">IPK</p>
        </div>
        <div class="bg-gradient-to-br from-green-50 to-green-100 rounded-xl border border-green-200 p-5 text-center">
          <p class="text-3xl font-bold text-green-800">{{ data.academic.ips }}</p>
          <p class="text-xs text-green-600 font-medium mt-1">IPS Semester Ini</p>
        </div>
        <div class="bg-gradient-to-br from-purple-50 to-purple-100 rounded-xl border border-purple-200 p-5 text-center">
          <p class="text-3xl font-bold text-purple-800">{{ data.academic.total_credits }}</p>
          <p class="text-xs text-purple-600 font-medium mt-1">SKS Ditempuh</p>
        </div>
        <div class="rounded-xl border p-5 text-center" :class="data.academic.krs_status === 'APPROVED' ? 'bg-green-50 border-green-200' : data.academic.krs_status === 'SUBMITTED' ? 'bg-yellow-50 border-yellow-200' : 'bg-gray-50 border-gray-200'">
          <p class="text-lg font-bold" :class="data.academic.krs_status === 'APPROVED' ? 'text-green-700' : data.academic.krs_status === 'SUBMITTED' ? 'text-yellow-700' : 'text-gray-700'">
            {{ data.academic.krs_status.replace(/_/g, ' ') }}
          </p>
          <p class="text-xs text-gray-500 mt-1">Status KRS ({{ data.academic.krs_credits }} SKS)</p>
        </div>
      </div>

      <!-- Tagihan -->
      <div v-if="data.finance.unpaid_count > 0" class="bg-red-50 border border-red-200 rounded-xl p-5">
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-3">
            <ExclamationTriangleIcon class="w-6 h-6 text-red-500" />
            <div>
              <p class="text-sm font-medium text-red-800">Anda memiliki {{ data.finance.unpaid_count }} tagihan belum lunas</p>
              <p class="text-xs text-red-600 mt-0.5">Total: {{ formatCurrency(data.finance.total_unpaid) }}</p>
            </div>
          </div>
          <button class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white text-sm rounded-lg font-medium" @click="router.push('/keuangan')">
            Lihat Tagihan
          </button>
        </div>
      </div>
      <div v-else class="bg-green-50 border border-green-200 rounded-xl p-4 flex items-center gap-3">
        <CheckCircleIcon class="w-5 h-5 text-green-600" />
        <p class="text-sm text-green-700 font-medium">Keuangan Anda clear. Tidak ada tagihan tertunggak.</p>
      </div>

      <!-- Quick Links -->
      <div class="bg-white rounded-xl border border-gray-200 p-5">
        <h2 class="text-sm font-semibold text-gray-800 mb-4">Menu Cepat</h2>
        <div class="grid grid-cols-3 md:grid-cols-6 gap-3">
          <button class="flex flex-col items-center gap-2 p-3 rounded-xl border border-gray-100 hover:border-blue-200 hover:bg-blue-50 transition-all group" @click="router.push('/akademik/krs-saya')">
            <div class="w-10 h-10 rounded-lg bg-blue-100 flex items-center justify-center group-hover:bg-blue-200 transition-colors">
              <BookOpenIcon class="w-5 h-5 text-blue-600" />
            </div>
            <span class="text-[11px] font-medium text-gray-600 text-center leading-tight">KRS Saya</span>
          </button>
          <button class="flex flex-col items-center gap-2 p-3 rounded-xl border border-gray-100 hover:border-green-200 hover:bg-green-50 transition-all group" @click="router.push('/akademik-saya/khs')">
            <div class="w-10 h-10 rounded-lg bg-green-100 flex items-center justify-center group-hover:bg-green-200 transition-colors">
              <DocumentCheckIcon class="w-5 h-5 text-green-600" />
            </div>
            <span class="text-[11px] font-medium text-gray-600 text-center leading-tight">KHS / Transkrip</span>
          </button>
          <button class="flex flex-col items-center gap-2 p-3 rounded-xl border border-gray-100 hover:border-purple-200 hover:bg-purple-50 transition-all group" @click="router.push('/akademik-saya/kelas')">
            <div class="w-10 h-10 rounded-lg bg-purple-100 flex items-center justify-center group-hover:bg-purple-200 transition-colors">
              <CalendarDaysIcon class="w-5 h-5 text-purple-600" />
            </div>
            <span class="text-[11px] font-medium text-gray-600 text-center leading-tight">Kelas Saya</span>
          </button>
          <button class="flex flex-col items-center gap-2 p-3 rounded-xl border border-gray-100 hover:border-orange-200 hover:bg-orange-50 transition-all group" @click="router.push('/keuangan/saya')">
            <div class="w-10 h-10 rounded-lg bg-orange-100 flex items-center justify-center group-hover:bg-orange-200 transition-colors">
              <CurrencyDollarIcon class="w-5 h-5 text-orange-600" />
            </div>
            <span class="text-[11px] font-medium text-gray-600 text-center leading-tight">Keuangan</span>
          </button>
          <button class="flex flex-col items-center gap-2 p-3 rounded-xl border border-gray-100 hover:border-teal-200 hover:bg-teal-50 transition-all group" @click="router.push('/persuratan/surat-saya')">
            <div class="w-10 h-10 rounded-lg bg-teal-100 flex items-center justify-center group-hover:bg-teal-200 transition-colors">
              <ChatBubbleLeftRightIcon class="w-5 h-5 text-teal-600" />
            </div>
            <span class="text-[11px] font-medium text-gray-600 text-center leading-tight">Surat Masuk</span>
          </button>
          <button class="flex flex-col items-center gap-2 p-3 rounded-xl border border-gray-100 hover:border-rose-200 hover:bg-rose-50 transition-all group" @click="router.push('/agenda')">
            <div class="w-10 h-10 rounded-lg bg-rose-100 flex items-center justify-center group-hover:bg-rose-200 transition-colors">
              <CalendarDaysIcon class="w-5 h-5 text-rose-600" />
            </div>
            <span class="text-[11px] font-medium text-gray-600 text-center leading-tight">Agenda</span>
          </button>
        </div>
      </div>

      <!-- Agenda Kegiatan Terbaru (Diundang) -->
      <div v-if="data.upcoming_events?.length" class="bg-white rounded-xl border border-gray-200 p-5">
        <div class="flex items-center justify-between mb-4">
          <h2 class="text-sm font-semibold text-gray-800 flex items-center gap-2">
            <CalendarDaysIcon class="w-4 h-4 text-orange-500" /> Undangan Kegiatan Terbaru
          </h2>
          <button class="text-xs text-blue-600 hover:text-blue-700 font-medium" @click="router.push('/agenda')">Lihat Semua →</button>
        </div>
        <div class="space-y-2">
          <div v-for="evt in data.upcoming_events" :key="evt.id" class="flex items-center gap-3 p-3 bg-orange-50 border border-orange-100 rounded-lg hover:bg-orange-100 cursor-pointer transition-colors" @click="router.push(`/agenda/${evt.id}`)">
            <div class="w-11 h-11 rounded-lg bg-orange-200 flex flex-col items-center justify-center flex-shrink-0">
              <span class="text-[10px] font-bold text-orange-700 uppercase">{{ new Date(evt.event_date).toLocaleDateString('id-ID', { month: 'short' }) }}</span>
              <span class="text-sm font-bold text-orange-800 -mt-0.5">{{ new Date(evt.event_date).getDate() }}</span>
            </div>
            <div class="flex-1 min-w-0">
              <p class="text-sm font-medium text-gray-900 truncate">{{ evt.title }}</p>
              <p class="text-xs text-gray-500">{{ evt.start_time }}{{ evt.end_time ? ' - ' + evt.end_time : '' }} · {{ evt.location }}</p>
            </div>
            <span v-if="evt.category" class="text-[10px] px-2 py-0.5 bg-orange-200 text-orange-700 rounded-full font-medium shrink-0">{{ evt.category }}</span>
          </div>
        </div>
      </div>

      <!-- Riwayat Kehadiran Agenda -->
      <div v-if="data.event_attendance_history?.length" class="bg-white rounded-xl border border-gray-200 p-5">
        <div class="flex items-center justify-between mb-4">
          <h2 class="text-sm font-semibold text-gray-800 flex items-center gap-2">
            <CheckCircleIcon class="w-4 h-4 text-green-500" /> Riwayat Kehadiran Agenda
          </h2>
        </div>
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="border-b border-gray-100">
                <th class="text-left py-2 px-2 text-xs font-medium text-gray-500">Kegiatan</th>
                <th class="text-left py-2 px-2 text-xs font-medium text-gray-500">Tanggal</th>
                <th class="text-left py-2 px-2 text-xs font-medium text-gray-500">Hadir</th>
                <th class="text-left py-2 px-2 text-xs font-medium text-gray-500">Metode</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="att in data.event_attendance_history" :key="att.id" class="border-b border-gray-50 hover:bg-gray-50 cursor-pointer" @click="router.push(`/agenda/${att.event_id}`)">
                <td class="py-2.5 px-2">
                  <p class="font-medium text-gray-800 truncate max-w-[200px]">{{ att.event_title }}</p>
                  <p class="text-xs text-gray-400">{{ att.location }}</p>
                </td>
                <td class="py-2.5 px-2 text-xs text-gray-600">{{ att.event_date }}</td>
                <td class="py-2.5 px-2 text-xs text-gray-600">{{ att.attended_at }}</td>
                <td class="py-2.5 px-2">
                  <span class="inline-flex items-center gap-1 text-xs px-2 py-0.5 rounded-full font-medium" :class="att.method === 'qr' ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-600'">
                    {{ att.method === 'qr' ? '📱 QR' : att.method === 'manual' ? '✍️ Manual' : att.method }}
                  </span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Surat Masuk Terbaru -->
      <div v-if="data.incoming_letters?.length" class="bg-white rounded-xl border border-gray-200 p-5">
        <div class="flex items-center justify-between mb-4">
          <h2 class="text-sm font-semibold text-gray-800 flex items-center gap-2">
            <ChatBubbleLeftRightIcon class="w-4 h-4 text-teal-500" /> Surat Masuk Terbaru
          </h2>
          <button class="text-xs text-blue-600 hover:text-blue-700 font-medium" @click="router.push('/persuratan/surat-saya')">Lihat Semua →</button>
        </div>
        <div class="space-y-2">
          <div v-for="letter in data.incoming_letters" :key="letter.id"
            class="flex items-center gap-3 p-3 rounded-lg border border-gray-100 hover:border-teal-200 hover:bg-teal-50/50 cursor-pointer transition-colors"
            @click="router.push(`/persuratan/surat-saya/${letter.id}`)">
            <div class="w-10 h-10 rounded-lg flex items-center justify-center flex-shrink-0" :class="letter.is_read ? 'bg-gray-100' : 'bg-teal-100'">
              <svg class="w-5 h-5" :class="letter.is_read ? 'text-gray-400' : 'text-teal-600'" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
              </svg>
            </div>
            <div class="flex-1 min-w-0">
              <div class="flex items-center gap-2">
                <p class="text-sm text-gray-900 truncate" :class="!letter.is_read ? 'font-semibold' : 'font-medium'">{{ letter.subject }}</p>
                <span v-if="!letter.is_read" class="w-2 h-2 bg-teal-500 rounded-full flex-shrink-0"></span>
              </div>
              <p class="text-xs text-gray-500 truncate">{{ letter.letter_type }} · {{ letter.letter_number }} · {{ letter.signer_name }}</p>
            </div>
            <span class="text-[10px] text-gray-400 flex-shrink-0">{{ letter.letter_date }}</span>
          </div>
        </div>
      </div>

      <!-- Kalender Akademik -->
      <div class="bg-white rounded-xl border border-gray-200 p-5">
        <div class="flex items-center justify-between mb-3">
          <h2 class="text-sm font-semibold text-gray-800 flex items-center gap-2">
            <CalendarDaysIcon class="w-4 h-4 text-blue-500" /> Kalender Akademik
          </h2>
          <button class="text-xs text-blue-600 hover:text-blue-700 font-medium" @click="router.push('/master-data/academic-calendar')">Lihat Semua →</button>
        </div>
        <div v-if="data.upcoming_calendar?.length" class="space-y-2">
          <div v-for="event in data.upcoming_calendar" :key="event.title" class="flex items-center gap-3 p-2.5 rounded-lg bg-gray-50">
            <div class="w-1.5 h-8 rounded-full shrink-0" :style="{ backgroundColor: event.color || '#3b82f6' }" />
            <div class="flex-1 min-w-0">
              <p class="text-sm font-medium text-gray-800 truncate">{{ event.title }}</p>
              <p class="text-xs text-gray-500">{{ event.start_date }}{{ event.end_date && event.end_date !== event.start_date ? ' — ' + event.end_date : '' }}</p>
            </div>
            <span v-if="event.category" class="text-[10px] px-1.5 py-0.5 bg-gray-200 text-gray-600 rounded shrink-0">{{ event.category }}</span>
          </div>
        </div>
        <p v-else class="text-sm text-gray-400 text-center py-4">Belum ada jadwal kalender akademik.</p>
      </div>
    </template>

    <!-- ============================================ -->
    <!-- DASHBOARD KEUANGAN -->
    <!-- ============================================ -->
    <template v-else-if="dashboardRole === 'KEUANGAN'">
      <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-4">
        <div class="bg-white rounded-xl border border-gray-200 p-5">
          <CurrencyDollarIcon class="w-5 h-5 text-green-500 mb-2" />
          <p class="text-lg font-bold text-gray-900">{{ formatCurrency(data.stats.total_paid) }}</p>
          <p class="text-xs text-gray-500">Total Terbayar</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-5">
          <ArrowTrendingUpIcon class="w-5 h-5 text-blue-500 mb-2" />
          <p class="text-lg font-bold text-gray-900">{{ formatCurrency(data.stats.total_invoiced) }}</p>
          <p class="text-xs text-gray-500">Total Tagihan</p>
        </div>
        <div class="bg-white rounded-xl border border-red-200 p-5 bg-red-50">
          <ExclamationTriangleIcon class="w-5 h-5 text-red-500 mb-2" />
          <p class="text-lg font-bold text-red-700">{{ formatCurrency(data.stats.total_outstanding) }}</p>
          <p class="text-xs text-red-600">Tunggakan</p>
        </div>
        <div class="bg-white rounded-xl border border-yellow-200 p-5 bg-yellow-50">
          <ClockIcon class="w-5 h-5 text-yellow-500 mb-2" />
          <p class="text-2xl font-bold text-yellow-700">{{ data.stats.pending_payments }}</p>
          <p class="text-xs text-yellow-600">Pending Verifikasi</p>
        </div>
        <div class="bg-white rounded-xl border border-orange-200 p-5 bg-orange-50">
          <ExclamationTriangleIcon class="w-5 h-5 text-orange-500 mb-2" />
          <p class="text-2xl font-bold text-orange-700">{{ data.stats.overdue_invoices }}</p>
          <p class="text-xs text-orange-600">Jatuh Tempo</p>
        </div>
      </div>

      <!-- Pembayaran Pending -->
      <div v-if="data.recent_payments?.length" class="bg-white rounded-xl border border-gray-200 p-5">
        <div class="flex items-center justify-between mb-4">
          <h2 class="text-sm font-semibold text-gray-800">Pembayaran Menunggu Verifikasi</h2>
          <button class="text-xs text-blue-600 hover:text-blue-700 font-medium" @click="router.push('/keuangan/pembayaran')">Lihat Semua →</button>
        </div>
        <div class="space-y-2">
          <div v-for="pay in data.recent_payments" :key="pay.id" class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
            <div>
              <p class="text-sm font-medium text-gray-900">{{ pay.student_name }}</p>
              <p class="text-xs text-gray-500">{{ pay.student_nim }} · {{ pay.method }}</p>
            </div>
            <span class="text-sm font-bold text-green-700">{{ formatCurrency(pay.amount) }}</span>
          </div>
        </div>
      </div>

      <!-- Quick Actions -->
      <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
        <button class="flex flex-col items-center gap-2 p-4 bg-white rounded-xl border border-gray-200 hover:border-blue-300 hover:bg-blue-50 transition-colors" @click="router.push('/keuangan/pembayaran')">
          <CheckCircleIcon class="w-6 h-6 text-blue-600" />
          <span class="text-xs font-medium text-gray-700">Verifikasi Bayar</span>
        </button>
        <button class="flex flex-col items-center gap-2 p-4 bg-white rounded-xl border border-gray-200 hover:border-green-300 hover:bg-green-50 transition-colors" @click="router.push('/keuangan/tagihan')">
          <DocumentCheckIcon class="w-6 h-6 text-green-600" />
          <span class="text-xs font-medium text-gray-700">Kelola Tagihan</span>
        </button>
        <button class="flex flex-col items-center gap-2 p-4 bg-white rounded-xl border border-gray-200 hover:border-purple-300 hover:bg-purple-50 transition-colors" @click="router.push('/keuangan/tagihan/generate')">
          <ArrowTrendingUpIcon class="w-6 h-6 text-purple-600" />
          <span class="text-xs font-medium text-gray-700">Generate Tagihan</span>
        </button>
        <button class="flex flex-col items-center gap-2 p-4 bg-white rounded-xl border border-gray-200 hover:border-orange-300 hover:bg-orange-50 transition-colors" @click="router.push('/keuangan/beasiswa')">
          <AcademicCapIcon class="w-6 h-6 text-orange-600" />
          <span class="text-xs font-medium text-gray-700">Beasiswa</span>
        </button>
      </div>
    </template>
  </div>
</template>
