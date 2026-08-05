import { ref } from 'vue'
import { useToast } from 'vue-toastification'
import api from '@/services/api'
import { extractErrorMessage } from './useCrud'

export function useExcel(endpoint: string) {
  const toast       = useToast()
  const exporting   = ref(false)
  const importing   = ref(false)
  const importErrors = ref<string[]>([])

  /** Download file Excel dari server */
  async function exportExcel(params: Record<string, any> = {}, filename?: string) {
    exporting.value = true
    try {
      const res = await api.get(`${endpoint}/export`, {
        params,
        responseType: 'blob',
      })
      const url  = URL.createObjectURL(new Blob([res.data]))
      const link = document.createElement('a')
      link.href  = url

      // Ambil nama file dari header jika ada, fallback ke parameter
      const disposition = res.headers['content-disposition'] ?? ''
      const match = disposition.match(/filename="?([^";\n]+)"?/)
      link.download = match?.[1] ?? filename ?? `export-${Date.now()}.xlsx`

      document.body.appendChild(link)
      link.click()
      link.remove()
      URL.revokeObjectURL(url)

      toast.success('File berhasil diunduh.')
    } catch (err: any) {
      toast.error('Gagal mengunduh file.')
    } finally {
      exporting.value = false
    }
  }

  /** Upload file Excel ke server */
  async function importExcel(file: File, onSuccess?: () => void) {
    importing.value = true
    importErrors.value = []
    try {
      const formData = new FormData()
      formData.append('file', file)

      const { data } = await api.post(`${endpoint}/import`, formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
      })

      if (data.errors?.length) {
        importErrors.value = data.errors
        toast.warning(`${data.message} (${data.errors.length} baris dilewati)`)
      } else {
        toast.success(data.message ?? 'Import berhasil.')
      }

      onSuccess?.()
    } catch (err: any) {
      toast.error(extractErrorMessage(err))
    } finally {
      importing.value = false
    }
  }

  return { exporting, importing, importErrors, exportExcel, importExcel }
}
