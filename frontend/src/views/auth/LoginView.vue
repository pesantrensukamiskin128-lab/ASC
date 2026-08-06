<script setup lang="ts">
import { reactive, ref, onMounted, computed } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { useInstitutionStore } from '@/stores/institution'
import { useToast } from 'vue-toastification'
import { CalendarDaysIcon, AcademicCapIcon, BellAlertIcon } from '@heroicons/vue/24/outline'
import api from '@/services/api'

const auth        = useAuthStore()
const institution = useInstitutionStore()
const toast       = useToast()

const form = reactive({ email: '', password: '' })
const showPassword = ref(false)
const calendarEvents = ref<any[]>([])

onMounted(async () => {
  institution.fetch()
  try {
    const { data } = await api.get('/public/academic-calendar')
    calendarEvents.value = data
  } catch { /* silent */ }
})

async function handleLogin() {
  try {
    await auth.login(form)
  } catch (err: any) {
    const msg = err?.response?.data?.message
      || err?.response?.data?.errors?.email?.[0]
      || 'Login gagal.'
    toast.error(msg)
  }
}

const upcomingEvents = computed(() => calendarEvents.value.slice(0, 5))

function formatDate(dateStr: string) {
  if (!dateStr) return ''
  const d = new Date(dateStr)
  return d.toLocaleDateString('id-ID', { day: 'numeric', month: 'short' })
}

function categoryColor(cat: string): string {
  const map: Record<string, string> = {
    Akademik: 'bg-blue-100 text-blue-700',
    UTS: 'bg-amber-100 text-amber-700',
    UAS: 'bg-red-100 text-red-700',
    Libur: 'bg-emerald-100 text-emerald-700',
    KKN: 'bg-violet-100 text-violet-700',
    Wisuda: 'bg-pink-100 text-pink-700',
    Lainnya: 'bg-gray-100 text-gray-600',
  }
  return map[cat] ?? 'bg-gray-100 text-gray-600'
}
</script>

<template>
  <div class="min-h-screen flex flex-col lg:flex-row">

    <!-- LEFT PANEL — Branding & Info -->
    <div class="hidden lg:flex lg:w-[55%] bg-gradient-to-br from-blue-900 via-blue-800 to-indigo-900 relative overflow-hidden">
      <!-- Background pattern -->
      <div class="absolute inset-0 opacity-10">
        <div class="absolute top-0 left-0 w-72 h-72 bg-white rounded-full -translate-x-1/2 -translate-y-1/2"></div>
        <div class="absolute bottom-0 right-0 w-96 h-96 bg-white rounded-full translate-x-1/3 translate-y-1/3"></div>
        <div class="absolute top-1/2 left-1/2 w-64 h-64 bg-white rounded-full -translate-x-1/2 -translate-y-1/2"></div>
      </div>

      <div class="relative z-10 flex flex-col justify-between w-full p-10">
        <!-- Top — App Name -->
        <div>
          <div class="flex items-center gap-3 mb-2">
            <div class="w-12 h-12 rounded-xl overflow-hidden bg-white/10 backdrop-blur-sm flex items-center justify-center border border-white/20">
              <img v-if="institution.logoUrl" :src="institution.logoUrl" alt="Logo" class="w-full h-full object-contain p-1" />
              <AcademicCapIcon v-else class="w-7 h-7 text-white/80" />
            </div>
            <div>
              <h1 class="text-2xl font-bold text-white tracking-tight">Al-Jawami Smart Campus</h1>
              <p class="text-blue-200 text-sm">{{ institution.institution?.name || 'Sistem Informasi Akademik Terpadu' }}</p>
            </div>
          </div>
        </div>

        <!-- Middle — Kalender Akademik -->
        <div class="flex-1 flex flex-col justify-center max-w-md">
          <div class="bg-white/10 backdrop-blur-md rounded-2xl border border-white/20 p-6">
            <div class="flex items-center gap-2 mb-4">
              <CalendarDaysIcon class="w-5 h-5 text-blue-200" />
              <h2 class="text-white font-semibold text-sm">Kalender Akademik</h2>
            </div>

            <div v-if="upcomingEvents.length === 0" class="text-blue-200/70 text-sm text-center py-6">
              Belum ada kegiatan akademik terjadwal.
            </div>

            <div v-else class="space-y-3">
              <div v-for="event in upcomingEvents" :key="event.id"
                class="flex items-start gap-3 group">
                <div class="shrink-0 w-12 text-center">
                  <p class="text-white font-bold text-sm leading-tight">{{ formatDate(event.start_date) }}</p>
                </div>
                <div class="flex-1 min-w-0">
                  <p class="text-white text-sm font-medium truncate">{{ event.title }}</p>
                  <div class="flex items-center gap-2 mt-0.5">
                    <span :class="['px-2 py-0.5 rounded-full text-[10px] font-semibold', categoryColor(event.category)]">
                      {{ event.category }}
                    </span>
                    <span v-if="event.end_date && event.end_date !== event.start_date" class="text-blue-300/70 text-[10px]">
                      s/d {{ formatDate(event.end_date) }}
                    </span>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Info Card -->
          <div class="mt-4 bg-white/5 backdrop-blur-sm rounded-xl border border-white/10 p-4">
            <div class="flex items-start gap-3">
              <BellAlertIcon class="w-5 h-5 text-amber-300 shrink-0 mt-0.5" />
              <div>
                <p class="text-white text-sm font-medium">Informasi Penting</p>
                <p class="text-blue-200/80 text-xs mt-1 leading-relaxed">
                  Gunakan email dan password yang telah diberikan oleh admin untuk masuk ke sistem. Hubungi bagian akademik jika mengalami kendala.
                </p>
              </div>
            </div>
          </div>
        </div>

        <!-- Bottom — Footer -->
        <div class="text-blue-300/60 text-xs">
          © {{ new Date().getFullYear() }} {{ institution.institution?.legal_entity_name || 'Al-Jawami Smart Campus' }}
        </div>
      </div>
    </div>

    <!-- RIGHT PANEL — Login Form -->
    <div class="flex-1 flex items-center justify-center bg-gray-50 px-4 py-10 lg:px-8">
      <div class="w-full max-w-sm">

        <!-- Mobile: Logo & App Name (hanya tampil di mobile) -->
        <div class="lg:hidden text-center mb-8">
          <div class="inline-flex items-center justify-center w-14 h-14 rounded-xl overflow-hidden mb-3 shadow-lg"
               :class="institution.logoUrl ? 'bg-white' : 'bg-blue-600'">
            <img v-if="institution.logoUrl" :src="institution.logoUrl" :alt="institution.name" class="w-full h-full object-contain p-1" />
            <span v-else class="text-white font-bold text-xl">A</span>
          </div>
          <h1 class="text-xl font-bold text-gray-900">Al-Jawami Smart Campus</h1>
          <p class="text-xs text-gray-500 mt-0.5">{{ institution.institution?.name || '' }}</p>
        </div>

        <!-- Desktop: Welcome text -->
        <div class="hidden lg:block mb-8">
          <h2 class="text-2xl font-bold text-gray-900">Assalamu'alaikum! 👋</h2>
          <p class="text-gray-500 text-sm mt-1">Masuk ke akun Anda untuk melanjutkan</p>
        </div>

        <!-- Login Form -->
        <form class="space-y-5" @submit.prevent="handleLogin">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Email</label>
            <div class="relative">
              <input
                v-model="form.email"
                type="email"
                required
                placeholder="nama@jawami.ac.id"
                class="w-full pl-10 pr-4 py-2.5 rounded-xl border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition bg-white"
              />
              <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4.5 h-4.5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" />
              </svg>
            </div>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Password</label>
            <div class="relative">
              <input
                v-model="form.password"
                :type="showPassword ? 'text' : 'password'"
                required
                placeholder="••••••••"
                class="w-full pl-10 pr-10 py-2.5 rounded-xl border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition bg-white"
              />
              <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4.5 h-4.5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" />
              </svg>
              <button type="button" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600" @click="showPassword = !showPassword">
                <svg v-if="!showPassword" class="w-4.5 h-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                  <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
                <svg v-else class="w-4.5 h-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" />
                </svg>
              </button>
            </div>
          </div>

          <button
            type="submit"
            :disabled="auth.loading"
            class="w-full py-2.5 bg-blue-600 hover:bg-blue-700 disabled:bg-blue-400 text-white text-sm font-semibold rounded-xl transition-all duration-200 shadow-lg shadow-blue-500/25 hover:shadow-blue-500/40"
          >
            <span v-if="auth.loading" class="flex items-center justify-center gap-2">
              <svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
              </svg>
              Memproses...
            </span>
            <span v-else>Masuk</span>
          </button>
        </form>

        <!-- Mobile: Kalender mini -->
        <div class="lg:hidden mt-8 bg-white rounded-xl border border-gray-200 p-4" v-if="upcomingEvents.length">
          <div class="flex items-center gap-2 mb-3">
            <CalendarDaysIcon class="w-4 h-4 text-blue-600" />
            <h3 class="text-xs font-semibold text-gray-700 uppercase">Kegiatan Mendatang</h3>
          </div>
          <div class="space-y-2">
            <div v-for="event in upcomingEvents.slice(0, 3)" :key="event.id" class="flex items-center gap-2">
              <span class="text-[10px] font-semibold text-gray-500 w-10 shrink-0">{{ formatDate(event.start_date) }}</span>
              <span class="text-xs text-gray-700 truncate">{{ event.title }}</span>
            </div>
          </div>
        </div>

        <!-- Footer -->
        <p class="text-center text-xs text-gray-400 mt-8">
          © {{ new Date().getFullYear() }} Al-Jawami Smart Campus
        </p>
      </div>
    </div>

  </div>
</template>
