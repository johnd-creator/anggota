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
          <Link href="/letters/create" class="inline-flex items-center px-5 py-2.5 bg-blue-600 text-white text-sm font-semibold rounded-full shadow-lg shadow-blue-300/70 hover:bg-blue-700 transition transform hover:-translate-y-0.5">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Buat Surat
          </Link>
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
                  <div class="flex justify-end space-x-2">
                    <Link :href="`/letters/${letter.id}/preview`" class="text-neutral-600 hover:text-neutral-800">Preview</Link>
                    <Link :href="`/letters/${letter.id}`" class="text-brand-primary-600 hover:text-brand-primary-700">Detail</Link>
                    <template v-if="canEdit(letter)">
                      <Link :href="`/letters/${letter.id}/edit`" class="text-blue-600 hover:text-blue-700">Edit</Link>
                      <button @click="confirmDelete(letter)" class="text-red-600 hover:text-red-700">Hapus</button>
                    </template>
                    <button v-if="canSubmit(letter)" @click="submitLetter(letter)" class="text-green-600 hover:text-green-700 font-medium">Ajukan</button>
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
        <div v-if="letters.links.length > 3" class="bg-white px-4 py-3 border-t border-neutral-200 flex items-center justify-between sm:px-6">
          <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
            <div>
              <p class="text-sm text-neutral-700">
                Menampilkan <span class="font-medium">{{ letters.from }}</span> sampai <span class="font-medium">{{ letters.to }}</span> dari <span class="font-medium">{{ letters.total }}</span> data
              </p>
            </div>
            <div>
              <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                <template v-for="(link, key) in letters.links" :key="key">
                  <component :is="link.url ? 'Link' : 'span'" :href="link.url" v-html="link.label" class="relative inline-flex items-center px-4 py-2 border text-sm font-medium" :class="{ 'z-10 bg-brand-primary-50 border-brand-primary-500 text-brand-primary-600': link.active, 'bg-white border-neutral-300 text-neutral-500 hover:bg-neutral-50': !link.active && link.url, 'bg-neutral-100 border-neutral-300 text-neutral-400 cursor-not-allowed': !link.url }" />
                </template>
              </nav>
            </div>
          </div>
        </div>
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
          <PrimaryButton class="bg-status-error hover:bg-status-error-dark focus:ring-status-error" @click="doDelete" :loading="deleting">Hapus</PrimaryButton>
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
