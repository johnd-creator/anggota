<template>
  <AppLayout page-title="Transaksi Keuangan">

    <div class="space-y-6">

      <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h2 class="text-lg font-semibold text-neutral-900">Kelola Transaksi</h2>
          <p class="text-sm text-neutral-500">Catat pemasukan dan pengeluaran unit Anda.</p>
        </div>
        <div class="flex flex-wrap gap-3">
          <CtaButton v-if="$page.props.auth.user.role?.name!=='pengurus'" href="/finance/ledgers/create">
            <template #icon>
              <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            </template>
            Tambah Transaksi
          </CtaButton>
        </div>
      </div>

      <CardContainer padding="sm">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
          <div>
            <p class="text-xs text-neutral-500">Saldo <span v-if="workflowEnabled" class="text-neutral-400">(approved)</span></p>
            <p class="text-2xl font-semibold" :class="saldo.balance >= 0 ? 'text-green-600' : 'text-status-error'">{{ formatCurrency(saldo.balance) }}</p>
          </div>
          <div>
            <p class="text-xs text-neutral-500">Pemasukan Bulan Ini <span v-if="workflowEnabled" class="text-neutral-400">(approved)</span></p>
            <p class="text-lg font-medium text-green-600">{{ formatCurrency(summary.income) }}</p>
          </div>
          <div>
            <p class="text-xs text-neutral-500">Pengeluaran Bulan Ini <span v-if="workflowEnabled" class="text-neutral-400">(approved)</span></p>
            <p class="text-lg font-medium text-status-error">{{ formatCurrency(summary.expense) }}</p>
          </div>
          <div>
            <p class="text-xs text-neutral-500">Net Bulan Ini</p>
            <p class="text-lg font-medium" :class="summary.balance >= 0 ? 'text-green-600' : 'text-status-error'">{{ formatCurrency(summary.balance) }}</p>
          </div>
        </div>
        <!-- Pending approval badge for admin_unit -->
        <div v-if="canApprove && pendingCount > 0" class="mt-4 p-3 bg-yellow-50 border border-yellow-200 rounded-lg">
          <p class="text-sm text-yellow-800">
            <svg class="inline w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            Ada <strong>{{ pendingCount }}</strong> transaksi menunggu persetujuan Anda.
          </p>
        </div>
      </CardContainer>

      <CardContainer padding="sm">
        <div class="grid grid-cols-1 md:grid-cols-6 gap-3 items-end">
          <InputField v-model="search" placeholder="Cari deskripsi..." />
          <div>
            <label class="block text-xs text-neutral-600">Tipe</label>
            <select v-model="type" class="w-full rounded-md border border-neutral-300 px-3 py-2 text-sm text-neutral-700">
              <option value="">Semua</option>
              <option value="income">Pemasukan</option>
              <option value="expense">Pengeluaran</option>
            </select>
          </div>
          <div>
            <label class="block text-xs text-neutral-600">Status</label>
            <select v-model="status" class="w-full rounded-md border border-neutral-300 px-3 py-2 text-sm text-neutral-700">
              <option value="">Semua</option>
              <option value="draft">Draft</option>
              <option value="submitted">Submitted</option>
              <option value="approved">Approved</option>
              <option value="rejected">Rejected</option>
            </select>
          </div>
          <div>
            <label class="block text-xs text-neutral-600">Kategori</label>
            <select v-model="categoryId" class="w-full rounded-md border border-neutral-300 px-3 py-2 text-sm text-neutral-700">
              <option value="">Semua</option>
              <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }} ({{ c.type==='income'?'Pemasukan':'Pengeluaran' }})</option>
            </select>
          </div>
          <div>
            <label class="block text-xs text-neutral-600">Dari</label>
            <input type="date" v-model="dateStart" class="w-full rounded-md border border-neutral-300 px-3 py-2 text-sm text-neutral-700" />
          </div>
          <div>
            <label class="block text-xs text-neutral-600">Sampai</label>
            <input type="date" v-model="dateEnd" class="w-full rounded-md border border-neutral-300 px-3 py-2 text-sm text-neutral-700" />
          </div>
        </div>
        <div class="mt-3 flex items-center space-x-2">
          <PrimaryButton @click="applyFilters">Terapkan</PrimaryButton>
          <SecondaryButton @click="exportCsv">Export CSV</SecondaryButton>
        </div>
      </CardContainer>

      <CardContainer padding="none" class="overflow-hidden">
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-neutral-200">
            <thead class="bg-neutral-50">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Tanggal</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Kategori</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Tipe</th>
                <th class="px-6 py-3 text-right text-xs font-medium text-neutral-500 uppercase tracking-wider">Nominal</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Status</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Deskripsi</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Lampiran</th>
                <th class="px-6 py-3 text-right text-xs font-medium text-neutral-500 uppercase tracking-wider">Aksi</th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-neutral-200">
              <tr
                v-for="l in ledgers.data"
                :key="l.id"
                class="hover:bg-neutral-50"
                :class="focusLedgerId && l.id === focusLedgerId ? 'bg-brand-primary-50/60' : ''"
              >
                <td class="px-6 py-4 text-sm text-neutral-700">{{ formatDate(l.date) }}</td>
                <td class="px-6 py-4 text-sm text-neutral-700">{{ l.category ? l.category.name : '-' }}</td>
                <td class="px-6 py-4 text-sm" :class="l.type==='income' ? 'text-green-700' : 'text-status-error'">{{ l.type==='income' ? 'Pemasukan' : 'Pengeluaran' }}</td>
                <td class="px-6 py-4 text-sm text-right font-semibold">{{ formatCurrency(l.amount) }}</td>
                <td class="px-6 py-4">
                  <span :class="statusBadgeClass(l.status)" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium">
                    {{ statusLabel(l.status) }}
                  </span>
                  <p v-if="l.status === 'rejected' && l.rejected_reason" class="text-xs text-red-600 mt-1 max-w-[150px] truncate" :title="l.rejected_reason">
                    {{ l.rejected_reason }}
                  </p>
                </td>
                <td class="px-6 py-4 text-sm text-neutral-700 truncate max-w-xs">{{ l.description || '-' }}</td>
                <td class="px-6 py-4 text-sm">
                  <a v-if="l.attachment_path" :href="`/storage/${l.attachment_path}`" class="text-brand-primary-700 underline" target="_blank">Lihat</a>
                  <span v-else class="text-neutral-400">-</span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                  <div class="flex justify-end space-x-2">
                    <!-- Approve/Reject buttons for admin_unit when status is submitted -->
                    <template v-if="canApprove && l.status === 'submitted'">
                      <button @click="doApprove(l)" class="inline-flex items-center px-2 py-1 text-xs font-medium text-white bg-green-600 rounded hover:bg-green-700">
                        <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        Approve
                      </button>
                      <button @click="openRejectModal(l)" class="inline-flex items-center px-2 py-1 text-xs font-medium text-white bg-red-600 rounded hover:bg-red-700">
                        <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        Reject
                      </button>
                    </template>
                    <!-- Edit button - shown if editable -->
                    <IconButton v-if="canEdit(l)" variant="ghost" aria-label="Edit" @click="router.visit(`/finance/ledgers/${l.id}/edit`)">
                      <svg class="w-5 h-5 text-brand-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                    </IconButton>
                    <!-- Delete button - shown if deletable -->
                    <IconButton v-if="canEdit(l)" variant="ghost" aria-label="Delete" @click="confirmDelete(l)">
                      <svg class="w-5 h-5 text-status-error" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                    </IconButton>
                  </div>
                </td>
              </tr>
              <tr v-if="ledgers.data.length === 0">
                <td colspan="8" class="px-6 py-10 text-center text-neutral-500">Tidak ada transaksi.</td>
              </tr>
            </tbody>
          </table>
        </div>

        <Pagination :paginator="ledgers" />
      </CardContainer>

      <!-- Delete Modal -->
      <ModalBase v-model:show="showDelete" title="Hapus Transaksi" size="md">
        <div class="space-y-4">
          <p class="text-neutral-600">Anda yakin ingin menghapus transaksi ini?</p>
        </div>
        <template #footer>
          <div class="flex justify-end space-x-3">
            <SecondaryButton @click="showDelete = false">Batal</SecondaryButton>
            <PrimaryButton class="bg-status-error hover:bg-status-error-dark focus:ring-status-error" @click="doDelete" :loading="deleting">Hapus</PrimaryButton>
          </div>
        </template>
      </ModalBase>

      <!-- Reject Modal -->
      <ModalBase v-model:show="showReject" title="Tolak Transaksi" size="md">
        <div class="space-y-4">
          <p class="text-neutral-600">Berikan alasan penolakan:</p>
          <textarea v-model="rejectReason" rows="3" class="w-full rounded-md border border-neutral-300 px-3 py-2 text-sm text-neutral-700" placeholder="Alasan penolakan..."></textarea>
        </div>
        <template #footer>
          <div class="flex justify-end space-x-3">
            <SecondaryButton @click="showReject = false; rejectReason = ''">Batal</SecondaryButton>
            <PrimaryButton class="bg-status-error hover:bg-status-error-dark focus:ring-status-error" @click="doReject" :loading="rejecting" :disabled="!rejectReason.trim()">Tolak</PrimaryButton>
          </div>
        </template>
      </ModalBase>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref } from 'vue'
import { router, Link, useForm, usePage } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import CardContainer from '@/Components/UI/CardContainer.vue'
import InputField from '@/Components/UI/InputField.vue'
import IconButton from '@/Components/UI/IconButton.vue'
	import ModalBase from '@/Components/UI/ModalBase.vue'
	import PrimaryButton from '@/Components/UI/PrimaryButton.vue'
	import SecondaryButton from '@/Components/UI/SecondaryButton.vue'
	import CtaButton from '@/Components/UI/CtaButton.vue'
	import Pagination from '@/Components/UI/Pagination.vue'

const props = defineProps({
  ledgers: Object,
  filters: Object,
  units: Array,
  categories: Array,
  summary: Object,
  saldo: Object,
  workflowEnabled: { type: Boolean, default: true },
  pendingCount: { type: Number, default: 0 },
  canApprove: { type: Boolean, default: false },
  focusLedgerId: { type: Number, default: null },
})

const page = usePage()

const search = ref(props.filters.search || '')
const type = ref(props.filters.type || '')
const status = ref(props.filters.status || '')
const categoryId = ref(props.filters.category_id || '')
const dateStart = ref(props.filters.date_start || '')
const dateEnd = ref(props.filters.date_end || '')

// Delete modal
const showDelete = ref(false)
const toDelete = ref(null)
const deleting = ref(false)

// Reject modal
const showReject = ref(false)
const toReject = ref(null)
const rejectReason = ref('')
const rejecting = ref(false)

function formatCurrency(n) {
  return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(n || 0)
}

function formatDate(dateStr) {
  if (!dateStr) return '-'
  const d = new Date(dateStr)
  if (Number.isNaN(d.getTime())) return dateStr
  return d.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' })
}

function applyFilters() {
  router.get('/finance/ledgers', {
    search: search.value,
    type: type.value,
    status: status.value,
    category_id: categoryId.value,
    date_start: dateStart.value,
    date_end: dateEnd.value,
  }, { preserveState: true, replace: true })
}

function exportCsv() {
  const q = new URLSearchParams({
    search: search.value || '',
    type: type.value || '',
    status: status.value || '',
    category_id: categoryId.value || '',
    date_start: dateStart.value || '',
    date_end: dateEnd.value || '',
  })
  window.location.href = `/finance/ledgers/export?${q.toString()}`
}

function statusBadgeClass(s) {
  switch (s) {
    case 'draft': return 'bg-neutral-100 text-neutral-800'
    case 'submitted': return 'bg-yellow-100 text-yellow-800'
    case 'approved': return 'bg-green-100 text-green-800'
    case 'rejected': return 'bg-red-100 text-red-800'
    default: return 'bg-neutral-100 text-neutral-800'
  }
}

function statusLabel(s) {
  switch (s) {
    case 'draft': return 'Draft'
    case 'submitted': return 'Menunggu'
    case 'approved': return 'Disetujui'
    case 'rejected': return 'Ditolak'
    default: return s
  }
}

function canEdit(ledger) {
  // For simplicity, show edit/delete if status is draft or submitted
  // Backend policy will enforce the actual permission
  // pengurus role cannot edit/delete (read-only)
  if (page.props.auth.user.role?.name === 'pengurus') return false
  if (!props.workflowEnabled) return true
  return ['draft', 'submitted'].includes(ledger.status)
}

function confirmDelete(l) {
  toDelete.value = l
  showDelete.value = true
}

function doDelete() {
  if (!toDelete.value) return
  deleting.value = true
  router.delete(`/finance/ledgers/${toDelete.value.id}`, {
    onSuccess() {
      showDelete.value = false
      toDelete.value = null
    },
    onFinish() {
      deleting.value = false
    },
  })
}

function doApprove(ledger) {
  router.post(`/finance/ledgers/${ledger.id}/approve`, {}, {
    preserveScroll: true,
  })
}

function openRejectModal(ledger) {
  toReject.value = ledger
  rejectReason.value = ''
  showReject.value = true
}

function doReject() {
  if (!toReject.value || !rejectReason.value.trim()) return
  rejecting.value = true
  router.post(`/finance/ledgers/${toReject.value.id}/reject`, {
    rejected_reason: rejectReason.value,
  }, {
    preserveScroll: true,
    onSuccess() {
      showReject.value = false
      toReject.value = null
      rejectReason.value = ''
    },
    onFinish() {
      rejecting.value = false
    },
  })
}
</script>
