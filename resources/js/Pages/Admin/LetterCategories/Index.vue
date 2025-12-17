<template>
  <AppLayout page-title="Kategori Surat">
    <div class="space-y-6">
      <AlertBanner v-if="$page.props.flash?.success" type="success" :message="$page.props.flash.success" dismissible />
      <AlertBanner v-if="$page.props.errors?.category" type="error" :message="$page.props.errors.category" dismissible />

      <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h2 class="text-lg font-semibold text-neutral-900">Kategori Surat</h2>
          <p class="text-sm text-neutral-500">Kelola kategori untuk pengelompokan surat menyurat.</p>
        </div>
        <div class="flex flex-wrap gap-3">
          <Link href="/admin/letter-categories/create" class="inline-flex items-center px-5 py-2.5 bg-blue-600 text-white text-sm font-semibold rounded-full shadow-lg shadow-blue-300/70 hover:bg-blue-700 transition transform hover:-translate-y-0.5">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Tambah Kategori
          </Link>
        </div>
      </div>

      <CardContainer padding="none" class="overflow-hidden">
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-neutral-200">
            <thead class="bg-neutral-50">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Kode</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Nama</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Warna</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Urutan</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Deskripsi</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Status</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Jumlah Surat</th>
                <th class="px-6 py-3 text-right text-xs font-medium text-neutral-500 uppercase tracking-wider">Aksi</th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-neutral-200">
              <tr v-for="cat in categories" :key="cat.id" class="hover:bg-neutral-50">
                <td class="px-6 py-4 text-sm font-mono font-medium text-neutral-900">{{ cat.code }}</td>
                <td class="px-6 py-4 text-sm font-medium text-neutral-900">{{ cat.name }}</td>
                <td class="px-6 py-4">
                  <ColorBadge :color="cat.color || 'neutral'" :dot="true">{{ cat.color || 'neutral' }}</ColorBadge>
                </td>
                <td class="px-6 py-4 text-sm text-neutral-600">{{ cat.sort_order ?? 0 }}</td>
                <td class="px-6 py-4 text-sm text-neutral-600">{{ cat.description || '-' }}</td>
                <td class="px-6 py-4">
                  <span :class="[
                    'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium',
                    cat.is_active ? 'bg-green-100 text-green-800' : 'bg-neutral-100 text-neutral-600'
                  ]">
                    {{ cat.is_active ? 'Aktif' : 'Nonaktif' }}
                  </span>
                </td>
                <td class="px-6 py-4 text-sm text-neutral-600">{{ cat.letters_count }}</td>
                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                  <div class="flex justify-end space-x-2">
                    <IconButton variant="ghost" aria-label="Edit" @click="router.visit(`/admin/letter-categories/${cat.id}/edit`)">
                      <svg class="w-5 h-5 text-brand-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                      </svg>
                    </IconButton>
                    <IconButton variant="ghost" aria-label="Delete" @click="confirmDelete(cat)" :disabled="cat.letters_count > 0">
                      <svg :class="['w-5 h-5', cat.letters_count > 0 ? 'text-neutral-300' : 'text-status-error']" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                      </svg>
                    </IconButton>
                  </div>
                </td>
              </tr>
              <tr v-if="categories.length === 0">
                <td colspan="8" class="px-6 py-10 text-center text-neutral-500">Belum ada kategori surat.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </CardContainer>
    </div>

    <ModalBase v-model:show="showDelete" title="Hapus Kategori Surat" size="md">
      <div class="space-y-4">
        <p class="text-neutral-600">Anda yakin ingin menghapus kategori <span class="font-semibold">{{ toDelete?.name }}</span> ({{ toDelete?.code }})?</p>
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
import { ref } from 'vue'
import { router, Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import CardContainer from '@/Components/UI/CardContainer.vue'
import IconButton from '@/Components/UI/IconButton.vue'
import AlertBanner from '@/Components/UI/AlertBanner.vue'
import ModalBase from '@/Components/UI/ModalBase.vue'
import PrimaryButton from '@/Components/UI/PrimaryButton.vue'
import SecondaryButton from '@/Components/UI/SecondaryButton.vue'
import ColorBadge from '@/Components/UI/ColorBadge.vue'

const props = defineProps({ categories: Array })
const showDelete = ref(false)
const toDelete = ref(null)
const deleting = ref(false)

function confirmDelete(cat) {
  toDelete.value = cat
  showDelete.value = true
}

function doDelete() {
  if (!toDelete.value) return
  deleting.value = true
  router.delete(`/admin/letter-categories/${toDelete.value.id}`, {
    onSuccess() {
      showDelete.value = false
      toDelete.value = null
    },
    onFinish() {
      deleting.value = false
    }
  })
}
</script>
