import api from './api'

export interface LoginCredentials {
  email: string
  password: string
}

export interface AuthUser {
  id: number
  name: string
  email: string
  username: string
  is_active: boolean
  last_login_at: string | null
  roles: string[]
  permissions: string[]
  student?: { id: number; nim: string; name: string; study_program_id?: number } | null
  lecturer?: { id: number; name: string } | null
}

export interface UpdateProfilePayload {
  name?: string
  email?: string
  username?: string
  current_password?: string
  new_password?: string
  new_password_confirmation?: string
}

export interface LoginResponse {
  access_token: string
  token_type: string
  user: AuthUser
}

const AuthService = {
  async login(credentials: LoginCredentials): Promise<LoginResponse> {
    const { data } = await api.post<LoginResponse>('/auth/login', credentials)
    return data
  },

  async me(): Promise<AuthUser> {
    const { data } = await api.get<AuthUser>('/auth/me')
    return data
  },

  async updateProfile(payload: UpdateProfilePayload): Promise<{ message: string; user: AuthUser }> {
    const { data } = await api.put<{ message: string; user: AuthUser }>('/auth/profile', payload)
    return data
  },

  async logout(): Promise<void> {
    await api.post('/auth/logout')
  },
}

export default AuthService
