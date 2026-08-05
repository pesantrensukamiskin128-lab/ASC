import { ref, computed } from 'vue'
import { defineStore } from 'pinia'
import router from '@/router'
import AuthService, { type AuthUser, type LoginCredentials, type UpdateProfilePayload } from '@/services/auth.service'

export const useAuthStore = defineStore('auth', () => {
  const user = ref<AuthUser | null>(null)
  const token = ref<string | null>(localStorage.getItem('auth_token'))
  const loading = ref(false)

  const isAuthenticated = computed(() => !!token.value && !!user.value)

  const hasRole = (role: string | string[]) => {
    if (!user.value) return false
    const roles = Array.isArray(role) ? role : [role]
    return roles.some((r) => user.value!.roles.includes(r))
  }

  const hasPermission = (permission: string) => {
    if (!user.value) return false
    return user.value.permissions.includes(permission)
  }

  async function login(credentials: LoginCredentials) {
    loading.value = true
    try {
      const response = await AuthService.login(credentials)
      token.value = response.access_token
      user.value = response.user
      localStorage.setItem('auth_token', response.access_token)
      await router.push('/dashboard')
    } finally {
      loading.value = false
    }
  }

  async function fetchMe() {
    if (!token.value) return
    try {
      user.value = await AuthService.me()
    } catch {
      logout()
    }
  }

  async function updateProfile(payload: UpdateProfilePayload) {
    const response = await AuthService.updateProfile(payload)
    user.value = response.user
    return response
  }

  async function logout() {
    try {
      if (token.value) await AuthService.logout()
    } catch {
      // ignore
    } finally {
      user.value = null
      token.value = null
      localStorage.removeItem('auth_token')
      await router.push('/login')
    }
  }

  return { user, token, loading, isAuthenticated, hasRole, hasPermission, login, fetchMe, updateProfile, logout }
})
