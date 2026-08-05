<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useToast } from 'vue-toastification'
import { ArrowLeftIcon, CheckCircleIcon } from '@heroicons/vue/24/outline'
import { useAuthStore } from '@/stores/auth'
import api from '@/services/api'

const route = useRoute()
const router = useRouter()
const toast = useToast()
const auth = useAuthStore()

const loading = ref(true)
const saving = ref(false)
const journal = ref<any>(null)
const attendances = ref<any[]>([])
const classMembers = ref<any[]>([])

const statusOptions = ['HADIR', 'IZIN', 'SAKIT', 'ALFA']
const statusColor: Record<string, string> = {
  HADIR: 'bg-green-100 text-green-700',
  IZIN: 'bg-blue-100 text-blue-700',
  SAKIT: 'bg-yellow-100 text-yellow-700',
  ALFA: 'bg-red-100 text-red-700',
}

const classId = computed(() => route.params.id)
const journalId = computed(() => route.params.journalId)

onMounted(async () => {
  try {
    const [journalRes, memberRes, attendRes] = await Promise.all([
      api.get(`/lectures/${classId.value}/journals`).then(({ data }) =>
        Array.isArray(data) ? data.find((j: any) => j.id == journalId.value) : null
      ),
      api.get(`/lectures/${classId.value}/members`).then(({ data }) => data),
      api.get(`/lectures/journals/${journalId.value}/attendances`).then(({ data }) => data),
    ])
    journal.value = journalRes
    classMembers.value = memberRes

    // Build attendance map: student_id → status
    const existingMap: Record<number, string> = {}
    for (const att of (attendRes ?? [])) {
      existingMap[att.student_id] = att.status
    }

    // Merge with members
    attendances.value = memberRes.map((m: any) => ({
      student_id: m.student_id,
      student: m.student,
      status: existingMap[m.student_id] ?? 'ALFA',
    }))
  } catch (e: any) {
    toast.error('Gagal memuat data presensi.')
  } finally {
    loading.value = false
  }
})

function setAll(status: string) {
  attendances.value = attendances.value.map(a => ({ ...a, status }))
}

const hadir = computed(() => attendances.value.filter(a => a.status === 'HADIR').length)

async function save() {
  saving.value = true
  try {
    await api.post(`/lectures/journals/${journalId.value}/attendances`, {
      attendances: attendances.value.map(a => ({
        student_id: a.student_id,
        status: a.status,
      })),
    })
    toast.success('Presensi berhasil disimpan.')
  } catch (e: any) {
    toast.error(e?.response?.data?.message ?? 'Gagal menyimpan.')
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <div class="space-y-5">
    <!-- Header -->
    <div class="flex items-start gap-3">
      <button class="p-2 rounded-lg hover:bg-gray-100" @click="router.back()">
        <ArrowLeftIcon class="w-5 h-5 text-gray-500" />
      </button>
      <div class="flex-1">
        <h1 class="text-xl font-bold text-gray-900">
          Presensi — Pertemuan {{ journal?.meeting_number }}
        </h1>
        <p class="text-sm text-gray-500 mt-0.5">{{ journal?.topic }} · {{ journal?.meeting_date }}</p>
      </div>
    </div>

    <div v-if="loading" class="flex items-center justify-center h-48">
      <p class="text-gray-400">Memuat...</p>
    </div>

    <template v-else>
      <!-- Summary & Bulk Actions -->
      <div class="flex items-center justify-between bg-white rounded-xl border border-gray-200 p-4">
        <div class="flex items-center gap-4">
          <div class="flex items-center gap-2">
            <CheckCircleIcon class="w-5 h-5 text-green-600" />
            <span class="text-sm font-medium text-gray-700">{{ hadir }}/{{ attendances.length }} Hadir</span>
          </div>
        </div>
        <div v-if="!auth.hasRole('MAHASISWA')" class="flex items-center gap-2">
          <span class="text-xs text-gray-500">Set semua:</span>
          <button v-for="s in statusOptions" :key="s" :class="['px-2.5 py-1 rounded-full text-xs font-medium cursor-pointer', statusColor[s]]" @click="setAll(s)">{{ s }}</button>
        </div>
      </div>

      <!-- List Mahasiswa -->
      <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <table class="w-full text-sm">
          <thead>
            <tr class="bg-gray-50 text-left text-xs text-gray-500 uppercase border-b">
              <th class="px-4 py-3">No</th>
              <th class="px-4 py-3">NIM</th>
              <th class="px-4 py-3">Nama Mahasiswa</th>
              <th class="px-4 py-3 text-center">Status Presensi</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(att, i) in attendances" :key="att.student_id" class="border-b border-gray-100 hover:bg-gray-50">
              <td class="px-4 py-3 text-gray-500">{{ i + 1 }}</td>
              <td class="px-4 py-3 font-mono text-xs text-gray-600">{{ att.student?.nim }}</td>
              <td class="px-4 py-3 font-medium text-gray-900">{{ att.student?.name }}</td>
              <td class="px-4 py-3">
                <div class="flex items-center justify-center gap-1">
                  <template v-if="!auth.hasRole('MAHASISWA')">
                    <button
                      v-for="s in statusOptions"
                      :key="s"
                      :class="['px-2.5 py-1 rounded-full text-xs font-medium transition-all', att.status === s ? statusColor[s] + ' ring-2 ring-offset-1 ring-current' : 'bg-gray-100 text-gray-500 hover:bg-gray-200']"
                      @click="att.status = s"
                    >{{ s }}</button>
                  </template>
                  <span v-else :class="['px-2.5 py-1 rounded-full text-xs font-medium', statusColor[att.status]]">{{ att.status }}</span>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
        <div v-if="!attendances.length" class="text-center py-8 text-gray-400 text-sm">
          Belum ada anggota kelas.
        </div>
      </div>

      <!-- Save Button — hanya dosen/admin -->
      <div v-if="!auth.hasRole('MAHASISWA')" class="flex justify-end">
        <button
          :disabled="saving"
          class="px-6 py-2.5 bg-blue-600 hover:bg-blue-700 disabled:bg-blue-400 text-white text-sm font-medium rounded-lg"
          @click="save"
        >
          {{ saving ? 'Menyimpan...' : 'Simpan Presensi' }}
        </button>
      </div>
    </template>
  </div>
</template>
