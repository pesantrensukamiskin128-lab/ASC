<script setup lang="ts">
import { reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useToast } from 'vue-toastification'
import AuthService from '@/services/auth.service'
import api from '@/services/api'

const router = useRouter()
const auth   = useAuthStore()
const toast  = useToast()
const saving = ref(false)

const form = reactive({ email: '', password: '' })

async function handleLogin() {
  saving.value = true
  try {
    const response = await AuthService.login(form)
    auth.token = response.access_token
    auth.user  = response.user
    localStorage.setItem('auth_token', response.access_token)

    // Cek apakah sudah punya pendaftaran
    try {
      const { data } = await api.get('/pmb/my/registration')
      if (!data || !data.id || data.status === 'DRAFT') {
        await router.push('/pmb/form')
      } else {
        await router.push('/pmb/status')
      }
    } catch {
      await router.push('/pmb/form')
    }
  } catch (err: any) {
    const msg = err?.response?.data?.message
      || err?.response?.data?.errors?.email?.[0]
      || 'Login gagal.'
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
        <h1 class="text-xl font-bold text-gray-900">Masuk Pendaftaran</h1>
        <p class="text-sm text-gray-500 mt-1">Login dengan akun pendaftaran Anda</p>
      </div>

      <form class="space-y-4" @submit.prevent="handleLogin">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1.5">Email</label>
          <input v-model="form.email" required type="email" placeholder="email@contoh.com"
            class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1.5">Password</label>
          <input v-model="form.password" required type="password" placeholder="••••••••"
            class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
        </div>

        <button type="submit" :disabled="saving"
          class="w-full py-2.5 bg-blue-600 hover:bg-blue-700 disabled:bg-blue-400 text-white font-semibold rounded-lg text-sm transition-colors">
          {{ saving ? 'Memproses...' : 'Masuk' }}
        </button>
      </form>

      <p class="text-center text-sm text-gray-500 mt-5">
        Belum punya akun?
        <RouterLink to="/pmb/register" class="text-blue-600 hover:underline font-medium">Daftar di sini</RouterLink>
      </p>
    </div>
  </div>
</template>
