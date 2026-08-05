import { ref, computed } from 'vue'
import { defineStore } from 'pinia'
import api from '@/services/api'

interface Institution {
  id: number
  name: string
  short_name: string
  legal_entity_name: string | null
  logo_path: string | null
  logo_url: string | null
  letterhead_path: string | null
  letterhead_url: string | null
  accreditation: string
}

export const useInstitutionStore = defineStore('institution', () => {
  const institution = ref<Institution | null>(null)

  // Gunakan logo_url yang sudah digenerate backend (absolute URL)
  const logoUrl = computed(() => institution.value?.logo_url ?? null)
  const letterheadUrl = computed(() => institution.value?.letterhead_url ?? null)

  const name = computed(() => institution.value?.short_name || institution.value?.name || 'ASC')

  async function fetch() {
    if (institution.value) return   // sudah ada data, tidak perlu fetch ulang
    try {
      const { data } = await api.get('/institution/public')
      institution.value = data ?? null
    } catch {
      // silent fail — tidak blok app
    }
  }

  return { institution, logoUrl, letterheadUrl, name, fetch }
})
