<script setup lang="ts">
import { reactive, ref, watch } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { useToast } from 'vue-toastification'
import { extractErrorMessage } from '@/composables/useCrud'
import {
  UserCircleIcon,
  KeyIcon,
  CheckCircleIcon,
} from '@heroicons/vue/24/outline'

const auth  = useAuthStore()
const toast = useToast()

// --- Tab aktif ---
const activeTab = ref<'info' | 'password'>('info')

// --- Form info ---
const infoForm = reactive({
  name:     auth.user?.name     ?? '',
  email:    auth.user?.email    ?? '',
  username: auth.user?.username ?? '',
})
const savingInfo = ref(false)

// Sync form jika user di-load ulang
watch(() => auth.user, (u) => {
  if (!u) return
  infoForm.name     = u.name
  infoForm.email    = u.email
  infoForm.username = u.username
}, { immediate: true })

async function saveInfo() {
  savingInfo.value = true
  try {
    const res = await auth.updateProfile({
      name:     infoForm.name,
      email:    infoForm.email,
      username: infoForm.username || undefined,
    })
    toast.success(res.message)
  } catch (err: any) {
    toast.error(extractErrorMessage(err))
  } finally {
    savingInfo.value = false
  }
}

// --- Form password ---
const pwForm = reactive({
  current_password:       '',
  new_password:           '',
  new_password_confirmation: '',
})
const savingPw  = ref(false)
const pwStrength = ref(0)

function checkStrength(val: string) {
  let score = 0
  if (val.length >= 8)              score++
  if (/[A-Z]/.test(val))           score++
  if (/[0-9]/.test(val))           score++
  if (/[^A-Za-z0-9]/.test(val))    score++
  pwStrength.value = score
}

const strengthLabel = ['', 'Lemah', 'Cukup', 'Baik', 'Kuat']
const strengthColor = ['', 'bg-red-500', 'bg-yellow-400', 'bg-blue-500', 'bg-green-500']

async function savePassword() {
  if (pwForm.new_password !== pwForm.new_password_confirmation) {
    toast.error('Konfirmasi password tidak cocok.')
    return
  }
  savingPw.value = true
  try {
    const res = await auth.updateProfile({
      current_password:           pwForm.current_password,
      new_password:               pwForm.new_password,
      new_password_confirmation:  pwForm.new_password_confirmation,
    })
    toast.success(res.message)
    pwForm.current_password       = ''
    pwForm.new_password           = ''
    pwForm.new_password_confirmation = ''
    pwStrength.value = 0
  } catch (err: any) {
    toast.error(extractErrorMessage(err))
  } finally {
    savingPw.value = false
  }
}

function formatDate(d: string | null | undefined) {
  if (!d) return '-'
  return new Date(d).toLocaleString('id-ID', {
    day: '2-digit', month: 'long', year: 'numeric',
    hour: '2-digit', minute: '2-digit',
  })
}
</script>

<template>
  <div class="max-w-2xl mx-auto space-y-6">

    <!-- Header -->
    <div>
      <h1 class="text-xl font-bold text-gray-900">Profil Saya</h1>
      <p class="text-sm text-gray-500 mt-0.5">Kelola informasi akun dan keamanan Anda</p>
    </div>

    <!-- Avatar card -->
    <div class="bg-white rounded-xl border border-gray-200 p-5 flex items-center gap-4">
      <div class="w-16 h-16 rounded-full bg-blue-600 flex items-center justify-center shrink-0 text-white text-2xl font-bold select-none">
        {{ auth.user?.name?.charAt(0).toUpperCase() }}
      </div>
      <div class="min-w-0">
        <p class="text-base font-semibold text-gray-900 truncate">{{ auth.user?.name }}</p>
        <p class="text-sm text-gray-500 truncate">{{ auth.user?.email }}</p>
        <div class="flex flex-wrap gap-1 mt-1.5">
          <span
            v-for="role in auth.user?.roles"
            :key="role"
            class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-700"
          >
            {{ role }}
          </span>
        </div>
      </div>
      <div class="ml-auto text-right shrink-0 hidden sm:block">
        <p class="text-xs text-gray-400">Login terakhir</p>
        <p class="text-xs text-gray-600 font-medium">{{ formatDate(auth.user?.last_login_at) }}</p>
        <span
          class="inline-flex mt-1 px-2 py-0.5 rounded-full text-xs font-medium"
          :class="auth.user?.is_active ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-600'"
        >
          {{ auth.user?.is_active ? 'Aktif' : 'Nonaktif' }}
        </span>
      </div>
    </div>

    <!-- Tabs -->
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">

      <!-- Tab nav -->
      <div class="flex border-b border-gray-200">
        <button
          :class="[
            'flex items-center gap-2 px-5 py-3 text-sm font-medium transition-colors border-b-2 -mb-px',
            activeTab === 'info'
              ? 'border-blue-600 text-blue-600'
              : 'border-transparent text-gray-500 hover:text-gray-700',
          ]"
          @click="activeTab = 'info'"
        >
          <UserCircleIcon class="w-4 h-4" />
          Informasi Akun
        </button>
        <button
          :class="[
            'flex items-center gap-2 px-5 py-3 text-sm font-medium transition-colors border-b-2 -mb-px',
            activeTab === 'password'
              ? 'border-blue-600 text-blue-600'
              : 'border-transparent text-gray-500 hover:text-gray-700',
          ]"
          @click="activeTab = 'password'"
        >
          <KeyIcon class="w-4 h-4" />
          Ubah Password
        </button>
      </div>

      <!-- Tab: Info -->
      <div v-if="activeTab === 'info'" class="p-6">
        <form class="space-y-4" @submit.prevent="saveInfo">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">
              Nama Lengkap <span class="text-red-500">*</span>
            </label>
            <input
              v-model="infoForm.name"
              required
              type="text"
              class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition"
            />
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">
              Email <span class="text-red-500">*</span>
            </label>
            <input
              v-model="infoForm.email"
              required
              type="email"
              class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition"
            />
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Username</label>
            <input
              v-model="infoForm.username"
              type="text"
              placeholder="Opsional"
              class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition"
            />
            <p class="text-xs text-gray-400 mt-1">
              Digunakan untuk login selain email. Biarkan kosong jika tidak dipakai.
            </p>
          </div>

          <div class="flex justify-end pt-2">
            <button
              type="submit"
              :disabled="savingInfo"
              class="flex items-center gap-2 px-5 py-2.5 bg-blue-600 hover:bg-blue-700 disabled:bg-blue-400 text-white text-sm font-semibold rounded-lg transition-colors"
            >
              <CheckCircleIcon class="w-4 h-4" />
              {{ savingInfo ? 'Menyimpan...' : 'Simpan Perubahan' }}
            </button>
          </div>
        </form>
      </div>

      <!-- Tab: Password -->
      <div v-if="activeTab === 'password'" class="p-6">
        <form class="space-y-4" @submit.prevent="savePassword">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">
              Password Saat Ini <span class="text-red-500">*</span>
            </label>
            <input
              v-model="pwForm.current_password"
              required
              type="password"
              autocomplete="current-password"
              placeholder="••••••••"
              class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition"
            />
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">
              Password Baru <span class="text-red-500">*</span>
            </label>
            <input
              v-model="pwForm.new_password"
              required
              type="password"
              autocomplete="new-password"
              placeholder="Min. 8 karakter"
              class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition"
              @input="checkStrength(pwForm.new_password)"
            />
            <!-- Password strength bar -->
            <div v-if="pwForm.new_password" class="mt-2 space-y-1">
              <div class="flex gap-1">
                <div
                  v-for="i in 4"
                  :key="i"
                  class="h-1 flex-1 rounded-full transition-all"
                  :class="i <= pwStrength ? strengthColor[pwStrength] : 'bg-gray-200'"
                />
              </div>
              <p class="text-xs" :class="pwStrength >= 3 ? 'text-green-600' : 'text-gray-400'">
                Kekuatan: {{ strengthLabel[pwStrength] || '-' }}
              </p>
            </div>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">
              Konfirmasi Password Baru <span class="text-red-500">*</span>
            </label>
            <input
              v-model="pwForm.new_password_confirmation"
              required
              type="password"
              autocomplete="new-password"
              placeholder="Ulangi password baru"
              class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition"
              :class="pwForm.new_password_confirmation && pwForm.new_password !== pwForm.new_password_confirmation
                ? 'border-red-400 focus:ring-red-400'
                : ''"
            />
            <p
              v-if="pwForm.new_password_confirmation && pwForm.new_password !== pwForm.new_password_confirmation"
              class="text-xs text-red-500 mt-1"
            >
              Password tidak cocok.
            </p>
          </div>

          <div class="pt-1 bg-gray-50 rounded-lg p-3 text-xs text-gray-500 space-y-1">
            <p class="font-medium text-gray-600">Tips password aman:</p>
            <ul class="list-disc list-inside space-y-0.5">
              <li>Minimal 8 karakter</li>
              <li>Kombinasi huruf besar & kecil</li>
              <li>Mengandung angka dan simbol</li>
            </ul>
          </div>

          <div class="flex justify-end pt-2">
            <button
              type="submit"
              :disabled="savingPw || (!!pwForm.new_password_confirmation && pwForm.new_password !== pwForm.new_password_confirmation)"
              class="flex items-center gap-2 px-5 py-2.5 bg-blue-600 hover:bg-blue-700 disabled:bg-blue-400 text-white text-sm font-semibold rounded-lg transition-colors"
            >
              <KeyIcon class="w-4 h-4" />
              {{ savingPw ? 'Menyimpan...' : 'Ubah Password' }}
            </button>
          </div>
        </form>
      </div>

    </div>
  </div>
</template>
