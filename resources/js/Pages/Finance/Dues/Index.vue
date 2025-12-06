<template>
  <AppLayout page-title="Iuran Bulanan">
    <div class="space-y-6">
      <!-- Summary Card -->
      <CardContainer padding="sm">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
          <div>
            <p class="text-xs text-neutral-500">Total Anggota</p>
            <p class="text-2xl font-semibold text-neutral-900">{{ summary.total }}</p>
          </div>
          <div>
            <p class="text-xs text-neutral-500">Sudah Bayar</p>
            <p class="text-lg font-medium text-green-600">{{ summary.paid }}</p>
          </div>
          <div>
            <p class="text-xs text-neutral-500">Belum Bayar</p>
            <p class="text-lg font-medium text-status-warning">{{ summary.unpaid }}</p>
          </div>
          <div>
            <p class="text-xs text-neutral-500">Persentase</p>
            <p class="text-lg font-medium" :class="paidPercentage >= 80 ? 'text-green-600' : 'text-status-warning'">
              {{ paidPercentage.toFixed(1) }}%
            </p>
          </div>
        </div>
      </CardContainer>

      <AlertBanner v-if="$page.props.flash.success" type="success" :message="$page.props.flash.success" dismissible @dismiss="$page.props.flash.success = null" />

      <!-- Filters Card -->
      <CardContainer padding="sm">
        <div class="grid grid-cols-1 md:grid-cols-5 gap-3 items-end">
          <div>
            <label class="block text-xs text-neutral-600">Periode</label>
            <input type="month" v-model="period" class="w-full rounded-md border border-neutral-300 px-3 py-2 text-sm text-neutral-700" />
          </div>
          <div v-if="canSelectUnit">
            <label class="block text-xs text-neutral-600">Unit</label>
            <select v-model="unitId" class="w-full rounded-md border border-neutral-300 px-3 py-2 text-sm text-neutral-700">
              <option value="">Semua Unit</option>
              <option v-for="u in units" :key="u.id" :value="u.id">{{ u.code }} - {{ u.name }}</option>
            </select>
          </div>
          <div>
            <label class="block text-xs text-neutral-600">Cari Nama/KTA</label>
            <input type="text" v-model="search" placeholder="Ketik nama atau KTA..." class="w-full rounded-md border border-neutral-300 px-3 py-2 text-sm text-neutral-700" />
          </div>
          <div>
            <label class="block text-xs text-neutral-600">Status</label>
            <select v-model="status" class="w-full rounded-md border border-neutral-300 px-3 py-2 text-sm text-neutral-700">
              <option value="">Semua</option>
              <option value="paid">Sudah Bayar</option>
              <option value="unpaid">Belum Bayar</option>
            </select>
          </div>
          <div>
            <PrimaryButton @click="applyFilters">Terapkan</PrimaryButton>
          </div>
        </div>
      </CardContainer>

      <!-- Table Card -->
      <CardContainer padding="none" class="overflow-hidden">
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-neutral-200">
            <thead class="bg-neutral-50">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Nama</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">KTA</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Status</th>
                <th class="px-6 py-3 text-right text-xs font-medium text-neutral-500 uppercase tracking-wider">Nominal</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Tanggal Bayar</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Catatan</th>
                <th class="px-6 py-3 text-right text-xs font-medium text-neutral-500 uppercase tracking-wider">Aksi</th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-neutral-200">
              <tr v-for="m in members.data" :key="m.id" class="hover:bg-neutral-50">
                <td class="px-6 py-4 text-sm text-neutral-700">{{ m.full_name }}</td>
                <td class="px-6 py-4 text-sm text-neutral-700">{{ m.kta_number || '-' }}</td>
                <td class="px-6 py-4">
                  <span :class="statusBadgeClass(m.dues_status)" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium">
                    {{ m.dues_status === 'paid' ? 'Sudah Bayar' : 'Belum Bayar' }}
                  </span>
                </td>
                <td class="px-6 py-4 text-sm text-right font-semibold">{{ m.amount ? formatCurrency(m.amount) : '-' }}</td>
                <td class="px-6 py-4 text-sm text-neutral-700">{{ m.paid_at ? formatDate(m.paid_at) : '-' }}</td>
                <td class="px-6 py-4 text-sm text-neutral-700 max-w-xs truncate" :title="m.notes">{{ m.notes || '-' }}</td>
                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                  <button v-if="m.dues_status !== 'paid'" @click="openPayModal(m)" class="inline-flex items-center px-3 py-1.5 text-xs font-medium text-white bg-green-600 rounded hover:bg-green-700">
                    <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    Sudah Bayar
                  </button>
                  <button v-else @click="confirmRevert(m)" class="inline-flex items-center px-3 py-1.5 text-xs font-medium text-neutral-700 bg-neutral-200 rounded hover:bg-neutral-300">
                    <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    Belum Bayar
                  </button>
                </td>
              </tr>
              <tr v-if="members.data.length === 0">
                <td colspan="7" class="px-6 py-10 text-center text-neutral-500">Tidak ada data anggota.</td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <div v-if="members.links.length > 3" class="bg-white px-4 py-3 border-t border-neutral-200 flex items-center justify-between sm:px-6">
          <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
            <div>
              <p class="text-sm text-neutral-700">Menampilkan <span class="font-medium">{{ members.from }}</span> sampai <span class="font-medium">{{ members.to }}</span> dari <span class="font-medium">{{ members.total }}</span> data</p>
            </div>
            <div>
              <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                <template v-for="(link, key) in members.links" :key="key">
                  <component :is="link.url ? 'Link' : 'span'" :href="link.url" v-html="link.label" class="relative inline-flex items-center px-4 py-2 border text-sm font-medium" :class="{ 'z-10 bg-brand-primary-50 border-brand-primary-500 text-brand-primary-600': link.active, 'bg-white border-neutral-300 text-neutral-500 hover:bg-neutral-50': !link.active && link.url, 'bg-neutral-100 border-neutral-300 text-neutral-400 cursor-not-allowed': !link.url }" />
                </template>
              </nav>
            </div>
          </div>
        </div>
      </CardContainer>

      <!-- Pay Modal -->
      <ModalBase v-model:show="showPayModal" title="Konfirmasi Pembayaran Iuran" size="md">
        <div class="space-y-4">
          <p class="text-neutral-600">Tandai iuran bulan <strong>{{ period }}</strong> untuk <strong>{{ selectedMember?.full_name }}</strong> sebagai sudah bayar.</p>
          <div>
            <label class="block text-sm font-medium text-neutral-700 mb-1">Nominal (Rp) *</label>
            <input type="number" v-model.number="payForm.amount" min="1" step="1000" class="w-full rounded-md border border-neutral-300 px-3 py-2 text-sm text-neutral-700" placeholder="Contoh: 50000" />
            <p v-if="payError" class="text-xs text-red-600 mt-1">{{ payError }}</p>
          </div>
          <div>
            <label class="block text-sm font-medium text-neutral-700 mb-1">Catatan (opsional)</label>
            <textarea v-model="payForm.notes" rows="2" class="w-full rounded-md border border-neutral-300 px-3 py-2 text-sm text-neutral-700" placeholder="Catatan tambahan..."></textarea>
          </div>
        </div>
        <template #footer>
          <div class="flex justify-end space-x-3">
            <SecondaryButton @click="showPayModal = false">Batal</SecondaryButton>
            <PrimaryButton @click="submitPay" :loading="submitting">Konfirmasi</PrimaryButton>
          </div>
        </template>
      </ModalBase>

      <!-- Revert Confirmation Modal -->
      <ModalBase v-model:show="showRevertModal" title="Batalkan Pembayaran" size="md">
        <div class="space-y-4">
          <p class="text-neutral-600">Anda yakin ingin menandai iuran bulan <strong>{{ period }}</strong> untuk <strong>{{ selectedMember?.full_name }}</strong> sebagai <strong>Belum Bayar</strong>?</p>
          <p class="text-sm text-neutral-500">Nominal dan catatan pembayaran sebelumnya akan dihapus.</p>
        </div>
        <template #footer>
          <div class="flex justify-end space-x-3">
            <SecondaryButton @click="showRevertModal = false">Batal</SecondaryButton>
            <PrimaryButton class="bg-status-error hover:bg-status-error-dark focus:ring-status-error" @click="submitRevert" :loading="submitting">Batalkan</PrimaryButton>
          </div>
        </template>
      </ModalBase>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { router, Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import CardContainer from '@/Components/UI/CardContainer.vue'
import AlertBanner from '@/Components/UI/AlertBanner.vue'
import ModalBase from '@/Components/UI/ModalBase.vue'
import PrimaryButton from '@/Components/UI/PrimaryButton.vue'
import SecondaryButton from '@/Components/UI/SecondaryButton.vue'

const props = defineProps({
  members: Object,
  filters: Object,
  summary: Object,
  units: Array,
  canSelectUnit: { type: Boolean, default: false },
})

const period = ref(props.filters.period || new Date().toISOString().slice(0, 7))
const search = ref(props.filters.search || '')
const status = ref(props.filters.status || '')
const unitId = ref(props.filters.unit_id || '')

const paidPercentage = computed(() => {
  if (props.summary.total === 0) return 0
  return (props.summary.paid / props.summary.total) * 100
})

// Pay modal state
const showPayModal = ref(false)
const selectedMember = ref(null)
const payForm = ref({ amount: null, notes: '' })
const payError = ref('')
const submitting = ref(false)

// Revert modal state
const showRevertModal = ref(false)

function formatCurrency(n) {
  return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(n || 0)
}

function formatDate(d) {
  if (!d) return '-'
  return new Date(d).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' })
}

function statusBadgeClass(s) {
  return s === 'paid' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'
}

function applyFilters() {
  router.get('/finance/dues', {
    period: period.value,
    search: search.value,
    status: status.value,
    unit_id: unitId.value,
  }, { preserveState: true, replace: true })
}

function openPayModal(member) {
  selectedMember.value = member
  payForm.value = { amount: null, notes: '' }
  payError.value = ''
  showPayModal.value = true
}

function submitPay() {
  if (!payForm.value.amount || payForm.value.amount <= 0) {
    payError.value = 'Nominal harus diisi dan lebih dari 0'
    return
  }
  payError.value = ''
  submitting.value = true
  router.post('/finance/dues/update', {
    member_id: selectedMember.value.id,
    period: period.value,
    status: 'paid',
    amount: payForm.value.amount,
    notes: payForm.value.notes,
  }, {
    preserveScroll: true,
    onSuccess() {
      showPayModal.value = false
      selectedMember.value = null
    },
    onFinish() {
      submitting.value = false
    },
  })
}

function confirmRevert(member) {
  selectedMember.value = member
  showRevertModal.value = true
}

function submitRevert() {
  submitting.value = true
  router.post('/finance/dues/update', {
    member_id: selectedMember.value.id,
    period: period.value,
    status: 'unpaid',
    amount: null,
    notes: null,
  }, {
    preserveScroll: true,
    onSuccess() {
      showRevertModal.value = false
      selectedMember.value = null
    },
    onFinish() {
      submitting.value = false
    },
  })
}
</script>
