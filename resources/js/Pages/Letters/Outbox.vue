<template>
  <AppLayout page-title="Surat Keluar">
    <div class="space-y-6">
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

      <!-- Letters Table (Desktop) -->
      <CardContainer padding="none" class="hidden md:block overflow-hidden">
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
                    <ActionIconButton
                      action="preview"
                      aria-label="Preview surat"
                      title="Preview"
                      @click="router.visit(`/letters/${letter.id}/preview`)"
                    />
                    <ActionIconButton
                      action="detail"
                      aria-label="Lihat detail surat"
                      title="Detail"
                      @click="router.visit(`/letters/${letter.id}`)"
                    />
                    <template v-if="canEdit(letter)">
                      <ActionIconButton
                        action="edit"
                        aria-label="Edit surat"
                        title="Edit"
                        @click="router.visit(`/letters/${letter.id}/edit`)"
                      />
                      <ActionIconButton
                        action="delete"
                        aria-label="Hapus surat"
                        title="Hapus"
                        @click="confirmDelete(letter)"
                      />
                    </template>
                    <ActionIconButton
                      v-if="canSubmit(letter)"
                      action="submit"
                      aria-label="Ajukan surat"
                      title="Ajukan"
                      label="Ajukan"
                      @click="submitLetter(letter)"
                      size="sm"
                    />
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

      <!-- Letters Cards (Mobile) -->
      <div v-if="letters.data.length > 0" class="md:hidden space-y-3">
        <DataCard
            v-for="letter in letters.data"
            :key="letter.id"
            :title="letter.subject"
            :subtitle="letter.letter_number || `Kategori: ${letter.category?.code || '-'}`"
            :status="letter.status"
            :meta="[
                { label: 'Kepada', value: getRecipient(letter) },
                { label: 'Tanggal', value: formatDate(letter.created_at) }
            ]"
        >
            <template #actions>
                <ActionIconButton
                  action="preview"
                  aria-label="Preview surat"
                  title="Preview"
                  size="md"
                  @click="router.visit(`/letters/${letter.id}/preview`)"
                />
                <ActionIconButton
                  action="detail"
                  aria-label="Lihat detail surat"
                  title="Detail"
                  size="md"
                  @click="router.visit(`/letters/${letter.id}`)"
                />
                <ActionIconButton
                  v-if="canEdit(letter)"
                  action="edit"
                  aria-label="Edit surat"
                  title="Edit"
                  size="md"
                  @click="router.visit(`/letters/${letter.id}/edit`)"
                />
                <ActionIconButton
                  v-if="canEdit(letter)"
                  action="delete"
                  aria-label="Hapus surat"
                  title="Hapus"
                  size="md"
                  @click="confirmDelete(letter)"
                />
                <ActionIconButton
                  v-if="canSubmit(letter)"
                  action="submit"
                  aria-label="Ajukan surat"
                  title="Ajukan"
                  label="Ajukan"
                  size="md"
                  @click="submitLetter(letter)"
                />
            </template>
        </DataCard>
        <div v-if="letters.data.length === 0" class="text-center py-8 text-neutral-500">
            Tidak ada surat keluar.
        </div>
      </div>

      <!-- Mobile Pagination -->
      <div v-if="letters.data.length > 0" class="md:hidden mt-4">
        <Pagination :paginator="letters" />
      </div>
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
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import CardContainer from '@/Components/UI/CardContainer.vue'
import InputField from '@/Components/UI/InputField.vue'
import StatusBadge from '@/Components/UI/StatusBadge.vue'
import ColorBadge from '@/Components/UI/ColorBadge.vue'
import ModalBase from '@/Components/UI/ModalBase.vue'
import PrimaryButton from '@/Components/UI/PrimaryButton.vue'
import SecondaryButton from '@/Components/UI/SecondaryButton.vue'
import Pagination from '@/Components/UI/Pagination.vue'
import CtaButton from '@/Components/UI/CtaButton.vue'
import DataCard from '@/Components/Mobile/DataCard.vue'
import ActionIconButton from '@/Components/UI/ActionIconButton.vue'

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
  if (letter.to_type === 'member') return this.$toTitleCase(letter.to_member?.full_name) || 'Anggota'
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
