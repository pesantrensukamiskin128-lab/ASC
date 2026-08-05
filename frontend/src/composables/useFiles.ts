import { ref } from 'vue'
import { useToast } from 'vue-toastification'
import api from '@/services/api'

export interface FileRecord {
  id: number
  owner_type: string
  owner_id: number
  collection: string
  file_name: string
  file_path: string
  disk: string
  mime_type: string
  file_size: number
  file_hash: string
  url: string
  uploaded_by: number | null
  created_at: string
}

export function useFiles(ownerType: string, ownerId: () => number | null) {
  const toast     = useToast()
  const files     = ref<FileRecord[]>([])
  const uploading = ref(false)
  const loading   = ref(false)

  /** Fetch files dari server */
  async function fetchFiles(collection?: string) {
    const id = ownerId()
    if (!id) return
    loading.value = true
    try {
      const { data } = await api.get('/files', {
        params: { owner_type: ownerType, owner_id: id, collection },
      })
      files.value = data
    } finally {
      loading.value = false
    }
  }

  /** Upload file */
  async function uploadFile(file: File, collection = 'default', replace = false): Promise<FileRecord | null> {
    const id = ownerId()
    if (!id) {
      toast.error('Simpan data terlebih dahulu sebelum upload file.')
      return null
    }

    uploading.value = true
    try {
      const formData = new FormData()
      formData.append('file', file)
      formData.append('owner_type', ownerType)
      formData.append('owner_id', id.toString())
      formData.append('collection', collection)
      if (replace) formData.append('replace', '1')

      const { data } = await api.post('/files/upload', formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
      })
      toast.success(data.message)
      await fetchFiles()
      return data.data
    } catch (err: any) {
      toast.error(err?.response?.data?.message ?? 'Upload gagal.')
      return null
    } finally {
      uploading.value = false
    }
  }

  /** Hapus file */
  async function deleteFile(fileId: number) {
    try {
      const { data } = await api.delete(`/files/${fileId}`)
      toast.success(data.message)
      files.value = files.value.filter(f => f.id !== fileId)
    } catch {
      toast.error('Gagal menghapus file.')
    }
  }

  /** Ambil file pertama dari collection */
  function getByCollection(collection: string): FileRecord | undefined {
    return files.value.find(f => f.collection === collection)
  }

  /** Format ukuran file */
  function humanSize(bytes: number): string {
    if (bytes < 1024) return bytes + ' B'
    if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB'
    return (bytes / 1048576).toFixed(1) + ' MB'
  }

  return { files, uploading, loading, fetchFiles, uploadFile, deleteFile, getByCollection, humanSize }
}
