<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useToast } from 'vue-toastification'
import { CurrencyDollarIcon, CheckCircleIcon, ClockIcon, ExclamationTriangleIcon } from '@heroicons/vue/24/outline'
import BaseModal from '@/components/ui/BaseModal.vue'
import api from '@/services/api'

const toast = useToast()
const loading = ref(true)
const activeTab = ref<'tagihan' | 'riwayat'>('tagihan')

const summary = ref<any>(null)
const invoices = ref<any[]>([])
const payments = ref<any[]>([])

// Payment form
const payModal = ref(false)
const paying = ref(false)
const selectedInvoice = ref<any>(null)
const payForm = ref({
  amount: 0, payment_method: 'TRANSFER', payment_date: new Date().toISOString().slice(0, 10),
  reference_number: '', bank_name: '', account_name: '', note: '',
})
const receiptFile = ref<File | null>(null)
const receiptPreview = ref<string | null>(null)
const showReceiptCamera = ref(false)
const receiptVideoRef = ref<HTMLVideoElement | null>(null)
const receiptStream = ref<MediaStream | null>(null)

const statusColor: Record<string, string> = {
  UNPAID: 'bg-red-100 text-red-700', PARTIAL: 'bg-yellow-100 text-yellow-700',
  PAID: 'bg-green-100 text-green-700', OVERDUE: 'bg-red-200 text-red-800',
  CANCELLED: 'bg-gray-100 text-gray-400', WAIVED: 'bg-blue-100 text-blue-600',
}
const payStatusColor: Record<string, string> = {
  PENDING: 'bg-yellow-100 text-yellow-700', VERIFIED: 'bg-green-100 text-green-700',
  REJECTED: 'bg-red-100 text-red-700',
}

onMounted(async () => {
  try {
    const [sumRes, invRes, payRes] = await Promise.all([
      api.get('/finance/my-summary'),
      api.get('/finance/my-invoices'),
      api.get('/finance/my-payments'),
    ])
    summary.value = sumRes.data
    invoices.value = invRes.data?.data ?? invRes.data ?? []
    payments.value = payRes.data?.data ?? payRes.data ?? []
  } catch { toast.error('Gagal memuat data keuangan.') }
  finally { loading.value = false }
})

const unpaidInvoices = computed(() => invoices.value.filter((i: any) => ['UNPAID', 'PARTIAL', 'OVERDUE'].includes(i.status)))

function openPay(invoice: any) {
  selectedInvoice.value = invoice
  const outstanding = invoice.total_amount - (invoice.discount_amount || 0) - (invoice.scholarship_amount || 0) - (invoice.paid_amount || 0)
  payForm.value = {
    amount: Math.max(0, outstanding),
    payment_method: 'TRANSFER',
    payment_date: new Date().toISOString().slice(0, 10),
    reference_number: '', bank_name: '', account_name: '', note: '',
  }
  receiptFile.value = null
  payModal.value = true
}

function onReceiptFile(e: Event) {
  const file = (e.target as HTMLInputElement).files?.[0] ?? null
  receiptFile.value = file
  receiptPreview.value = file ? URL.createObjectURL(file) : null
}

async function openReceiptCamera() {
  try {
    const stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } })
    receiptStream.value = stream
    showReceiptCamera.value = true
    setTimeout(() => {
      if (receiptVideoRef.value) {
        receiptVideoRef.value.srcObject = stream
        receiptVideoRef.value.play()
      }
    }, 100)
  } catch (err: any) {
    toast.error('Tidak bisa mengakses kamera: ' + (err.message || 'Permission denied'))
  }
}

function captureReceipt() {
  if (!receiptVideoRef.value) return
  const canvas = document.createElement('canvas')
  canvas.width = receiptVideoRef.value.videoWidth
  canvas.height = receiptVideoRef.value.videoHeight
  canvas.getContext('2d')?.drawImage(receiptVideoRef.value, 0, 0)
  canvas.toBlob((blob) => {
    if (!blob) return
    const file = new File([blob], `bukti-bayar-${Date.now()}.jpg`, { type: 'image/jpeg' })
    receiptFile.value = file
    receiptPreview.value = URL.createObjectURL(file)
    closeReceiptCamera()
  }, 'image/jpeg', 0.85)
}

function closeReceiptCamera() {
  if (receiptStream.value) {
    receiptStream.value.getTracks().forEach(t => t.stop())
    receiptStream.value = null
  }
  showReceiptCamera.value = false
}

async function submitPayment() {
  if (!selectedInvoice.value) return
  paying.value = true
  try {
    const fd = new FormData()
    fd.append('invoice_id', selectedInvoice.value.id)
    fd.append('amount', String(payForm.value.amount))
    fd.append('payment_method', payForm.value.payment_method)
    fd.append('payment_date', payForm.value.payment_date)
    if (payForm.value.reference_number) fd.append('reference_number', payForm.value.reference_number)
    if (payForm.value.bank_name) fd.append('bank_name', payForm.value.bank_name)
    if (payForm.value.account_name) fd.append('account_name', payForm.value.account_name)
    if (payForm.value.note) fd.append('note', payForm.value.note)
    if (receiptFile.value) fd.append('receipt', receiptFile.value)

    await api.post('/finance/my-payment', fd, { headers: { 'Content-Type': 'multipart/form-data' } })
    toast.success('Pembayaran berhasil dikirim! Menunggu verifikasi admin.')
    payModal.value = false
    // Reload
    const [invRes, payRes] = await Promise.all([
      api.get('/finance/my-invoices'),
      api.get('/finance/my-payments'),
    ])
    invoices.value = invRes.data?.data ?? invRes.data ?? []
    payments.value = payRes.data?.data ?? payRes.data ?? []
  } catch (e: any) { toast.error(e?.response?.data?.message ?? 'Gagal mengirim pembayaran.') }
  finally { paying.value = false }
}

function formatCurrency(n: number) {
  return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(n ?? 0)
}
function formatDate(d: string) {
  return d ? new Date(d).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' }) : '-'
}
</script>

<template>
  <div class="space-y-6">
    <div>
      <h1 class="text-xl font-bold text-gray-900">Keuangan Saya</h1>
      <p class="text-sm text-gray-500 mt-0.5">Lihat tagihan dan riwayat pembayaran</p>
    </div>

    <div v-if="loading" class="flex items-center justify-center h-48"><p class="text-gray-400">Memuat...</p></div>

    <template v-else>
      <!-- Summary Cards -->
      <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-white rounded-xl border border-gray-200 p-4">
          <CurrencyDollarIcon class="w-5 h-5 text-blue-500 mb-1" />
          <p class="text-lg font-bold text-gray-900">{{ formatCurrency(summary?.total_invoiced ?? 0) }}</p>
          <p class="text-xs text-gray-500">Total Tagihan</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-4">
          <CheckCircleIcon class="w-5 h-5 text-green-500 mb-1" />
          <p class="text-lg font-bold text-green-700">{{ formatCurrency(summary?.total_paid ?? 0) }}</p>
          <p class="text-xs text-gray-500">Sudah Dibayar</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-4">
          <ExclamationTriangleIcon class="w-5 h-5 text-red-500 mb-1" />
          <p class="text-lg font-bold text-red-700">{{ formatCurrency(summary?.outstanding ?? 0) }}</p>
          <p class="text-xs text-gray-500">Sisa Tunggakan</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-4">
          <ClockIcon class="w-5 h-5 text-purple-500 mb-1" />
          <p class="text-lg font-bold text-purple-700">{{ formatCurrency(summary?.total_scholarship ?? 0) }}</p>
          <p class="text-xs text-gray-500">Beasiswa</p>
        </div>
      </div>

      <!-- Tabs -->
      <div class="flex gap-2 border-b border-gray-200">
        <button :class="['px-4 py-2 text-sm font-medium border-b-2 -mb-px', activeTab === 'tagihan' ? 'border-blue-600 text-blue-700' : 'border-transparent text-gray-500 hover:text-gray-700']" @click="activeTab = 'tagihan'">
          Tagihan Aktif ({{ unpaidInvoices.length }})
        </button>
        <button :class="['px-4 py-2 text-sm font-medium border-b-2 -mb-px', activeTab === 'riwayat' ? 'border-blue-600 text-blue-700' : 'border-transparent text-gray-500 hover:text-gray-700']" @click="activeTab = 'riwayat'">
          Riwayat Pembayaran ({{ payments.length }})
        </button>
      </div>

      <!-- Tab: Tagihan -->
      <div v-if="activeTab === 'tagihan'" class="space-y-3">
        <div v-if="!unpaidInvoices.length" class="bg-green-50 border border-green-200 rounded-xl p-6 text-center">
          <CheckCircleIcon class="w-10 h-10 text-green-500 mx-auto mb-2" />
          <p class="text-sm font-medium text-green-800">Tidak ada tagihan yang belum lunas. Keuangan Anda clear!</p>
        </div>
        <div v-for="inv in unpaidInvoices" :key="inv.id" class="bg-white rounded-xl border border-gray-200 p-5">
          <div class="flex items-start justify-between">
            <div>
              <p class="font-medium text-gray-900">{{ inv.invoice_number }}</p>
              <p class="text-xs text-gray-500 mt-0.5">{{ inv.semester?.name ?? '-' }} · Jatuh tempo: {{ formatDate(inv.due_date) }}</p>
            </div>
            <span :class="['px-2 py-0.5 rounded-full text-xs font-medium', statusColor[inv.status]]">{{ inv.status }}</span>
          </div>
          <div class="mt-3 grid grid-cols-3 gap-3 text-sm">
            <div><span class="text-xs text-gray-400">Total</span><p class="font-bold">{{ formatCurrency(inv.total_amount) }}</p></div>
            <div><span class="text-xs text-gray-400">Diskon/Beasiswa</span><p class="text-green-700">-{{ formatCurrency((inv.discount_amount || 0) + (inv.scholarship_amount || 0)) }}</p></div>
            <div><span class="text-xs text-gray-400">Sisa Bayar</span><p class="font-bold text-red-700">{{ formatCurrency(inv.total_amount - (inv.discount_amount || 0) - (inv.scholarship_amount || 0) - (inv.paid_amount || 0)) }}</p></div>
          </div>
          <button class="mt-3 w-full px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg" @click="openPay(inv)">
            Bayar Sekarang
          </button>
        </div>
      </div>

      <!-- Tab: Riwayat -->
      <div v-if="activeTab === 'riwayat'" class="space-y-3">
        <div v-if="!payments.length" class="text-center text-gray-400 py-8 text-sm">Belum ada riwayat pembayaran.</div>
        <div v-for="pay in payments" :key="pay.id" class="bg-white rounded-xl border border-gray-200 p-4 flex items-center justify-between">
          <div>
            <p class="text-sm font-medium text-gray-900">{{ pay.payment_number }}</p>
            <p class="text-xs text-gray-500">{{ pay.payment_method }} · {{ formatDate(pay.payment_date) }}</p>
            <p v-if="pay.reference_number" class="text-xs text-gray-400 font-mono">Ref: {{ pay.reference_number }}</p>
          </div>
          <div class="text-right">
            <p class="font-bold text-gray-900">{{ formatCurrency(pay.amount) }}</p>
            <span :class="['px-2 py-0.5 rounded-full text-xs font-medium', payStatusColor[pay.status]]">{{ pay.status }}</span>
          </div>
        </div>
      </div>
    </template>
  </div>

  <!-- Modal Bayar -->
  <BaseModal :open="payModal" title="Konfirmasi Pembayaran" size="lg" @close="payModal = false">
    <form class="space-y-4" @submit.prevent="submitPayment">
      <div class="bg-blue-50 border border-blue-200 rounded-lg p-3">
        <p class="text-xs text-blue-700 font-medium">Tagihan: {{ selectedInvoice?.invoice_number }}</p>
        <p class="text-sm font-bold text-blue-900 mt-0.5">Sisa bayar: {{ formatCurrency(selectedInvoice ? selectedInvoice.total_amount - (selectedInvoice.discount_amount || 0) - (selectedInvoice.scholarship_amount || 0) - (selectedInvoice.paid_amount || 0) : 0) }}</p>
      </div>

      <div class="grid grid-cols-2 gap-3">
        <div><label class="text-xs font-medium text-gray-700">Jumlah Bayar (Rp) *</label><input v-model.number="payForm.amount" type="number" required min="1" class="w-full mt-1 px-3 py-2 border rounded-lg text-sm" /></div>
        <div><label class="text-xs font-medium text-gray-700">Tanggal Bayar *</label><input v-model="payForm.payment_date" type="date" required class="w-full mt-1 px-3 py-2 border rounded-lg text-sm" /></div>
      </div>
      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="text-xs font-medium text-gray-700">Metode Pembayaran *</label>
          <select v-model="payForm.payment_method" required class="w-full mt-1 px-3 py-2 border rounded-lg text-sm">
            <option value="TRANSFER">Transfer Bank</option>
            <option value="CASH">Tunai</option>
            <option value="VA">Virtual Account</option>
            <option value="QRIS">QRIS</option>
            <option value="LAINNYA">Lainnya</option>
          </select>
        </div>
        <div><label class="text-xs font-medium text-gray-700">No. Referensi / Bukti Transfer</label><input v-model="payForm.reference_number" placeholder="Contoh: 12345678" class="w-full mt-1 px-3 py-2 border rounded-lg text-sm" /></div>
      </div>
      <div class="grid grid-cols-2 gap-3">
        <div><label class="text-xs font-medium text-gray-700">Nama Bank</label><input v-model="payForm.bank_name" placeholder="BCA, Mandiri, BSI, dll" class="w-full mt-1 px-3 py-2 border rounded-lg text-sm" /></div>
        <div><label class="text-xs font-medium text-gray-700">Nama Pemilik Rekening</label><input v-model="payForm.account_name" class="w-full mt-1 px-3 py-2 border rounded-lg text-sm" /></div>
      </div>
      <div>
        <label class="text-xs font-medium text-gray-700">Upload Bukti Pembayaran</label>
        <div class="mt-2 border border-gray-200 rounded-lg p-3 space-y-2">
          <!-- Camera preview -->
          <div v-if="showReceiptCamera" class="space-y-2">
            <div class="bg-black rounded-lg overflow-hidden" style="max-height:200px;">
              <video ref="receiptVideoRef" autoplay playsinline class="w-full" style="max-height:200px; object-fit:cover;"></video>
            </div>
            <div class="flex gap-2">
              <button type="button" class="flex-1 px-3 py-2 bg-green-600 hover:bg-green-700 text-white text-xs font-medium rounded-lg" @click="captureReceipt">✓ Ambil Foto</button>
              <button type="button" class="px-3 py-2 bg-gray-200 hover:bg-gray-300 text-gray-700 text-xs rounded-lg" @click="closeReceiptCamera">Batal</button>
            </div>
          </div>

          <!-- Result / buttons -->
          <div v-else class="flex items-center gap-3">
            <div v-if="receiptPreview" class="w-20 h-20 rounded-lg border overflow-hidden shrink-0">
              <img :src="receiptPreview" class="w-full h-full object-cover" />
            </div>
            <div class="flex flex-col gap-2">
              <div class="flex gap-2">
                <button type="button" class="px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-medium rounded-lg" @click="openReceiptCamera">📷 Foto Bukti</button>
                <label class="px-3 py-1.5 bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-medium rounded-lg cursor-pointer border border-gray-300">
                  📁 Pilih File
                  <input type="file" accept="image/*,.pdf" class="hidden" @change="onReceiptFile" />
                </label>
              </div>
              <p class="text-[10px] text-gray-400">JPG, PNG, PDF. Maks 5MB.</p>
            </div>
          </div>
        </div>
      </div>
      <div><label class="text-xs font-medium text-gray-700">Catatan (opsional)</label><textarea v-model="payForm.note" rows="2" class="w-full mt-1 px-3 py-2 border rounded-lg text-sm" /></div>
    </form>
    <template #footer>
      <button class="px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded-lg" @click="payModal = false">Batal</button>
      <button :disabled="paying" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 disabled:bg-blue-400 text-white text-sm font-medium rounded-lg" @click="submitPayment">
        {{ paying ? 'Mengirim...' : 'Kirim Pembayaran' }}
      </button>
    </template>
  </BaseModal>
</template>
