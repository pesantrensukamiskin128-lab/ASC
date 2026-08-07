import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: '/login',
      name: 'login',
      component: () => import('@/views/auth/LoginView.vue'),
      meta: { guest: true },
    },

    // =============================================
    // Verifikasi Dokumen (Publik — tanpa login)
    // =============================================
    {
      path: '/verify/:type/:id',
      name: 'verify-document',
      component: () => import('@/views/VerifyDocumentView.vue'),
      meta: { guest: false },
    },

    // =============================================
    // REPOSITORY PUBLIK (tanpa login)
    // =============================================
    {
      path: '/repository',
      component: () => import('@/layouts/RepositoryLayout.vue'),
      children: [
        {
          path: '',
          name: 'repository',
          component: () => import('@/views/repository/RepositoryView.vue'),
        },
        {
          path: ':source/:id',
          name: 'repository-detail',
          component: () => import('@/views/repository/RepositoryDetailView.vue'),
        },
      ],
    },

    // Presensi Publik (tanpa AppLayout)
    {
      path: '/presensi/:token',
      name: 'event-attend',
      component: () => import('@/views/agenda/EventAttendView.vue'),
    },

    // PMB Public Pages (tanpa AppLayout)
    {
      path: '/pmb',
      component: () => import('@/layouts/PmbLayout.vue'),
      children: [
        {
          path: '',
          name: 'pmb-landing',
          component: () => import('@/views/pmb/public/PmbLandingView.vue'),
        },
        {
          path: 'register',
          name: 'pmb-register',
          component: () => import('@/views/pmb/public/PmbRegisterView.vue'),
        },
        {
          path: 'login',
          name: 'pmb-login',
          component: () => import('@/views/pmb/public/PmbLoginView.vue'),
        },
        {
          path: 'form',
          name: 'pmb-form',
          component: () => import('@/views/pmb/public/PmbFormView.vue'),
          meta: { pmbAuth: true },
        },
        {
          path: 'status',
          name: 'pmb-status',
          component: () => import('@/views/pmb/public/PmbStatusView.vue'),
          meta: { pmbAuth: true },
        },
      ],
    },
    {
      path: '/',
      component: () => import('@/layouts/AppLayout.vue'),
      meta: { requiresAuth: true },
      children: [
        { path: '', redirect: '/dashboard' },
        {
          path: 'dashboard',
          name: 'dashboard',
          component: () => import('@/views/DashboardView.vue'),
        },

        // Master Data
        {
          path: 'master-data/institutions',
          name: 'institutions',
          component: () => import('@/views/master-data/InstitutionView.vue'),
          meta: { permission: 'master-data.view' },
        },
        {
          path: 'master-data/faculties',
          name: 'faculties',
          component: () => import('@/views/master-data/FacultyView.vue'),
          meta: { permission: 'master-data.view' },
        },
        {
          path: 'master-data/study-programs',
          name: 'study-programs',
          component: () => import('@/views/master-data/StudyProgramView.vue'),
          meta: { permission: 'master-data.view' },
        },
        {
          path: 'master-data/concentrations',
          name: 'concentrations',
          component: () => import('@/views/master-data/ConcentrationView.vue'),
          meta: { permission: 'master-data.view' },
        },
        {
          path: 'master-data/academic-years',
          name: 'academic-years',
          component: () => import('@/views/master-data/AcademicYearView.vue'),
          meta: { permission: 'master-data.view' },
        },
        {
          path: 'master-data/semesters',
          name: 'semesters',
          component: () => import('@/views/master-data/SemesterView.vue'),
          meta: { permission: 'master-data.view' },
        },
        {
          path: 'master-data/academic-calendar',
          name: 'academic-calendar',
          component: () => import('@/views/master-data/AcademicCalendarView.vue'),
        },
        {
          path: 'master-data/buildings',
          name: 'buildings',
          component: () => import('@/views/master-data/BuildingView.vue'),
          meta: { permission: 'master-data.view' },
        },
        {
          path: 'master-data/rooms',
          name: 'rooms',
          component: () => import('@/views/master-data/RoomView.vue'),
          meta: { permission: 'master-data.view' },
        },
        {
          path: 'master-data/courses',
          name: 'courses',
          component: () => import('@/views/master-data/CourseView.vue'),
          meta: { permission: 'master-data.view' },
        },

        // SDM
        {
          path: 'sdm/students',
          name: 'students',
          component: () => import('@/views/sdm/StudentView.vue'),
          meta: { permission: 'mahasiswa.view' },
        },
        {
          path: 'sdm/students/:id',
          name: 'student-detail',
          component: () => import('@/views/sdm/StudentDetailView.vue'),
          meta: { permission: 'mahasiswa.view' },
        },
        {
          path: 'sdm/lecturers',
          name: 'lecturers',
          component: () => import('@/views/sdm/LecturerView.vue'),
          meta: { permission: 'master-data.view' },
        },
        {
          path: 'sdm/lecturers/:id',
          name: 'lecturer-detail',
          component: () => import('@/views/sdm/LecturerDetailView.vue'),
          meta: { permission: 'master-data.view' },
        },
        {
          path: 'sdm/staff',
          name: 'staff',
          component: () => import('@/views/sdm/StaffView.vue'),
          meta: { permission: 'master-data.view' },
        },

        // PMB - Penerimaan Mahasiswa Baru
        {
          path: 'pmb',
          name: 'pmb-dashboard',
          component: () => import('@/views/pmb/PmbDashboardView.vue'),
          meta: { permission: 'pmb.view' },
        },
        {
          path: 'pmb/periods',
          name: 'pmb-periods',
          component: () => import('@/views/pmb/PmbPeriodView.vue'),
          meta: { permission: 'pmb.view' },
        },
        {
          path: 'pmb/paths',
          name: 'pmb-paths',
          component: () => import('@/views/pmb/PmbPathView.vue'),
          meta: { permission: 'pmb.view' },
        },
        {
          path: 'pmb/exam-types',
          name: 'pmb-exam-types',
          component: () => import('@/views/pmb/PmbExamTypeView.vue'),
          meta: { permission: 'pmb.view' },
        },
        {
          path: 'pmb/registrants',
          name: 'pmb-registrants',
          component: () => import('@/views/pmb/PmbRegistrantListView.vue'),
          meta: { permission: 'pmb.view' },
        },
        {
          path: 'pmb/registrants/:id',
          name: 'pmb-registrant-detail',
          component: () => import('@/views/pmb/PmbRegistrantDetailView.vue'),
          meta: { permission: 'pmb.view' },
        },

        // Akademik - Kalender
        {
          path: 'akademik/kalender',
          name: 'kalender-akademik',
          component: () => import('@/views/akademik/AcademicCalendarView.vue'),
          meta: { permission: 'krs.view' },
        },

        // Akademik - KRS
        {
          path: 'akademik/kelas',
          name: 'kelas',
          component: () => import('@/views/akademik/ClassOfferingView.vue'),
          meta: { permission: 'jadwal.view' },
        },
        {
          path: 'perkuliahan/:id',
          name: 'perkuliahan-detail',
          component: () => import('@/views/perkuliahan/ClassDetailView.vue'),
          meta: { permission: 'presensi.view' },
        },
        {
          path: 'perkuliahan/:id/presensi/:journalId',
          name: 'perkuliahan-presensi',
          component: () => import('@/views/perkuliahan/AttendanceView.vue'),
          meta: { permission: 'presensi.view' },
        },
        {
          path: 'ujian/:id',
          name: 'ujian-detail',
          component: () => import('@/views/perkuliahan/ExamDetailView.vue'),
          meta: { permission: 'presensi.view' },
        },
        {
          path: 'ujian/:id/take',
          name: 'ujian-take',
          component: () => import('@/views/perkuliahan/ExamTakeView.vue'),
        },
        {
          path: 'ujian/:id/results',
          name: 'ujian-results',
          component: () => import('@/views/perkuliahan/ExamResultView.vue'),
          meta: { permission: 'presensi.view' },
        },
        // Penilaian
        {
          path: 'penilaian/nilai',
          name: 'input-nilai',
          component: () => import('@/views/penilaian/InputNilaiView.vue'),
          meta: { permission: 'nilai.view' },
        },
        {
          path: 'penilaian/khs',
          name: 'khs-transkrip',
          component: () => import('@/views/penilaian/KhsTranscriptView.vue'),
          meta: { permission: 'nilai.view' },
        },
        {
          path: 'bank-soal',
          name: 'bank-soal',
          component: () => import('@/views/perkuliahan/QuestionBankView.vue'),
          meta: { permission: 'nilai.view' },
        },
        {
          path: 'bank-soal/:id',
          name: 'bank-soal-detail',
          component: () => import('@/views/perkuliahan/QuestionBankDetailView.vue'),
          meta: { permission: 'nilai.view' },
        },
        {
          path: 'notifikasi',
          name: 'notifikasi',
          component: () => import('@/views/NotificationView.vue'),
        },
        {
          path: 'akademik/krs',
          name: 'krs',
          component: () => import('@/views/akademik/KrsListView.vue'),
        },

        // Cuti Akademik
        {
          path: 'akademik/cuti',
          name: 'academic-leave',
          component: () => import('@/views/akademik/AcademicLeaveView.vue'),
          meta: { permission: 'krs.view' },
        },
        {
          path: 'akademik/cuti/:id',
          name: 'academic-leave-detail',
          component: () => import('@/views/akademik/AcademicLeaveDetailView.vue'),
          meta: { permission: 'krs.view' },
        },

        // Transfer Nilai
        {
          path: 'akademik/transfer',
          name: 'transfer-credit',
          component: () => import('@/views/akademik/TransferCreditView.vue'),
          meta: { permission: 'krs.view' },
        },
        {
          path: 'akademik/transfer/:id',
          name: 'transfer-credit-detail',
          component: () => import('@/views/akademik/TransferCreditDetailView.vue'),
          meta: { permission: 'krs.view' },
        },

        // Keuangan Mahasiswa
        {
          path: 'keuangan',
          name: 'keuangan-dashboard',
          component: () => import('@/views/keuangan/FinanceDashboardView.vue'),
          meta: { permission: 'keuangan.view' },
        },
        {
          path: 'keuangan/saya',
          name: 'keuangan-saya',
          component: () => import('@/views/keuangan/StudentFinanceView.vue'),
        },
        {
          path: 'akademik/krs-saya',
          name: 'krs-saya',
          component: () => import('@/views/akademik/StudentKrsView.vue'),
        },
        {
          path: 'akademik-saya/rps',
          name: 'akademik-saya-rps',
          component: () => import('@/views/akademik/StudentAcademicView.vue'),
          props: { section: 'rps' },
        },
        {
          path: 'akademik-saya/kelas',
          name: 'akademik-saya-kelas',
          component: () => import('@/views/akademik/StudentAcademicView.vue'),
          props: { section: 'kelas' },
        },
        {
          path: 'akademik-saya/khs',
          name: 'akademik-saya-khs',
          component: () => import('@/views/akademik/StudentAcademicView.vue'),
          props: { section: 'khs' },
        },
        {
          path: 'akademik-saya/praktikum',
          name: 'akademik-saya-praktikum',
          component: () => import('@/views/akademik/StudentAcademicView.vue'),
          props: { section: 'praktikum' },
        },
        {
          path: 'akademik-saya/skripsi',
          name: 'akademik-saya-skripsi',
          component: () => import('@/views/akademik/StudentAcademicView.vue'),
          props: { section: 'skripsi' },
        },
        {
          path: 'akademik-saya/cuti',
          name: 'akademik-saya-cuti',
          component: () => import('@/views/akademik/StudentAcademicView.vue'),
          props: { section: 'cuti' },
        },
        {
          path: 'akademik-saya/wisuda',
          name: 'akademik-saya-wisuda',
          component: () => import('@/views/akademik/StudentAcademicView.vue'),
          props: { section: 'wisuda' },
        },
        {
          path: 'keuangan/jenis-tagihan',
          name: 'keuangan-fee-types',
          component: () => import('@/views/keuangan/FeeTypeView.vue'),
          meta: { permission: 'keuangan.view' },
        },
        {
          path: 'keuangan/tagihan',
          name: 'keuangan-invoices',
          component: () => import('@/views/keuangan/InvoiceView.vue'),
          meta: { permission: 'keuangan.view' },
        },
        {
          path: 'keuangan/tagihan/create',
          name: 'keuangan-invoice-create',
          component: () => import('@/views/keuangan/InvoiceCreateView.vue'),
          meta: { permission: 'keuangan.view' },
        },
        {
          path: 'keuangan/tagihan/generate',
          name: 'keuangan-invoice-generate',
          component: () => import('@/views/keuangan/InvoiceGenerateView.vue'),
          meta: { permission: 'keuangan.view' },
        },
        {
          path: 'keuangan/tagihan/:id',
          name: 'keuangan-invoice-detail',
          component: () => import('@/views/keuangan/InvoiceDetailView.vue'),
          meta: { permission: 'keuangan.view' },
        },
        {
          path: 'keuangan/pembayaran',
          name: 'keuangan-payments',
          component: () => import('@/views/keuangan/PaymentView.vue'),
          meta: { permission: 'keuangan.view' },
        },
        {
          path: 'keuangan/beasiswa',
          name: 'keuangan-scholarships',
          component: () => import('@/views/keuangan/ScholarshipView.vue'),
          meta: { permission: 'keuangan.view' },
        },

        // Alumni
        {
          path: 'alumni',
          name: 'alumni-dashboard',
          component: () => import('@/views/alumni/AlumniDashboardView.vue'),
          meta: { permission: 'alumni.view' },
        },
        {
          path: 'alumni/data',
          name: 'alumni-list',
          component: () => import('@/views/alumni/AlumniListView.vue'),
          meta: { permission: 'alumni.view' },
        },
        {
          path: 'alumni/data/:id',
          name: 'alumni-detail',
          component: () => import('@/views/alumni/AlumniDetailView.vue'),
          meta: { permission: 'alumni.view' },
        },
        {
          path: 'alumni/tracer-study',
          name: 'alumni-tracer-study',
          component: () => import('@/views/alumni/TracerStudyView.vue'),
          meta: { permission: 'alumni.view' },
        },

        // Bimbingan Akademik
        {
          path: 'bimbingan',
          name: 'bimbingan-dashboard',
          component: () => import('@/views/bimbingan/GuidanceDashboardView.vue'),
          meta: { permission: 'bimbingan.view' },
        },
        {
          path: 'bimbingan/sessions',
          name: 'bimbingan-sessions',
          component: () => import('@/views/bimbingan/GuidanceSessionView.vue'),
          meta: { permission: 'bimbingan.view' },
        },
        {
          path: 'bimbingan/sessions/:id',
          name: 'bimbingan-session-detail',
          component: () => import('@/views/bimbingan/GuidanceSessionDetailView.vue'),
          meta: { permission: 'bimbingan.view' },
        },
        {
          path: 'bimbingan/mahasiswa',
          name: 'bimbingan-students',
          component: () => import('@/views/bimbingan/GuidanceStudentsView.vue'),
          meta: { permission: 'bimbingan.view' },
        },
        {
          path: 'bimbingan/catatan',
          name: 'bimbingan-notes',
          component: () => import('@/views/bimbingan/AcademicNoteView.vue'),
          meta: { permission: 'bimbingan.view' },
        },
        {
          path: 'bimbingan/warnings',
          name: 'bimbingan-warnings',
          component: () => import('@/views/bimbingan/AcademicWarningView.vue'),
          meta: { permission: 'bimbingan.view' },
        },

        // Praktikum / KKN / PPL / Magang / PKL
        {
          path: 'praktikum',
          name: 'praktikum-programs',
          component: () => import('@/views/praktikum/PracticalProgramView.vue'),
          meta: { permission: 'kkn.view' },
        },
        {
          path: 'praktikum/:id',
          name: 'praktikum-detail',
          component: () => import('@/views/praktikum/PracticalDetailView.vue'),
          meta: { permission: 'kkn.view' },
        },
        {
          path: 'praktikum/peserta/:id',
          name: 'praktikum-participant',
          component: () => import('@/views/praktikum/PracticalParticipantView.vue'),
          meta: { permission: 'kkn.view' },
        },

        // Karya Dosen
        {
          path: 'karya-dosen',
          name: 'karya-dosen',
          component: () => import('@/views/karya/LecturerWorkView.vue'),
          meta: { permission: 'skripsi.view' },
        },
        {
          path: 'karya-dosen/buat',
          name: 'karya-dosen-buat',
          component: () => import('@/views/karya/LecturerWorkFormView.vue'),
          meta: { permission: 'skripsi.create' },
        },
        {
          path: 'karya-dosen/:id',
          name: 'karya-dosen-detail',
          component: () => import('@/views/karya/LecturerWorkDetailView.vue'),
          meta: { permission: 'skripsi.view' },
        },
        {
          path: 'karya-dosen/:id/edit',
          name: 'karya-dosen-edit',
          component: () => import('@/views/karya/LecturerWorkFormView.vue'),
          meta: { permission: 'skripsi.create' },
        },

        // Penelitian & Pengabdian
        {
          path: 'penelitian',
          name: 'penelitian',
          component: () => import('@/views/penelitian/PenelitianListView.vue'),
          meta: { permission: 'skripsi.view' },
        },
        {
          path: 'penelitian/buat',
          name: 'penelitian-buat',
          component: () => import('@/views/penelitian/PenelitianFormView.vue'),
          meta: { permission: 'skripsi.create' },
        },
        {
          path: 'penelitian/periode',
          name: 'penelitian-periode',
          component: () => import('@/views/penelitian/PenelitianPeriodView.vue'),
          meta: { permission: 'skripsi.view' },
        },
        {
          path: 'penelitian/:id',
          name: 'penelitian-detail',
          component: () => import('@/views/penelitian/PenelitianDetailView.vue'),
          meta: { permission: 'skripsi.view' },
        },
        {
          path: 'penelitian/:id/edit',
          name: 'penelitian-edit',
          component: () => import('@/views/penelitian/PenelitianFormView.vue'),
          meta: { permission: 'skripsi.create' },
        },

        // Skripsi / Tugas Akhir
        {
          path: 'skripsi',
          name: 'skripsi-list',
          component: () => import('@/views/skripsi/ThesisListView.vue'),
          meta: { permission: 'skripsi.view' },
        },
        {
          path: 'skripsi/:id',
          name: 'skripsi-detail',
          component: () => import('@/views/skripsi/ThesisDetailView.vue'),
          meta: { permission: 'skripsi.view' },
        },

        // Wisuda
        {
          path: 'wisuda',
          name: 'wisuda',
          component: () => import('@/views/wisuda/GraduationView.vue'),
          meta: { permission: 'krs.view' },
        },
        {
          path: 'wisuda/:id',
          name: 'wisuda-detail',
          component: () => import('@/views/wisuda/GraduationDetailView.vue'),
          meta: { permission: 'krs.view' },
        },

        {
          path: 'akademik/krs/:id',
          name: 'krs-detail',
          component: () => import('@/views/akademik/KrsDetailView.vue'),
        },
        {
          path: 'kurikulum',
          name: 'kurikulum',
          component: () => import('@/views/kurikulum/CurriculumView.vue'),
          meta: { permission: 'kurikulum.view' },
        },
        {
          path: 'kurikulum/:id',
          name: 'kurikulum-detail',
          component: () => import('@/views/kurikulum/CurriculumDetailView.vue'),
          meta: { permission: 'kurikulum.view' },
        },
        {
          path: 'rps',
          name: 'rps',
          component: () => import('@/views/kurikulum/RpsView.vue'),
          meta: { permission: 'rps.view' },
        },
        {
          path: 'rps/:id',
          name: 'rps-detail',
          component: () => import('@/views/kurikulum/RpsDetailView.vue'),
          meta: { permission: 'rps.view' },
        },

        // Laporan & Statistik
        {
          path: 'laporan',
          name: 'laporan',
          component: () => import('@/views/laporan/LaporanView.vue'),
          meta: { permission: 'dashboard.analytics' },
        },
        {
          path: 'laporan/mahasiswa',
          name: 'laporan-mahasiswa',
          component: () => import('@/views/laporan/LaporanMahasiswaView.vue'),
          meta: { permission: 'dashboard.analytics' },
        },
        {
          path: 'laporan/akademik',
          name: 'laporan-akademik',
          component: () => import('@/views/laporan/LaporanAkademikView.vue'),
          meta: { permission: 'dashboard.analytics' },
        },
        {
          path: 'laporan/keuangan',
          name: 'laporan-keuangan',
          component: () => import('@/views/laporan/LaporanKeuanganView.vue'),
          meta: { permission: 'dashboard.analytics' },
        },
        {
          path: 'laporan/sdm',
          name: 'laporan-sdm',
          component: () => import('@/views/laporan/LaporanSdmView.vue'),
          meta: { permission: 'dashboard.analytics' },
        },

        // User Management
        {
          path: 'users',
          name: 'users',
          component: () => import('@/views/UserManagementView.vue'),
          meta: { permission: 'user.view' },
        },

        // Audit Log
        {
          path: 'audit-log',
          name: 'audit-log',
          component: () => import('@/views/AuditLogView.vue'),
          meta: { permission: 'user.view' },
        },

        // Profil
        {
          path: 'profile',
          name: 'profile',
          component: () => import('@/views/ProfileView.vue'),
        },

        // =========================================================
        // PERSURATAN
        // =========================================================
        {
          path: 'persuratan/surat-keluar',
          name: 'outgoing-letters',
          component: () => import('@/views/persuratan/OutgoingLetterListView.vue'),
          meta: { permission: 'surat-keluar.view' },
        },
        {
          path: 'persuratan/surat-keluar/buat',
          name: 'outgoing-letter-create',
          component: () => import('@/views/persuratan/OutgoingLetterFormView.vue'),
          meta: { permission: 'surat-keluar.create' },
        },
        {
          path: 'persuratan/surat-keluar/:id',
          name: 'outgoing-letter-detail',
          component: () => import('@/views/persuratan/OutgoingLetterDetailView.vue'),
          meta: { permission: 'surat-keluar.view' },
        },
        {
          path: 'persuratan/surat-keluar/:id/edit',
          name: 'outgoing-letter-edit',
          component: () => import('@/views/persuratan/OutgoingLetterFormView.vue'),
          meta: { permission: 'surat-keluar.edit' },
        },
        {
          path: 'persuratan/surat-masuk',
          name: 'incoming-letters',
          component: () => import('@/views/persuratan/IncomingLetterView.vue'),
          meta: { permission: 'surat-masuk.view' },
        },
        {
          path: 'persuratan/disposisi',
          name: 'dispositions',
          component: () => import('@/views/persuratan/DispositionView.vue'),
          meta: { permission: 'disposisi.view' },
        },
        {
          path: 'persuratan/template-surat',
          name: 'letter-templates',
          component: () => import('@/views/persuratan/LetterTemplateView.vue'),
          meta: { permission: 'surat-keluar.create' },
        },

        // =========================================================
        // AGENDA KEGIATAN
        // =========================================================
        {
          path: 'agenda',
          name: 'events',
          component: () => import('@/views/agenda/EventListView.vue'),
          meta: { permission: 'agenda.view' },
        },
        {
          path: 'agenda/buat',
          name: 'event-create',
          component: () => import('@/views/agenda/EventListView.vue'),
          meta: { permission: 'agenda.create' },
        },
        {
          path: 'agenda/:id',
          name: 'event-detail',
          component: () => import('@/views/agenda/EventDetailView.vue'),
          meta: { permission: 'agenda.view' },
        },

        { path: 'forbidden', name: 'forbidden', component: () => import('@/views/ForbiddenView.vue') },
      ],
    },
    {
      path: '/:pathMatch(.*)*',
      name: 'not-found',
      component: () => import('@/views/NotFoundView.vue'),
    },
  ],
})

router.beforeEach(async (to) => {
  const auth = useAuthStore()

  if (to.meta.guest && auth.isAuthenticated) return '/dashboard'

  // PMB pages yang butuh login
  if (to.meta.pmbAuth && !auth.token) return '/pmb/login'

  if (to.meta.requiresAuth || to.meta.permission) {
    if (!auth.token) return '/login'
    if (!auth.user) await auth.fetchMe()

    // SUPER_ADMIN bypass semua permission check
    if (to.meta.permission && !auth.hasRole('SUPER_ADMIN') && !auth.hasPermission(to.meta.permission as string)) {
      return '/forbidden'
    }
  }
})

export default router

