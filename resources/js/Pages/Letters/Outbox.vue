<template>
  <AppLayout page-title="Surat Keluar">
    <div class="space-y-6">
      <AlertBanner v-if="$page.props.flash?.success" type="success" :message="$page.props.flash.success" dismissible />
      <AlertBanner v-if="$page.props.errors?.letter" type="error" :message="$page.props.errors.letter" dismissible />

      <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h2 class="text-lg font-semibold text-neutral-900">Surat Keluar</h2>
          <p class="text-sm text-neutral-500">Surat yang Anda buat.</p>
        </div>
        <div class="flex flex-wrap gap-3">
          <CtaButton href="/letters/create">
            <template #icon>
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            </template>
            Buat Surat
          </CtaButton>
        </div>
      </div>

      <!-- Filters -->
      <CardContainer padding="sm">
        <div class="flex flex-wrap items-center gap-3">
          <div class="w-full max-w-md">
            <InputField v-model="search" placeholder="Cari perihal surat..." class="w-full" />
          </div>
          <div>
            <select v-model="status" class="rounded-md border border-neutral-300 px-3 py-2 text-sm text-neutral-700">
              <option value="">Semua Status</option>
              <option value="draft">Draft</option>
              <option value="submitted">Diajukan</option>
              <option value="revision">Revisi</option>
              <option value="approved">Disetujui</option>
              <option value="sent">Terkirim</option>
              <option value="rejected">Ditolak</option>
            </select>
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
                <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">No. Surat</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Kategori</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Perihal</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Kepada</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Status</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Tanggal</th>
                <th class="px-6 py-3 text-right text-xs font-medium text-neutral-500 uppercase tracking-wider">Aksi</th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-neutral-200">
              <tr v-for="letter in letters.data" :key="letter.id" class="hover:bg-neutral-50">
                <td class="px-6 py-4 text-sm font-mono text-neutral-600">{{ letter.letter_number || '-' }}</td>
                <td class="px-6 py-4 text-sm text-neutral-900">
                  <ColorBadge :color="letter.category?.color || 'neutral'" :dot="true">
                    {{ letter.category?.code || '-' }}
                  </ColorBadge>
                </td>
                <td class="px-6 py-4 text-sm font-medium text-neutral-900">{{ letter.subject }}</td>
                <td class="px-6 py-4 text-sm text-neutral-600">{{ getRecipient(letter) }}</td>
                <td class="px-6 py-4">
                  <div class="flex flex-wrap items-center gap-2">
                    <StatusBadge :status="letter.status" />
                    <StatusBadge :status="letter.confidentiality" size="sm" :showDot="false" />
                    <StatusBadge :status="letter.urgency" size="sm" :showDot="false" />
                  </div>
                </td>
                <td class="px-6 py-4 text-sm text-neutral-600">{{ formatDate(letter.created_at) }}</td>
                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                  <div class="flex justify-end items-center gap-2">
                    <IconButton
                      variant="ghost"
                      aria-label="Preview"
                      @click="router.visit(`/letters/${letter.id}/preview`)"
                      title="Preview"
                    >
                      <svg class="w-5 h-5 text-neutral-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                      </svg>
                    </IconButton>
                    <IconButton
                      variant="ghost"
                      aria-label="Detail"
                      @click="router.visit(`/letters/${letter.id}`)"
                      title="Detail"
                    >
                      <svg class="w-5 h-5 text-brand-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                      </svg>
                    </IconButton>
                    <template v-if="canEdit(letter)">
                      <IconButton
                        variant="ghost"
                        aria-label="Edit"
                        @click="router.visit(`/letters/${letter.id}/edit`)"
                        title="Edit"
                      >
                        <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2-2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                        </svg>
                      </IconButton>
                      <IconButton
                        variant="ghost"
                        aria-label="Hapus"
                        @click="confirmDelete(letter)"
                        title="Hapus"
                      >
                        <svg class="w-5 h-5 text-status-error" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                      </IconButton>
                    </template>
                    <IconButton
                      v-if="canSubmit(letter)"
                      variant="outline"
                      aria-label="Ajukan"
                      @click="submitLetter(letter)"
                      title="Ajukan"
                      size="sm"
                      class="ml-1"
                    >
                      <svg class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                      </svg>
                      <span class="ml-1 text-xs text-green-600 font-medium">Ajukan</span>
                    </IconButton>
                  </div>
                </td>
              </tr>
              <tr v-if="letters.data.length === 0">
                <td colspan="7" class="px-6 py-10 text-center text-neutral-500">Tidak ada surat keluar.</td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <!-- Pagination -->
        <Pagination :paginator="letters" />
      </CardContainer>
    </div>

    <!-- Delete Modal -->
    <ModalBase v-model:show="showDelete" title="Hapus Surat" size="md">
      <div class="space-y-4">
        <p class="text-neutral-600">Anda yakin ingin menghapus surat <span class="font-semibold">"{{ toDelete?.subject }}"</span>?</p>
      </div>
      <template #footer>
        <div class="flex justify-end space-x-3">
          <SecondaryButton @click="showDelete = false">Batal</SecondaryButton>
          <PrimaryButton variant="danger" @click="doDelete" :loading="deleting">Hapus</PrimaryButton>
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
import StatusBadge from '@/Components/UI/StatusBadge.vue'
import ColorBadge from '@/Components/UI/ColorBadge.vue'
import ModalBase from '@/Components/UI/ModalBase.vue'
import PrimaryButton from '@/Components/UI/PrimaryButton.vue'
import SecondaryButton from '@/Components/UI/SecondaryButton.vue'
import Pagination from '@/Components/UI/Pagination.vue'
import CtaButton from '@/Components/UI/CtaButton.vue'
import IconButton from '@/Components/UI/IconButton.vue'

const props = defineProps({
  letters: Object,
  categories: Array,
  filters: Object,
})

const search = ref(props.filters?.search || '')
const status = ref(props.filters?.status || '')
const categoryId = ref(props.filters?.category_id || '')
const showDelete = ref(false)
const toDelete = ref(null)
const deleting = ref(false)

watch([search, status, categoryId], ([s, st, cat]) => {
  router.get('/letters/outbox', { search: s, status: st, category_id: cat }, { preserveState: true, replace: true })
})

function formatDate(dateStr) {
  if (!dateStr) return '-'
  return new Date(dateStr).toLocaleDateString('id-ID', { year: 'numeric', month: 'short', day: 'numeric' })
}

function getRecipient(letter) {
  if (letter.to_type === 'unit') return letter.to_unit?.name || 'Unit'
  if (letter.to_type === 'member') return letter.to_member?.full_name || 'Anggota'
  if (letter.to_type === 'admin_pusat') return 'Admin Pusat'
  return '-'
}

function canEdit(letter) {
  return ['draft', 'revision'].includes(letter.status)
}

function canSubmit(letter) {
  return ['draft', 'revision'].includes(letter.status)
}

function confirmDelete(letter) {
  toDelete.value = letter
  showDelete.value = true
}

function doDelete() {
  if (!toDelete.value) return
  deleting.value = true
  router.delete(`/letters/${toDelete.value.id}`, {
    onSuccess() {
      showDelete.value = false
      toDelete.value = null
    },
    onFinish() {
      deleting.value = false
    }
  })
}

function submitLetter(letter) {
  if (!confirm('Ajukan surat ini untuk persetujuan?')) return
  router.post(`/letters/${letter.id}/submit`)
}
</script>
