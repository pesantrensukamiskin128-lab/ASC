<script setup lang="ts">
import { ref, onMounted, reactive } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { PlusIcon, TrashIcon, ArrowLeftIcon } from '@heroicons/vue/24/outline'
import { useToast } from 'vue-toastification'
import BaseModal from '@/components/ui/BaseModal.vue'
import api from '@/services/api'
import { cleanPayload, extractErrorMessage } from '@/composables/useCrud'

const route = useRoute()
const router = useRouter()
const toast = useToast()

const curriculum = ref<any>(null)
const loading = ref(false)
const loModalOpen = ref(false)
const courseModalOpen = ref(false)
const saving = ref(false)
const savingCourses = ref(false)

const loForm = reactive({ code: '', description: '', graduate_profile_ids: [] as number[] })

// Data untuk modal mata kuliah
interface CourseOption { id: number; code: string; name: string; credits: number; semester: number; type: string }
const allCourses = ref<CourseOption[]>([])
const selectedCourses = ref<{ course_id: number; semester: number; is_required: boolean }[]>([])

// Profil Lulusan
const profileModalOpen = ref(false)
const savingProfile = ref(false)
const editingProfileId = ref<number | null>(null)
const profileForm = reactive({ code: '', name: '', description: '' })
const graduateProfiles = ref<any[]>([])

onMounted(load)

async function load() {
  loading.value = true
  try {
    const [curRes, profileRes] = await Promise.all([
      api.get(`/curriculums/${route.params.id}`),
      api.get(`/curriculums/${route.params.id}/graduate-profiles`),
    ])
    curriculum.value = curRes.data
    graduateProfiles.value = profileRes.data
    // Load matrix setelah data tersedia
    loadMatrix()
  } catch {
    toast.error('Gagal memuat data kurikulum.')
  } finally {
    loading.value = false
  }
}

// Kelola Profil Lulusan
function openCreateProfile() {
  editingProfileId.value = null
  Object.assign(profileForm, { code: '', name: '', description: '' })
  profileModalOpen.value = true
}

function openEditProfile(profile: any) {
  editingProfileId.value = profile.id
  Object.assign(profileForm, { code: profile.code, name: profile.name, description: profile.description ?? '' })
  profileModalOpen.value = true
}

async function saveGraduateProfile() {
  savingProfile.value = true
  try {
    if (editingProfileId.value) {
      await api.put(`/curriculums/${route.params.id}/graduate-profiles/${editingProfileId.value}`, profileForm)
      toast.success('Profil lulusan berhasil diupdate.')
    } else {
      await api.post(`/curriculums/${route.params.id}/graduate-profiles`, profileForm)
      toast.success('Profil lulusan berhasil ditambahkan.')
    }
    profileModalOpen.value = false
    Object.assign(profileForm, { code: '', name: '', description: '' })
    load()
  } catch (err: any) {
    toast.error(extractErrorMessage(err))
  } finally {
    savingProfile.value = false
  }
}

async function deleteGraduateProfile(profileId: number) {
  if (!confirm('Hapus profil lulusan ini?')) return
  try {
    await api.delete(`/curriculums/${route.params.id}/graduate-profiles/${profileId}`)
    toast.success('Profil lulusan berhasil dihapus.')
    load()
  } catch (err: any) {
    toast.error(extractErrorMessage(err))
  }
}

// Kelola CPL
const editingLoId = ref<number | null>(null)

function openCreateCpl() {
  editingLoId.value = null
  Object.assign(loForm, { code: '', description: '', graduate_profile_ids: [] })
  loModalOpen.value = true
}

function openEditCpl(lo: any) {
  editingLoId.value = lo.id
  Object.assign(loForm, {
    code: lo.code,
    description: lo.description,
    graduate_profile_ids: lo.graduate_profiles?.map((gp: any) => gp.id) ?? [],
  })
  loModalOpen.value = true
}

async function saveLearningOutcome() {
  saving.value = true
  try {
    if (editingLoId.value) {
      await api.put(`/curriculums/${route.params.id}/learning-outcomes/${editingLoId.value}`, cleanPayload(loForm))
      toast.success('CPL berhasil diupdate.')
    } else {
      await api.post(`/curriculums/${route.params.id}/learning-outcomes`, cleanPayload(loForm))
      toast.success('CPL berhasil ditambahkan.')
    }
    loModalOpen.value = false
    Object.assign(loForm, { code: '', description: '', graduate_profile_ids: [] })
    load()
  } catch (err: any) {
    toast.error(extractErrorMessage(err))
  } finally {
    saving.value = false
  }
}

async function deleteLearningOutcome(loId: number) {
  if (!confirm('Hapus CPL ini?')) return
  try {
    await api.delete(`/curriculums/${route.params.id}/learning-outcomes/${loId}`)
    toast.success('CPL berhasil dihapus.')
    load()
  } catch (err: any) {
    toast.error(extractErrorMessage(err))
  }
}

// ===== Kelola Mata Kuliah =====
async function openCourseModal() {
  // Load semua mata kuliah prodi ini
  if (allCourses.value.length === 0 && curriculum.value?.study_program?.id) {
    const { data } = await api.get('/courses/all', { params: { study_program_id: curriculum.value.study_program.id } })
    allCourses.value = data
  }
  // Pre-fill selected dari data existing
  selectedCourses.value = (curriculum.value?.curriculum_courses ?? []).map((cc: any) => ({
    course_id: cc.course?.id ?? cc.course_id,
    semester: cc.semester,
    is_required: cc.is_required,
  }))
  courseModalOpen.value = true
}

function isCourseSelected(courseId: number): boolean {
  return selectedCourses.value.some(c => c.course_id === courseId)
}

function toggleCourse(course: CourseOption) {
  const idx = selectedCourses.value.findIndex(c => c.course_id === course.id)
  if (idx >= 0) {
    selectedCourses.value.splice(idx, 1)
  } else {
    selectedCourses.value.push({ course_id: course.id, semester: course.semester, is_required: course.type === 'Wajib' })
  }
}

function getSelectedCourse(courseId: number) {
  return selectedCourses.value.find(c => c.course_id === courseId)
}

async function saveCourses() {
  savingCourses.value = true
  try {
    await api.post(`/curriculums/${route.params.id}/courses`, { courses: selectedCourses.value })
    toast.success('Mata kuliah kurikulum berhasil disimpan.')
    courseModalOpen.value = false
    load()
  } catch (err: any) {
    toast.error(extractErrorMessage(err))
  } finally {
    savingCourses.value = false
  }
}

// (groupedOutcomes removed — CPL displayed as flat list)

// CPL-MK Matrix
const matrixData = ref<any>(null)
const loadingMatrix = ref(false)
const savingMatrix = ref(false)

async function loadMatrix() {
  const cpls = curriculum.value?.learning_outcomes
  const courses = curriculum.value?.curriculum_courses
  if (!cpls?.length || !courses?.length) return
  loadingMatrix.value = true
  try {
    const { data } = await api.get(`/curriculums/${route.params.id}/cpl-course-matrix`)
    matrixData.value = data
  } catch (e) {
    console.error('Matrix load error:', e)
  }
  finally { loadingMatrix.value = false }
}

function isChecked(cplId: number, courseId: number): boolean {
  if (!matrixData.value) return false
  const row = matrixData.value.matrix.find((r: any) => r.cpl.id === cplId)
  return !!row?.courses?.[courseId]
}

function toggleMapping(cplId: number, courseId: number) {
  if (!matrixData.value) return
  const row = matrixData.value.matrix.find((r: any) => r.cpl.id === cplId)
  if (row) {
    row.courses[courseId] = row.courses[courseId] ? null : 'Tinggi'
  }
}

async function saveMatrix() {
  if (!matrixData.value) return
  savingMatrix.value = true
  const mappings: any[] = []
  matrixData.value.matrix.forEach((row: any) => {
    Object.entries(row.courses).forEach(([courseId, level]) => {
      mappings.push({
        learning_outcome_id: row.cpl.id,
        course_id: Number(courseId),
        checked: !!level,
      })
    })
  })
  try {
    await api.post(`/curriculums/${route.params.id}/cpl-course-mapping`, { mappings })
    toast.success('Pemetaan CPL–Mata Kuliah berhasil disimpan.')
  } catch (err: any) { toast.error(extractErrorMessage(err)) }
  finally { savingMatrix.value = false }
}
</script>

<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center gap-3">
      <button class="p-1.5 rounded-lg text-gray-500 hover:bg-gray-100" @click="router.back()">
        <ArrowLeftIcon class="w-5 h-5" />
      </button>
      <div class="flex-1">
        <h1 class="text-xl font-bold text-gray-900">{{ curriculum?.name ?? 'Loading...' }}</h1>
        <p class="text-sm text-gray-500 mt-0.5">{{ curriculum?.study_program?.name }} — {{ curriculum?.year }}</p>
      </div>
      <span v-if="curriculum" :class="['inline-flex px-3 py-1 rounded-full text-xs font-medium',
        curriculum.status === 'Aktif' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600']">
        {{ curriculum?.status }}
      </span>
    </div>

    <div v-if="loading" class="text-center py-12 text-gray-400">Memuat data...</div>

    <div v-else-if="curriculum" class="space-y-6">

      <!-- Profil Lulusan -->
      <div class="bg-white rounded-xl border border-gray-200 p-5">
        <div class="flex items-center justify-between mb-4">
          <div>
            <h2 class="font-semibold text-gray-900">Profil Lulusan</h2>
            <p class="text-xs text-gray-500 mt-0.5">Kompetensi yang diharapkan dari lulusan program studi</p>
          </div>
          <button class="flex items-center gap-1.5 px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-medium rounded-lg" @click="openCreateProfile">
            <PlusIcon class="w-3.5 h-3.5" /> Tambah Profil
          </button>
        </div>

        <div v-if="!graduateProfiles.length" class="text-center py-6 text-gray-400 text-sm">
          Belum ada profil lulusan. Tambahkan profil untuk mendefinisikan kompetensi lulusan.
        </div>

        <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
          <div v-for="profile in graduateProfiles" :key="profile.id"
            class="p-3 border border-gray-200 rounded-lg group hover:border-blue-200 transition-colors cursor-pointer"
            @click="openEditProfile(profile)">
            <div class="flex items-start justify-between">
              <span class="inline-flex px-2 py-0.5 bg-blue-100 text-blue-700 text-xs font-mono font-bold rounded">
                {{ profile.code }}
              </span>
              <button class="opacity-0 group-hover:opacity-100 p-1 text-red-400 hover:text-red-600 transition-opacity" @click.stop="deleteGraduateProfile(profile.id)">
                <TrashIcon class="w-3.5 h-3.5" />
              </button>
            </div>
            <p class="text-sm font-medium text-gray-800 mt-2">{{ profile.name }}</p>
            <p v-if="profile.description" class="text-xs text-gray-500 mt-1">{{ profile.description }}</p>
          </div>
        </div>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <!-- CPL - Capaian Pembelajaran Lulusan -->
      <div class="bg-white rounded-xl border border-gray-200 p-5">
        <div class="flex items-center justify-between mb-4">
          <h2 class="font-semibold text-gray-900">Capaian Pembelajaran Lulusan (CPL)</h2>
          <button class="flex items-center gap-1.5 px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-medium rounded-lg" @click="openCreateCpl">
            <PlusIcon class="w-3.5 h-3.5" /> Tambah CPL
          </button>
        </div>

        <div v-if="curriculum.learning_outcomes?.length === 0" class="text-center py-8 text-gray-400 text-sm">
          Belum ada CPL. Klik Tambah CPL untuk mulai.
        </div>

        <div v-else class="space-y-2">
          <div v-for="lo in curriculum.learning_outcomes" :key="lo.id" class="flex items-start gap-3 p-3 rounded-lg bg-gray-50 group">
            <span class="inline-flex px-2 py-0.5 rounded text-xs font-mono font-bold shrink-0 mt-0.5 bg-blue-100 text-blue-700">
              {{ lo.code }}
            </span>
            <div class="flex-1 cursor-pointer" @click="openEditCpl(lo)">
              <p class="text-sm text-gray-700 leading-relaxed">{{ lo.description }}</p>
              <div v-if="lo.graduate_profiles?.length" class="flex flex-wrap gap-1 mt-1.5">
                <span v-for="gp in lo.graduate_profiles" :key="gp.id" class="inline-flex px-1.5 py-0.5 bg-blue-50 text-blue-600 text-[10px] font-medium rounded">
                  {{ gp.code }} {{ gp.name }}
                </span>
              </div>
            </div>
            <button class="opacity-0 group-hover:opacity-100 p-1 text-red-400 hover:text-red-600 transition-opacity shrink-0" @click="deleteLearningOutcome(lo.id)">
              <TrashIcon class="w-4 h-4" />
            </button>
          </div>
        </div>
      </div>

      <!-- Mata Kuliah dalam Kurikulum -->
      <div class="bg-white rounded-xl border border-gray-200 p-5">
        <div class="flex items-center justify-between mb-4">
          <h2 class="font-semibold text-gray-900">Mata Kuliah ({{ curriculum.curriculum_courses?.length ?? 0 }} MK)</h2>
          <button class="flex items-center gap-1.5 px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-medium rounded-lg" @click="openCourseModal">
            <PlusIcon class="w-3.5 h-3.5" /> Kelola MK
          </button>
        </div>

        <div v-if="!curriculum.curriculum_courses?.length" class="text-center py-8 text-gray-400 text-sm">
          Belum ada mata kuliah dalam kurikulum ini.
        </div>

        <div v-else class="space-y-1">
          <!-- Group by semester -->
          <div v-for="sem in 8" :key="sem">
            <template v-if="curriculum.curriculum_courses.filter((c: any) => c.semester === sem).length">
              <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider mt-3 mb-1">Semester {{ sem }}</p>
              <div v-for="cc in curriculum.curriculum_courses.filter((c: any) => c.semester === sem)" :key="cc.id"
                class="flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-gray-50">
                <span class="font-mono text-xs text-gray-500 w-16 shrink-0">{{ cc.course?.code }}</span>
                <span class="text-sm text-gray-800 flex-1">{{ cc.course?.name }}</span>
                <span class="text-xs text-gray-500 shrink-0">{{ cc.course?.credits }} SKS</span>
                <span :class="['text-xs px-1.5 py-0.5 rounded shrink-0', cc.is_required ? 'bg-blue-100 text-blue-700' : 'bg-yellow-100 text-yellow-700']">
                  {{ cc.is_required ? 'Wajib' : 'Pilihan' }}
                </span>
              </div>
            </template>
          </div>
        </div>
      </div>
      </div>

      <!-- Pemetaan CPL – Mata Kuliah -->
      <div v-if="curriculum.learning_outcomes?.length && curriculum.curriculum_courses?.length" class="bg-white rounded-xl border border-gray-200 p-5">
        <div class="flex items-center justify-between mb-4">
          <div>
            <h2 class="font-semibold text-gray-900">Pemetaan CPL – Mata Kuliah</h2>
            <p class="text-xs text-gray-500 mt-0.5">Centang CPL yang didukung oleh setiap mata kuliah</p>
          </div>
          <button v-if="matrixData" :disabled="savingMatrix" class="px-3 py-1.5 bg-blue-600 hover:bg-blue-700 disabled:bg-blue-400 text-white text-xs font-medium rounded-lg" @click="saveMatrix">
            {{ savingMatrix ? 'Menyimpan...' : 'Simpan Pemetaan' }}
          </button>
        </div>

        <div v-if="loadingMatrix" class="text-center py-6 text-gray-400 text-sm">Memuat matriks...</div>

        <div v-else-if="matrixData" class="overflow-x-auto">
          <table class="text-xs border-collapse w-full">
            <thead>
              <tr>
                <th class="border border-gray-200 px-2 py-2 bg-gray-50 text-left min-w-[180px]">Mata Kuliah</th>
                <th v-for="cpl in matrixData.cpls" :key="cpl.id"
                  class="border border-gray-200 px-2 py-2 bg-gray-50 text-center min-w-[55px]"
                  :title="cpl.description">
                  <span class="font-mono font-bold text-blue-700">{{ cpl.code }}</span>
                </th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="course in matrixData.courses" :key="course.id" class="hover:bg-blue-50/30">
                <td class="border border-gray-200 px-2 py-1.5 bg-gray-50">
                  <span class="font-mono text-gray-500 mr-1.5">{{ course.code }}</span>
                  <span class="text-gray-800">{{ course.name }}</span>
                </td>
                <td v-for="cpl in matrixData.cpls" :key="cpl.id"
                  class="border border-gray-200 text-center cursor-pointer hover:bg-blue-100 transition-colors"
                  @click="toggleMapping(cpl.id, course.id)">
                  <span v-if="isChecked(cpl.id, course.id)" class="text-green-600 font-bold text-sm">✓</span>
                  <span v-else class="text-gray-200">–</span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

    </div>
  </div>

  <!-- Modal Profil Lulusan -->
  <BaseModal :open="profileModalOpen" :title="editingProfileId ? 'Edit Profil Lulusan' : 'Tambah Profil Lulusan'" @close="profileModalOpen = false">
    <form class="space-y-4" @submit.prevent="saveGraduateProfile">
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Kode <span class="text-red-500">*</span></label>
        <input v-model="profileForm.code" required placeholder="PL-01" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Nama Profil <span class="text-red-500">*</span></label>
        <input v-model="profileForm.name" required placeholder="Praktisi hukum ekonomi syariah" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Deskripsi</label>
        <textarea v-model="profileForm.description" rows="3" placeholder="Deskripsi kompetensi..." class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
      </div>
    </form>
    <template #footer>
      <button class="px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded-lg" @click="profileModalOpen = false">Batal</button>
      <button :disabled="savingProfile" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 disabled:bg-blue-400 text-white text-sm font-medium rounded-lg" @click="saveGraduateProfile">
        {{ savingProfile ? 'Menyimpan...' : 'Simpan' }}
      </button>
    </template>
  </BaseModal>

  <!-- Modal CPL -->
  <BaseModal :open="loModalOpen" :title="editingLoId ? 'Edit CPL' : 'Tambah CPL'" @close="loModalOpen = false">
    <form class="space-y-4" @submit.prevent="saveLearningOutcome">
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Kode CPL <span class="text-red-500">*</span></label>
        <input v-model="loForm.code" required placeholder="CPL-01" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Deskripsi CPL <span class="text-red-500">*</span></label>
        <textarea v-model="loForm.description" required rows="3" placeholder="Mampu..." class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Profil Lulusan Terkait</label>
        <div v-if="graduateProfiles.length" class="space-y-1.5 max-h-40 overflow-y-auto border border-gray-200 rounded-lg p-2">
          <label v-for="gp in graduateProfiles" :key="gp.id" class="flex items-center gap-2 p-1.5 rounded hover:bg-gray-50 cursor-pointer">
            <input type="checkbox" :value="gp.id" v-model="loForm.graduate_profile_ids" class="rounded border-gray-300 text-blue-600" />
            <span class="text-xs font-mono text-blue-700 font-bold">{{ gp.code }}</span>
            <span class="text-sm text-gray-700">{{ gp.name }}</span>
          </label>
        </div>
        <p v-else class="text-xs text-gray-400 italic">Belum ada profil lulusan. Tambahkan terlebih dahulu.</p>
      </div>
    </form>
    <template #footer>
      <button class="px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded-lg" @click="loModalOpen = false">Batal</button>
      <button :disabled="saving" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 disabled:bg-blue-400 text-white text-sm font-medium rounded-lg" @click="saveLearningOutcome">
        {{ saving ? 'Menyimpan...' : 'Simpan' }}
      </button>
    </template>
  </BaseModal>

  <!-- Modal Kelola Mata Kuliah -->
  <BaseModal :open="courseModalOpen" title="Kelola Mata Kuliah Kurikulum" size="xl" @close="courseModalOpen = false">
    <div class="space-y-3">
      <p class="text-sm text-gray-500">Centang mata kuliah yang termasuk dalam kurikulum ini. Atur semester dan status wajib/pilihan.</p>

      <div class="max-h-96 overflow-y-auto border border-gray-200 rounded-lg divide-y divide-gray-100">
        <div v-for="course in allCourses" :key="course.id"
          class="flex items-center gap-3 px-3 py-2.5 hover:bg-gray-50 transition-colors"
        >
          <!-- Checkbox -->
          <input
            type="checkbox"
            :checked="isCourseSelected(course.id)"
            class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
            @change="toggleCourse(course)"
          />

          <!-- Info MK -->
          <div class="flex-1 min-w-0">
            <p class="text-sm text-gray-800"><span class="font-mono text-xs text-gray-500 mr-2">{{ course.code }}</span>{{ course.name }}</p>
          </div>

          <!-- SKS -->
          <span class="text-xs text-gray-500 shrink-0">{{ course.credits }} SKS</span>

          <!-- Semester (editable saat diselect) -->
          <select
            v-if="isCourseSelected(course.id)"
            :value="getSelectedCourse(course.id)?.semester"
            class="w-16 px-1.5 py-1 border border-gray-300 rounded text-xs focus:outline-none focus:ring-1 focus:ring-blue-500"
            @change="getSelectedCourse(course.id)!.semester = Number(($event.target as HTMLSelectElement).value)"
          >
            <option v-for="s in 8" :key="s" :value="s">Smt {{ s }}</option>
          </select>

          <!-- Wajib/Pilihan -->
          <select
            v-if="isCourseSelected(course.id)"
            :value="getSelectedCourse(course.id)?.is_required ? '1' : '0'"
            class="w-20 px-1.5 py-1 border border-gray-300 rounded text-xs focus:outline-none focus:ring-1 focus:ring-blue-500"
            @change="getSelectedCourse(course.id)!.is_required = ($event.target as HTMLSelectElement).value === '1'"
          >
            <option value="1">Wajib</option>
            <option value="0">Pilihan</option>
          </select>
        </div>

        <div v-if="allCourses.length === 0" class="text-center py-8 text-gray-400 text-sm">
          Tidak ada mata kuliah di program studi ini. Tambahkan mata kuliah terlebih dahulu.
        </div>
      </div>

      <p class="text-xs text-gray-400">
        Terpilih: <strong class="text-gray-600">{{ selectedCourses.length }}</strong> mata kuliah
      </p>
    </div>

    <template #footer>
      <button class="px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded-lg" @click="courseModalOpen = false">Batal</button>
      <button :disabled="savingCourses" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 disabled:bg-blue-400 text-white text-sm font-medium rounded-lg" @click="saveCourses">
        {{ savingCourses ? 'Menyimpan...' : 'Simpan Mata Kuliah' }}
      </button>
    </template>
  </BaseModal>
</template>
