<script setup lang="ts">
import { reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useToast } from 'vue-toastification'
import api from '@/services/api'

const router = useRouter()
const auth   = useAuthStore()
const toast  = useToast()
const saving = ref(false)

const form = reactive({
  name:                  '',
  email:                 '',
  password:              '',
  password_confirmation: '',
})

async function handleRegister() {
  if (form.password !== form.password_confirmation) {
    toast.error('Konfirmasi password tidak cocok.')
    return
  }
  saving.value = true
  try {
    const { data } = await api.post('/pmb/register', form)
    // Auto login setelah registrasi
    auth.token = data.access_token
    auth.user  = data.user
    localStorage.setItem('auth_token', data.access_token)
    toast.success('Registrasi berhasil! Silakan lengkapi formulir pendaftaran.')
    await router.push('/pmb/form')
  } catch (err: any) {
    const msg = err?.response?.data?.errors?.email?.[0]
      || err?.response?.data?.message
      || 'Registrasi gagal.'
    toast.error(msg)
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <div class="max-w-md mx-auto">
    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-8">
      <div class="text-center mb-6">
        <h1 class="text-xl font-bold text-gray-900">Buat Akun Pendaftaran</h1>
        <p class="text-sm text-gray-500 mt-1">Daftarkan akun untuk memulai pendaftaran mahasiswa baru</p>
      </div>

      <form class="space-y-4" @submit.prevent="handleRegister">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1.5">Nama Lengkap <span class="text-red-500">*</span></label>
          <input v-model="form.name" required type="text" placeholder="Nama sesuai ijazah"
            class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1.5">Email <span class="text-red-500">*</span></label>
          <input v-model="form.email" required type="email" placeholder="email@contoh.com"
            class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1.5">Password <span class="text-red-500">*</span></label>
          <input v-model="form.password" required type="password" placeholder="Min. 8 karakter"
            class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1.5">Konfirmasi Password <span class="text-red-500">*</span></label>
          <input v-model="form.password_confirmation" required type="password" placeholder="Ulangi password"
            class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
        </div>

        <button type="submit" :disabled="saving"
          class="w-full py-2.5 bg-blue-600 hover:bg-blue-700 disabled:bg-blue-400 text-white font-semibold rounded-lg text-sm transition-colors">
          {{ saving ? 'Mendaftar...' : 'Buat Akun & Lanjutkan' }}
        </button>
      </form>

      <p class="text-center text-sm text-gray-500 mt-5">
        Sudah punya akun?
        <RouterLink to="/pmb/login" class="text-blue-600 hover:underline font-medium">Masuk di sini</RouterLink>
      </p>
    </div>
  </div>
</template>
