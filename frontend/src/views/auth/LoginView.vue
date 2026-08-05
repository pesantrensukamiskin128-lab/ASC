<script setup lang="ts">
import { reactive, onMounted } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { useInstitutionStore } from '@/stores/institution'
import { useToast } from 'vue-toastification'

const auth        = useAuthStore()
const institution = useInstitutionStore()
const toast       = useToast()

const form = reactive({ email: '', password: '' })

// Fetch data institusi untuk tampilkan logo di halaman login
onMounted(() => institution.fetch())

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
</script>

<template>
  <div class="min-h-screen flex items-center justify-center bg-gradient-to-br from-blue-900 to-blue-700 px-4">
    <div class="w-full max-w-md">
      <div class="bg-white rounded-2xl shadow-xl p-8">

        <!-- Logo & Nama Institusi -->
        <div class="text-center mb-8">
          <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl overflow-hidden mb-4 shadow-md"
               :class="institution.logoUrl ? 'bg-transparent' : 'bg-blue-600'">
            <img
              v-if="institution.logoUrl"
              :src="institution.logoUrl"
              :alt="institution.name"
              class="w-full h-full object-contain"
            />
            <span v-else class="text-white font-bold text-2xl">
              {{ institution.name.charAt(0) }}
            </span>
          </div>
          <h1 class="text-2xl font-bold text-gray-900">{{ institution.name }}</h1>
          <p class="text-sm text-gray-500 mt-1">Sistem Informasi Akademik Terpadu</p>
        </div>

        <!-- Form -->
        <form class="space-y-5" @submit.prevent="handleLogin">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Email</label>
            <input
              v-model="form.email"
              type="email"
              required
              placeholder="nama@institusi.ac.id"
              class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition"
            />
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Password</label>
            <input
              v-model="form.password"
              type="password"
              required
              placeholder="••••••••"
              class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition"
            />
          </div>

          <button
            type="submit"
            :disabled="auth.loading"
            class="w-full py-2.5 bg-blue-600 hover:bg-blue-700 disabled:bg-blue-400 text-white text-sm font-semibold rounded-lg transition-colors"
          >
            {{ auth.loading ? 'Memproses...' : 'Masuk' }}
          </button>
        </form>

        <p class="text-center text-xs text-gray-400 mt-6">
          © {{ new Date().getFullYear() }} {{ institution.institution?.legal_entity_name || institution.name }}
        </p>
      </div>
    </div>
  </div>
</template>
