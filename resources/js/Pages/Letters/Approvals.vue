<template>
  <AppLayout page-title="Perlu Persetujuan">
    <div class="space-y-6">
      <AlertBanner v-if="$page.props.flash?.success" type="success" :message="$page.props.flash.success" dismissible />

      <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h2 class="text-lg font-semibold text-neutral-900">Perlu Persetujuan</h2>
          <p class="text-sm text-neutral-500">Surat yang menunggu persetujuan Anda.</p>
        </div>
      </div>

      <!-- Stats -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <SummaryCard title="Menunggu Persetujuan" :value="stats.pending" color="yellow" icon="clock" />
        <SummaryCard title="Disetujui Bulan Ini" :value="stats.approved" color="green" icon="check" />
        <SummaryCard title="Perlu Revisi / Ditolak" :value="stats.rejected" color="red" icon="x" />
      </div>

      <!-- Filters -->
      <CardContainer padding="sm">
        <div class="flex flex-wrap items-center gap-3">
          <div class="w-full max-w-md">
            <InputField v-model="search" placeholder="Cari perihal surat..." class="w-full" />
          </div>
          <div>
            <select v-model="categoryId" class="rounded-md border border-neutral-300 px-3 py-2 text-sm text-neutral-700">
              <option value="">Semua Kategori</option>
              <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.code }} - {{ cat.name }}</option>
            </select>
          </div>
        </div>
      </CardContainer>

      <!-- Letters Table -->
      <CardContainer padding="none" class="overflow-hidden">
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-neutral-200">
            <thead class="bg-neutral-50">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Kategori</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Perihal</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Dari</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Penandatangan</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Diajukan</th>
                <th class="px-6 py-3 text-right text-xs font-medium text-neutral-500 uppercase tracking-wider">Aksi</th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-neutral-200">
              <tr v-for="letter in letters.data" :key="letter.id" class="hover:bg-neutral-50">
                <td class="px-6 py-4 text-sm text-neutral-900">
                  <div class="flex flex-wrap items-center gap-2">
                    <ColorBadge :color="letter.category?.color || 'neutral'" :dot="true">
                      {{ letter.category?.code || '-' }}
                    </ColorBadge>
                    <StatusBadge :status="letter.confidentiality" size="sm" :showDot="false" />
                    <StatusBadge :status="letter.urgency" size="sm" :showDot="false" />
                  </div>
                </td>
                <td class="px-6 py-4 text-sm font-medium text-neutral-900">{{ letter.subject }}</td>
                <td class="px-6 py-4 text-sm text-neutral-600">{{ letter.from_unit?.name || 'Pusat' }}</td>
                <td class="px-6 py-4 text-sm text-neutral-600 capitalize">{{ letter.signer_type }}</td>
                <td class="px-6 py-4 text-sm text-neutral-600">{{ formatDate(letter.submitted_at) }}</td>
                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                  <div class="flex justify-end space-x-2">
                    <Link :href="`/letters/${letter.id}`" class="text-brand-primary-600 hover:text-brand-primary-700">Detail</Link>
                    <button @click="approveModal(letter)" class="text-green-600 hover:text-green-700">Setujui</button>
                    <button @click="revisionModal(letter)" class="text-yellow-600 hover:text-yellow-700">Revisi</button>
                    <button @click="rejectModal(letter)" class="text-red-600 hover:text-red-700">Tolak</button>
                  </div>
                </td>
              </tr>
              <tr v-if="letters.data.length === 0">
                <td colspan="6" class="px-6 py-10 text-center text-neutral-500">Tidak ada surat yang menunggu persetujuan.</td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <!-- Pagination -->
        <Pagination :paginator="letters" />
      </CardContainer>
    </div>

    <!-- Approve Modal -->
    <ModalBase v-model:show="showApprove" title="Setujui Surat" size="md">
      <p class="text-neutral-600">Anda yakin ingin menyetujui surat "<span class="font-medium">{{ selected?.subject }}</span>"?</p>
      <p class="text-sm text-neutral-500 mt-2">Nomor surat akan digenerate secara otomatis.</p>
      <template #footer>
        <div class="flex justify-end space-x-3">
          <SecondaryButton @click="showApprove = false">Batal</SecondaryButton>
          <PrimaryButton class="bg-green-600 hover:bg-green-700" @click="doApprove" :loading="processing">Setujui</PrimaryButton>
        </div>
      </template>
    </ModalBase>

    <!-- Revision Modal -->
    <ModalBase v-model:show="showRevision" title="Minta Revisi" size="md">
      <div class="space-y-4">
        <p class="text-neutral-600">Minta revisi untuk surat "<span class="font-medium">{{ selected?.subject }}</span>"</p>
        <div>
          <label class="block text-sm font-medium text-neutral-700 mb-1">Catatan Revisi *</label>
          <textarea v-model="revisionNote" rows="4" class="w-full rounded-md border border-neutral-300 px-3 py-2 text-sm" placeholder="Jelaskan apa yang perlu diperbaiki..."></textarea>
        </div>
      </div>
      <template #footer>
        <div class="flex justify-end space-x-3">
          <SecondaryButton @click="showRevision = false">Batal</SecondaryButton>
          <PrimaryButton class="bg-yellow-600 hover:bg-yellow-700" @click="doRevision" :loading="processing" :disabled="!revisionNote.trim()">Minta Revisi</PrimaryButton>
        </div>
      </template>
    </ModalBase>

    <!-- Reject Modal -->
    <ModalBase v-model:show="showReject" title="Tolak Surat" size="md">
      <div class="space-y-4">
        <p class="text-neutral-600">Tolak surat "<span class="font-medium">{{ selected?.subject }}</span>"</p>
        <div>
          <label class="block text-sm font-medium text-neutral-700 mb-1">Alasan Penolakan *</label>
          <textarea v-model="rejectNote" rows="4" class="w-full rounded-md border border-neutral-300 px-3 py-2 text-sm" placeholder="Jelaskan alasan penolakan..."></textarea>
        </div>
      </div>
      <template #footer>
        <div class="flex justify-end space-x-3">
          <SecondaryButton @click="showReject = false">Batal</SecondaryButton>
          <PrimaryButton class="bg-red-600 hover:bg-red-700" @click="doReject" :loading="processing" :disabled="!rejectNote.trim()">Tolak</PrimaryButton>
        </div>
      </template>
    </ModalBase>
  </AppLayout>
</template>
 
<script setup>
import { ref, watch } from 'vue'
import { router, Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import CardContainer from '@/Components/UI/CardContainer.vue'
import InputField from '@/Components/UI/InputField.vue'
import AlertBanner from '@/Components/UI/AlertBanner.vue'
import ModalBase from '@/Components/UI/ModalBase.vue'
import PrimaryButton from '@/Components/UI/PrimaryButton.vue'
import SecondaryButton from '@/Components/UI/SecondaryButton.vue'
import StatusBadge from '@/Components/UI/StatusBadge.vue'
import ColorBadge from '@/Components/UI/ColorBadge.vue'
import Pagination from '@/Components/UI/Pagination.vue'
import SummaryCard from '@/Components/UI/SummaryCard.vue'

const props = defineProps({
  letters: Object,
  categories: Array,
  filters: Object,
  stats: Object,
})

const search = ref(props.filters?.search || '')
const categoryId = ref(props.filters?.category_id || '')

const showApprove = ref(false)
const showRevision = ref(false)
const showReject = ref(false)
const selected = ref(null)
const revisionNote = ref('')
const rejectNote = ref('')
const processing = ref(false)

watch([search, categoryId], ([s, cat]) => {
  router.get('/letters/approvals', { search: s, category_id: cat }, { preserveState: true, replace: true })
})

function formatDate(dateStr) {
  if (!dateStr) return '-'
  return new Date(dateStr).toLocaleDateString('id-ID', { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' })
}

function approveModal(letter) {
  selected.value = letter
  showApprove.value = true
}

function revisionModal(letter) {
  selected.value = letter
  revisionNote.value = ''
  showRevision.value = true
}

function rejectModal(letter) {
  selected.value = letter
  rejectNote.value = ''
  showReject.value = true
}

function doApprove() {
  if (!selected.value) return
  processing.value = true
  router.post(`/letters/${selected.value.id}/approve`, {}, {
    onSuccess() {
      showApprove.value = false
      selected.value = null
    },
    onFinish() { processing.value = false }
  })
}

function doRevision() {
  if (!selected.value || !revisionNote.value.trim()) return
  processing.value = true
  router.post(`/letters/${selected.value.id}/revise`, { note: revisionNote.value }, {
    onSuccess() {
      showRevision.value = false
      selected.value = null
      revisionNote.value = ''
    },
    onFinish() { processing.value = false }
  })
}

function doReject() {
  if (!selected.value || !rejectNote.value.trim()) return
  processing.value = true
  router.post(`/letters/${selected.value.id}/reject`, { note: rejectNote.value }, {
    onSuccess() {
      showReject.value = false
      selected.value = null
      rejectNote.value = ''
    },
    onFinish() { processing.value = false }
  })
}
</script>
