import { ref } from 'vue'
import { useToast } from 'vue-toastification'
import api from '@/services/api'

export interface PaginatedResponse<T> {
  data: T[]
  current_page: number
  last_page: number
  total: number
  per_page: number
}

/** Ambil pesan error dari response Laravel (validasi 422 atau message biasa) */
export function extractErrorMessage(err: any): string {
  const res = err?.response?.data
  if (!res) return 'Terjadi kesalahan. Coba lagi.'

  // Laravel validation errors — ambil pesan pertama dari setiap field
  if (res.errors) {
    const messages = Object.values(res.errors as Record<string, string[]>)
      .flat()
      .join(', ')
    return messages
  }

  return res.message || 'Gagal menyimpan data.'
}

/** Bersihkan payload: ubah string kosong jadi null agar tidak gagal validasi FK */
export function cleanPayload(payload: Record<string, any>): Record<string, any> {
  return Object.fromEntries(
    Object.entries(payload).map(([k, v]) => [k, v === '' ? null : v])
  )
}

export function useCrud<T extends { id: number }>(endpoint: string) {
  const toast = useToast()
  const items = ref<T[]>([])
  const pagination = ref({ currentPage: 1, lastPage: 1, total: 0, perPage: 15 })
  const loading = ref(false)

  async function fetchAll(params: Record<string, unknown> = {}) {
    loading.value = true
    try {
      const { data } = await api.get<PaginatedResponse<T>>(endpoint, { params })
      items.value = data.data
      pagination.value = {
        currentPage: data.current_page,
        lastPage: data.last_page,
        total: data.total,
        perPage: data.per_page,
      }
    } catch {
      toast.error('Gagal memuat data.')
    } finally {
      loading.value = false
    }
  }

  async function create(payload: Record<string, any>): Promise<T | null> {
    try {
      const { data } = await api.post<{ data: T; message: string }>(endpoint, cleanPayload(payload))
      toast.success(data.message || 'Data berhasil ditambahkan.')
      return data.data
    } catch (err: any) {
      toast.error(extractErrorMessage(err))
      throw err
    }
  }

  async function update(id: number, payload: Record<string, any>): Promise<T | null> {
    try {
      const { data } = await api.put<{ data: T; message: string }>(`${endpoint}/${id}`, cleanPayload(payload))
      toast.success(data.message || 'Data berhasil diupdate.')
      return data.data
    } catch (err: any) {
      toast.error(extractErrorMessage(err))
      throw err
    }
  }

  async function remove(id: number): Promise<void> {
    try {
      const { data } = await api.delete<{ message: string }>(`${endpoint}/${id}`)
      toast.success(data.message || 'Data berhasil dihapus.')
    } catch (err: any) {
      toast.error(extractErrorMessage(err))
      throw err
    }
  }

  return { items, pagination, loading, fetchAll, create, update, remove }
}
