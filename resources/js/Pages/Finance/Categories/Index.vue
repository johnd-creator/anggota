<template>
  <AppLayout page-title="Kategori Keuangan">
    <template #actions>
      <a href="/finance/categories/create" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white font-medium rounded-lg shadow-sm hover:bg-blue-700 transition-colors duration-200">
        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
        </svg>
        Tambah Kategori
      </a>
    </template>

    <div class="space-y-6">
      <AlertBanner v-if="$page.props.flash.success" type="success" :message="$page.props.flash.success" dismissible @dismiss="$page.props.flash.success = null" />

      <CardContainer padding="sm">
        <div class="flex items-center space-x-3">
          <div class="w-full max-w-md">
            <InputField v-model="search" placeholder="Cari nama kategori..." class="w-full" />
          </div>
          <select v-model="type" class="rounded-md border border-neutral-300 px-3 py-2 text-sm text-neutral-700">
            <option value="">Semua Tipe</option>
            <option value="income">Pemasukan</option>
            <option value="expense">Pengeluaran</option>
          </select>
          <div v-if="isSuperAdmin">
            <select v-model="unitId" class="rounded-md border border-neutral-300 px-3 py-2 text-sm text-neutral-700">
              <option value="">Semua Unit</option>
              <option value="null">Global</option>
              <option v-for="u in units" :key="u.id" :value="u.id">{{ u.name }}</option>
            </select>
          </div>
        </div>
      </CardContainer>

      <CardContainer padding="none" class="overflow-hidden">
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-neutral-200">
            <thead class="bg-neutral-50">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Nama</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Tipe</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Unit</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Dibuat oleh</th>
                <th class="px-6 py-3 text-right text-xs font-medium text-neutral-500 uppercase tracking-wider">Aksi</th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-neutral-200">
              <tr v-for="c in categories.data" :key="c.id" class="hover:bg-neutral-50">
                <td class="px-6 py-4 text-sm font-medium text-neutral-900">{{ c.name }}</td>
                <td class="px-6 py-4 text-sm text-neutral-700">{{ c.type === 'income' ? 'Pemasukan' : 'Pengeluaran' }}</td>
                <td class="px-6 py-4 text-sm text-neutral-700">{{ c.organization_unit ? c.organization_unit.name : 'Global' }}</td>
                <td class="px-6 py-4 text-sm text-neutral-700">{{ c.creator ? c.creator.name : '-' }}</td>
                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                  <div class="flex justify-end space-x-2">
                    <IconButton variant="ghost" aria-label="Edit" @click="router.visit(`/finance/categories/${c.id}/edit`)">
                      <svg class="w-5 h-5 text-brand-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                      </svg>
                    </IconButton>
                    <IconButton variant="ghost" aria-label="Delete" @click="confirmDelete(c)">
                      <svg class="w-5 h-5 text-status-error" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                      </svg>
                    </IconButton>
                  </div>
                </td>
              </tr>
              <tr v-if="categories.data.length === 0">
                <td colspan="5" class="px-6 py-10 text-center text-neutral-500">Tidak ada kategori.</td>
              </tr>
            </tbody>
          </table>
        </div>

        <div v-if="categories.links.length > 3" class="bg-white px-4 py-3 border-t border-neutral-200 flex items-center justify-between sm:px-6">
          <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
            <div>
              <p class="text-sm text-neutral-700">
                Menampilkan <span class="font-medium">{{ categories.from }}</span> sampai <span class="font-medium">{{ categories.to }}</span> dari <span class="font-medium">{{ categories.total }}</span> data
              </p>
            </div>
            <div>
              <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                <template v-for="(link, key) in categories.links" :key="key">
                  <component :is="link.url ? 'Link' : 'span'" :href="link.url" v-html="link.label" class="relative inline-flex items-center px-4 py-2 border text-sm font-medium" :class="{ 'z-10 bg-brand-primary-50 border-brand-primary-500 text-brand-primary-600': link.active, 'bg-white border-neutral-300 text-neutral-500 hover:bg-neutral-50': !link.active && link.url, 'bg-neutral-100 border-neutral-300 text-neutral-400 cursor-not-allowed': !link.url }" />
                </template>
              </nav>
            </div>
          </div>
        </div>
      </CardContainer>
    </div>
    <ModalBase v-model:show="showDelete" title="Hapus Kategori" size="md">
      <div class="space-y-4">
        <p class="text-neutral-600">Anda yakin ingin menghapus <span class="font-semibold">{{ toDelete?.name }}</span>?</p>
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
import { ref, watch, computed } from 'vue'
import { router, Link, usePage } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import CardContainer from '@/Components/UI/CardContainer.vue'
import InputField from '@/Components/UI/InputField.vue'
import IconButton from '@/Components/UI/IconButton.vue'
import AlertBanner from '@/Components/UI/AlertBanner.vue'
import ModalBase from '@/Components/UI/ModalBase.vue'
import PrimaryButton from '@/Components/UI/PrimaryButton.vue'
import SecondaryButton from '@/Components/UI/SecondaryButton.vue'

const props = defineProps({ categories: Object, filters: Object, units: Array })
const page = usePage()
const search = ref(props.filters.search || '')
const type = ref(props.filters.type || '')
const unitId = ref(props.filters.unit_id || '')
const showDelete = ref(false)
const toDelete = ref(null)
const deleting = ref(false)
const isSuperAdmin = computed(() => (page.props?.auth?.user?.role?.name || '') === 'super_admin')

watch([search, type, unitId], ([s, t, u]) => {
  router.get('/finance/categories', { search: s, type: t, unit_id: u }, { preserveState: true, replace: true })
})

function confirmDelete(c){ toDelete.value = c; showDelete.value = true }
function doDelete(){ if(!toDelete.value) return; deleting.value = true; router.delete(`/finance/categories/${toDelete.value.id}`, { onSuccess(){ showDelete.value=false; toDelete.value=null }, onFinish(){ deleting.value=false } }) }
</script>
