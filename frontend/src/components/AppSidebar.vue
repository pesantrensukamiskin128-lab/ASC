<script setup lang="ts">
import { computed } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useInstitutionStore } from '@/stores/institution'
import {
  HomeIcon,
  UsersIcon,
  AcademicCapIcon,
  ClipboardDocumentListIcon,
  CurrencyDollarIcon,
  ChartBarIcon,
  Cog6ToothIcon,
  BuildingLibraryIcon,
  ChatBubbleLeftRightIcon,
  CalendarDaysIcon,
  BookOpenIcon,
} from '@heroicons/vue/24/outline'

defineProps<{ open: boolean }>()
defineEmits<{ close: [] }>()

const route       = useRoute()
const auth        = useAuthStore()
const institution = useInstitutionStore()

const navigation = computed(() => [
  {
    label: 'Dashboard',
    to: '/dashboard',
    icon: HomeIcon,
    show: true,
  },
  {
    label: 'Kalender Akademik',
    to: '/master-data/academic-calendar',
    icon: CalendarDaysIcon,
    show: !auth.hasRole('SUPER_ADMIN') && !auth.hasRole('ADMIN_AKADEMIK'),
  },
  {
    label: 'Master Data',
    icon: BuildingLibraryIcon,
    show: auth.hasRole('SUPER_ADMIN') || auth.hasRole('ADMIN_AKADEMIK'),
    children: [
      { label: 'Institusi', to: '/master-data/institutions' },
      { label: 'Fakultas', to: '/master-data/faculties' },
      { label: 'Program Studi', to: '/master-data/study-programs' },
      { label: 'Konsentrasi', to: '/master-data/concentrations' },
      { label: 'Tahun Akademik', to: '/master-data/academic-years' },
      { label: 'Semester', to: '/master-data/semesters' },
      { label: 'Kalender Akademik', to: '/master-data/academic-calendar' },
      { label: 'Gedung', to: '/master-data/buildings' },
      { label: 'Ruangan', to: '/master-data/rooms' },
      { label: 'Mata Kuliah', to: '/master-data/courses' },
    ],
  },
  {
    label: 'SDM',
    icon: UsersIcon,
    show: auth.hasPermission('mahasiswa.view'),
    children: [
      { label: 'Mahasiswa', to: '/sdm/students' },
      { label: 'Dosen', to: '/sdm/lecturers' },
      { label: 'Tenaga Kependidikan', to: '/sdm/staff', show: auth.hasRole('SUPER_ADMIN') || auth.hasRole('ADMIN_AKADEMIK') },
    ].filter(c => c.show !== false),
  },
  {
    label: 'PMB',
    icon: AcademicCapIcon,
    show: auth.hasPermission('pmb.view'),
    children: [
      { label: 'Dashboard PMB', to: '/pmb' },
      { label: 'Periode & Gelombang', to: '/pmb/periods' },
      { label: 'Jalur Seleksi', to: '/pmb/paths' },
      { label: 'Jenis Ujian', to: '/pmb/exam-types' },
      { label: 'Pendaftar', to: '/pmb/registrants' },
    ],
  },
  {
    label: 'Akademik',
    icon: AcademicCapIcon,
    show: !auth.hasRole('MAHASISWA') && (auth.hasPermission('krs.view') || auth.hasPermission('rps.view') || auth.hasPermission('jadwal.view') || auth.hasPermission('nilai.view')),
    children: [
      { label: 'Kurikulum OBE', to: '/kurikulum', show: auth.hasPermission('kurikulum.view') },
      { label: 'RPKPS / RPS', to: '/rps', show: auth.hasPermission('rps.view') },
      { label: 'Penawaran Kelas', to: '/akademik/kelas', show: auth.hasPermission('jadwal.view') },
      { label: 'KRS & Perwalian', to: '/akademik/krs', show: auth.hasPermission('krs.view') },
      { label: 'Input Nilai', to: '/penilaian/nilai', show: auth.hasPermission('nilai.create') },
      { label: 'Bank Soal', to: '/bank-soal', show: auth.hasPermission('nilai.view') },
      { label: 'KHS & Transkrip', to: '/penilaian/khs', show: auth.hasPermission('khs.view') || auth.hasPermission('khs.generate') },
      { label: 'Praktikum / KKN', to: '/praktikum', show: auth.hasPermission('kkn.create') || auth.hasPermission('kkn.edit') },
      { label: 'Skripsi / TA', to: '/skripsi', show: auth.hasPermission('skripsi.create') || auth.hasPermission('skripsi.approve') },
      { label: 'Karya Dosen', to: '/karya-dosen', show: (auth.hasPermission('skripsi.view') || auth.hasPermission('karya.view')) && !auth.hasRole('MAHASISWA') },
      { label: 'Penelitian & Pengabdian', to: '/penelitian', show: auth.hasPermission('skripsi.view') && !auth.hasRole('MAHASISWA') },
      { label: 'Periode Hibah', to: '/penelitian/periode', show: (auth.hasPermission('karya.verify') || auth.hasRole('SUPER_ADMIN') || auth.hasRole('ADMIN_AKADEMIK')) },
      { label: 'Wisuda', to: '/wisuda', show: auth.hasPermission('wisuda.view') || auth.hasPermission('yudisium.view') },
      { label: 'Cuti Akademik', to: '/akademik/cuti', show: auth.hasPermission('krs.view') && !auth.hasRole('DOSEN') },
      { label: 'Transfer Nilai', to: '/akademik/transfer', show: auth.hasPermission('krs.view') && !auth.hasRole('DOSEN') },
    ].filter(c => c.show !== false),
  },
  {
    label: 'Akademik',
    icon: AcademicCapIcon,
    show: auth.hasRole('MAHASISWA'),
    children: [
      { label: 'KRS Saya', to: '/akademik/krs-saya' },
      { label: 'RPS Mata Kuliah', to: '/akademik-saya/rps' },
      { label: 'Kelas Saya', to: '/akademik-saya/kelas' },
      { label: 'KHS / Transkrip', to: '/akademik-saya/khs' },
      { label: 'Praktikum / KKN', to: '/akademik-saya/praktikum' },
      { label: 'Skripsi / TA', to: '/akademik-saya/skripsi' },
      { label: 'Cuti Akademik', to: '/akademik-saya/cuti' },
      { label: 'Daftar Wisuda', to: '/akademik-saya/wisuda' },
    ],
  },
  {
    label: 'LP2M',
    icon: ClipboardDocumentListIcon,
    show: auth.hasPermission('karya.verify'),
    children: [
      { label: 'Karya Dosen', to: '/karya-dosen' },
      { label: 'Skripsi / TA', to: '/skripsi' },
      { label: 'Praktikum / KKN', to: '/praktikum' },
      { label: 'Hibah Penelitian', to: '/penelitian' },
      { label: 'Periode Hibah', to: '/penelitian/periode' },
    ],
  },
  {
    label: 'Alumni',
    icon: AcademicCapIcon,
    show: auth.hasPermission('alumni.view'),
    children: [
      { label: 'Dashboard', to: '/alumni' },
      { label: 'Data Alumni', to: '/alumni/data' },
      { label: 'Tracer Study', to: '/alumni/tracer-study' },
    ],
  },
  {
    label: 'Keuangan',
    icon: CurrencyDollarIcon,
    show: auth.hasPermission('keuangan.view') && !auth.hasRole('MAHASISWA'),
    children: [
      { label: 'Dashboard', to: '/keuangan' },
      { label: 'Jenis Tagihan', to: '/keuangan/jenis-tagihan' },
      { label: 'Tagihan', to: '/keuangan/tagihan' },
      { label: 'Pembayaran', to: '/keuangan/pembayaran' },
      { label: 'Beasiswa', to: '/keuangan/beasiswa' },
    ],
  },
  {
    label: 'Keuangan Saya',
    to: '/keuangan/saya',
    icon: CurrencyDollarIcon,
    show: auth.hasRole('MAHASISWA'),
  },
  {
    label: 'Laporan',
    icon: ChartBarIcon,
    show: auth.hasPermission('dashboard.analytics'),
    children: [
      { label: 'Ringkasan', to: '/laporan' },
      { label: 'Statistik Mahasiswa', to: '/laporan/mahasiswa' },
      { label: 'Statistik Akademik', to: '/laporan/akademik' },
      { label: 'Statistik Keuangan', to: '/laporan/keuangan' },
      { label: 'Statistik SDM', to: '/laporan/sdm' },
    ],
  },
  {
    label: 'Repository',
    to: '/repository',
    icon: BookOpenIcon,
    show: true,
  },
  {
    label: 'Persuratan',
    icon: ClipboardDocumentListIcon,
    show: auth.hasPermission('surat-keluar.view') || auth.hasPermission('surat-masuk.view') || auth.hasPermission('disposisi.receive'),
    children: [
      { label: 'Surat Keluar', to: '/persuratan/surat-keluar', show: auth.hasPermission('surat-keluar.view') },
      { label: 'Surat Masuk', to: '/persuratan/surat-masuk', show: auth.hasPermission('surat-masuk.view') },
      { label: 'Disposisi', to: '/persuratan/disposisi', show: auth.hasPermission('disposisi.view') || auth.hasPermission('disposisi.receive') },
      { label: 'Template Surat', to: '/persuratan/template-surat', show: auth.hasPermission('surat-keluar.create') },
    ].filter(c => c.show !== false),
  },
  {
    label: 'Agenda Kegiatan',
    to: '/agenda',
    icon: CalendarDaysIcon,
    show: auth.hasPermission('agenda.view'),
  },
  {
    label: 'Pengaturan',
    icon: Cog6ToothIcon,
    show: auth.hasPermission('user.view'),
    children: [
      { label: 'Pengguna', to: '/users' },
      { label: 'Audit Log', to: '/audit-log' },
      { label: 'Integrasi LMS', to: '/settings/lms', show: auth.hasRole('SUPER_ADMIN') || auth.hasRole('ADMIN_AKADEMIK') },
    ].filter(c => c.show !== false),
  },
])

const isActive = (to: string) => route.path === to
const isParentActive = (children: { to: string }[]) =>
  children.some((c) => route.path.startsWith(c.to))
</script>

<template>
  <!-- Mobile overlay -->
  <div v-if="open" class="fixed inset-0 z-20 bg-black/50 lg:hidden" @click="$emit('close')" />

  <aside
    :class="[
      'fixed inset-y-0 left-0 z-30 flex flex-col w-64 bg-white border-r border-gray-200 transition-transform duration-300 lg:static lg:translate-x-0',
      open ? 'translate-x-0' : '-translate-x-full',
    ]"
  >
    <!-- Logo -->
    <div class="flex items-center gap-3 px-4 py-4 border-b border-gray-200 shrink-0">
      <div class="flex items-center justify-center w-9 h-9 rounded-lg overflow-hidden shrink-0"
           :class="institution.logoUrl ? 'bg-transparent' : 'bg-blue-600'">
        <img
          v-if="institution.logoUrl"
          :src="institution.logoUrl"
          :alt="institution.name"
          class="w-full h-full object-contain"
        />
        <span v-else class="text-white font-bold text-sm">{{ institution.name.charAt(0) }}</span>
      </div>
      <div class="min-w-0">
        <p class="text-sm font-semibold text-gray-900 truncate">{{ institution.name }}</p>
        <p class="text-xs text-gray-500 truncate">Al-Jawami Smart Campus</p>
      </div>
    </div>

    <!-- Nav -->
    <nav class="flex-1 overflow-y-auto px-3 py-4 space-y-1">
      <template v-for="item in navigation" :key="item.label">
        <template v-if="item.show">
          <!-- Single link -->
          <RouterLink
            v-if="item.to"
            :to="item.to"
            :class="[
              'flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-colors',
              isActive(item.to)
                ? 'bg-blue-50 text-blue-700'
                : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900',
            ]"
          >
            <component :is="item.icon" class="w-5 h-5 shrink-0" />
            {{ item.label }}
          </RouterLink>

          <!-- Group with children -->
          <details v-else-if="item.children" :open="isParentActive(item.children)" class="group">
            <summary
              class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium text-gray-600 hover:bg-gray-100 hover:text-gray-900 cursor-pointer list-none transition-colors"
            >
              <component :is="item.icon" class="w-5 h-5 shrink-0" />
              <span class="flex-1">{{ item.label }}</span>
              <svg class="w-4 h-4 transition-transform group-open:rotate-90" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
              </svg>
            </summary>
            <div class="mt-1 ml-4 pl-4 border-l border-gray-200 space-y-1">
              <RouterLink
                v-for="child in item.children"
                :key="child.to"
                :to="child.to"
                :class="[
                  'block px-3 py-1.5 rounded-lg text-sm transition-colors',
                  isActive(child.to)
                    ? 'bg-blue-50 text-blue-700 font-medium'
                    : 'text-gray-500 hover:bg-gray-100 hover:text-gray-900',
                ]"
              >
                {{ child.label }}
              </RouterLink>
            </div>
          </details>
        </template>
      </template>
    </nav>

    <!-- User info -->
    <div class="px-4 py-3 border-t border-gray-200 shrink-0">
      <p class="text-sm font-medium text-gray-900 truncate">{{ auth.user?.name }}</p>
      <p class="text-xs text-gray-500 truncate">{{ auth.user?.roles[0] }}</p>
    </div>
  </aside>
</template>
