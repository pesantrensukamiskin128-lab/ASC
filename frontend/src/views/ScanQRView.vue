<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import { useToast } from 'vue-toastification'

const router = useRouter()
const toast = useToast()
const videoRef = ref<HTMLVideoElement | null>(null)
const scanning = ref(false)
const error = ref('')
let stream: MediaStream | null = null
let animationId: number | null = null

onMounted(() => startCamera())
onUnmounted(() => stopCamera())

async function startCamera() {
  error.value = ''
  try {
    stream = await navigator.mediaDevices.getUserMedia({
      video: { facingMode: 'environment' }
    })
    if (videoRef.value) {
      videoRef.value.srcObject = stream
      videoRef.value.play()
      scanning.value = true
      scanLoop()
    }
  } catch (e: any) {
    error.value = 'Tidak bisa mengakses kamera. Pastikan izin kamera sudah diberikan.'
  }
}

function stopCamera() {
  scanning.value = false
  if (animationId) cancelAnimationFrame(animationId)
  if (stream) stream.getTracks().forEach(t => t.stop())
}

function scanLoop() {
  if (!scanning.value || !videoRef.value) return

  const video = videoRef.value
  if (video.readyState !== video.HAVE_ENOUGH_DATA) {
    animationId = requestAnimationFrame(scanLoop)
    return
  }

  const canvas = document.createElement('canvas')
  canvas.width = video.videoWidth
  canvas.height = video.videoHeight
  const ctx = canvas.getContext('2d')!
  ctx.drawImage(video, 0, 0)
  const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height)

  // Gunakan BarcodeDetector API jika tersedia
  if ('BarcodeDetector' in window) {
    const detector = new (window as any).BarcodeDetector({ formats: ['qr_code'] })
    detector.detect(imageData).then((barcodes: any[]) => {
      if (barcodes.length > 0) {
        handleQRResult(barcodes[0].rawValue)
        return
      }
      animationId = requestAnimationFrame(scanLoop)
    }).catch(() => {
      animationId = requestAnimationFrame(scanLoop)
    })
  } else {
    // Fallback: coba setiap 500ms (kurang real-time tapi bisa jalan)
    setTimeout(() => {
      animationId = requestAnimationFrame(scanLoop)
    }, 500)
  }
}

function handleQRResult(data: string) {
  stopCamera()
  toast.success('QR Code terdeteksi!')

  try {
    const url = new URL(data)
    const path = url.pathname

    // Route ke halaman yang sesuai
    if (path.startsWith('/presensi/')) {
      router.push(path)
    } else if (path.startsWith('/verify/')) {
      router.push(path)
    } else {
      // Buka URL langsung jika bukan internal
      window.open(data, '_blank')
    }
  } catch {
    // Bukan URL valid — tampilkan data
    toast.info('Data QR: ' + data)
    scanning.value = false
  }
}

function handleFileInput(e: Event) {
  const file = (e.target as HTMLInputElement).files?.[0]
  if (!file) return

  const img = new Image()
  img.onload = () => {
    const canvas = document.createElement('canvas')
    canvas.width = img.width
    canvas.height = img.height
    const ctx = canvas.getContext('2d')!
    ctx.drawImage(img, 0, 0)
    const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height)

    if ('BarcodeDetector' in window) {
      const detector = new (window as any).BarcodeDetector({ formats: ['qr_code'] })
      detector.detect(imageData).then((barcodes: any[]) => {
        if (barcodes.length > 0) {
          handleQRResult(barcodes[0].rawValue)
        } else {
          toast.error('QR code tidak ditemukan dalam gambar.')
        }
      })
    } else {
      toast.error('Browser tidak mendukung scan QR dari gambar.')
    }
  }
  img.src = URL.createObjectURL(file)
}
</script>

<template>
  <div class="space-y-5 max-w-lg mx-auto">
    <div class="text-center">
      <h1 class="text-xl font-bold text-gray-900">Scan QR Code</h1>
      <p class="text-sm text-gray-500 mt-0.5">Arahkan kamera ke QR Code untuk presensi atau verifikasi dokumen</p>
    </div>

    <!-- Error -->
    <div v-if="error" class="bg-red-50 border border-red-200 rounded-xl p-4 text-center">
      <p class="text-sm text-red-700">{{ error }}</p>
      <button class="mt-2 px-4 py-2 bg-red-600 hover:bg-red-700 text-white text-sm rounded-lg" @click="startCamera">Coba Lagi</button>
    </div>

    <!-- Camera View -->
    <div v-else class="relative rounded-xl overflow-hidden bg-black aspect-square">
      <video ref="videoRef" class="w-full h-full object-cover" playsinline muted />
      <!-- Scan overlay -->
      <div v-if="scanning" class="absolute inset-0 flex items-center justify-center">
        <div class="w-56 h-56 border-2 border-white/60 rounded-2xl relative">
          <div class="absolute top-0 left-0 w-8 h-8 border-t-4 border-l-4 border-blue-400 rounded-tl-lg"></div>
          <div class="absolute top-0 right-0 w-8 h-8 border-t-4 border-r-4 border-blue-400 rounded-tr-lg"></div>
          <div class="absolute bottom-0 left-0 w-8 h-8 border-b-4 border-l-4 border-blue-400 rounded-bl-lg"></div>
          <div class="absolute bottom-0 right-0 w-8 h-8 border-b-4 border-r-4 border-blue-400 rounded-br-lg"></div>
        </div>
      </div>
      <div v-if="scanning" class="absolute bottom-4 left-0 right-0 text-center">
        <span class="bg-black/60 text-white text-xs px-3 py-1.5 rounded-full">Mendeteksi QR Code...</span>
      </div>
    </div>

    <!-- Atau upload gambar -->
    <div class="text-center">
      <p class="text-xs text-gray-400 mb-2">Atau scan dari gambar:</p>
      <label class="inline-flex items-center gap-2 px-4 py-2 border border-gray-300 rounded-lg text-sm text-gray-600 hover:bg-gray-50 cursor-pointer">
        📷 Pilih Gambar QR
        <input type="file" accept="image/*" class="hidden" @change="handleFileInput" />
      </label>
    </div>
  </div>
</template>
