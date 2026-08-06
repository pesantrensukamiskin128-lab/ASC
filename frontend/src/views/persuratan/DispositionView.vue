<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useToast } from 'vue-toastification'
import api from '@/services/api'

const toast = useToast()
const items = ref<any[]>([])
const loading = ref(true)
const responseText = ref('')
const respondingId = ref<number | null>(null)

onMounted(() => load())

async function load() {
  loading.value = true
  try {
    const { data } = await api.get('/dispositions')
    items.value = data.data ?? data
  } catch { toast.error('Gagal memuat disposisi.') }
  finally { loading.value = false }
}

async function markRead(id: number) {
  try {
    await api.post(`/dispositions/${id}/read`)
    const item = items.value.find(i => i.id === id)
    if (item) item.is_read = true
  } catch {}
}

async function submitResponse(id: number) {
  if (!responseText.value.trim()) { toast.error('Jawaban tidak boleh kosong.'); return }
  try {
    await api.post(`/dispositions/${id}/respond`, { response: responseText.value })
    toast.success('Jawaban berhasil dikirim.')
    respondingId.value = null
    responseText.value = ''
    load()
  } catch (e: any) { toast.error(e?.response?.data?.message ?? 'Gagal.') }
}
</script>

<template>
  <div class="space-y-5">
    <div>
      <h1 class="text-xl font-bold text-gray-900">Disposisi Surat</h1>
      <p class="text-sm text-gray-500 mt-0.5">Daftar disposisi yang ditujukan kepada Anda</p>
    </div>

    <div v-if="loading" class="text-center text-gray-400 py-12">Memuat...</div>
    <div v-else-if="items.length === 0" class="text-center text-gray-400 py-12">Belum ada disposisi untuk Anda.</div>
    <div v-else class="space-y-4">
      <div v-for="item in items" :key="item.id"
        :class="['bg-white rounded-xl border p-5 transition-all', item.is_read ? 'border-gray-200' : 'border-l-4 border-l-blue-500 border-gray-200']">
        <div class="flex items-start justify-between">
          <div class="flex-1">
            <div class="flex items-center gap-2 mb-1">
              <span v-if="!item.is_read" class="px-2 py-0.5 bg-blue-100 text-blue-700 text-[10px] font-semibold rounded-full">Baru</span>
              <span class="text-xs text-gray-400">Dari: {{ item.creator?.name }}</span>
            </div>
            <h3 class="text-sm font-semibold text-gray-900">{{ item.incoming_letter?.subject }}</h3>
            <p class="text-xs text-gray-500 mt-0.5">Pengirim: {{ item.incoming_letter?.sender }}</p>
            <div class="mt-2 bg-amber-50 border border-amber-200 rounded-lg p-3">
              <p class="text-xs font-semibold text-amber-700">Instruksi:</p>
              <p class="text-sm text-amber-900 mt-1">{{ item.instruction }}</p>
              <p v-if="item.notes" class="text-xs text-amber-600 mt-1">Catatan: {{ item.notes }}</p>
            </div>
          </div>
        </div>

        <!-- Response -->
        <div class="mt-3">
          <div v-if="item.my_response" class="bg-green-50 border border-green-200 rounded-lg p-3">
            <p class="text-xs font-semibold text-green-700">Jawaban Anda:</p>
            <p class="text-sm text-green-900 mt-1">{{ item.my_response }}</p>
            <p class="text-[10px] text-green-500 mt-1">{{ item.responded_at }}</p>
          </div>
          <div v-else>
            <div v-if="respondingId === item.id" class="mt-2">
              <textarea v-model="responseText" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Tulis jawaban/tindak lanjut..." />
              <div class="flex gap-2 mt-2">
                <button class="px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-medium rounded-lg" @click="submitResponse(item.id)">Kirim Jawaban</button>
                <button class="px-3 py-1.5 text-xs text-gray-600 hover:bg-gray-100 rounded-lg" @click="respondingId = null">Batal</button>
              </div>
            </div>
            <div v-else class="flex items-center gap-2 mt-2">
              <button v-if="!item.is_read" class="px-3 py-1.5 text-xs text-blue-600 hover:bg-blue-50 rounded-lg border border-blue-200" @click="markRead(item.id)">Tandai Dibaca</button>
              <button class="px-3 py-1.5 text-xs text-green-600 hover:bg-green-50 rounded-lg border border-green-200" @click="respondingId = item.id; responseText = ''">Jawab</button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
