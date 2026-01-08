<template>
  <AppLayout page-title="Letter Approvers">
    <div class="space-y-6">
      <AlertBanner v-if="$page.props.errors?.user_id" type="error" :message="$page.props.errors.user_id" dismissible />

      <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h2 class="text-lg font-semibold text-neutral-900">Letter Approvers</h2>
          <p class="text-sm text-neutral-500">Kelola delegasi persetujuan surat per unit.</p>
        </div>
        <div class="flex flex-wrap gap-3">
          <CtaButton href="/admin/letter-approvers/create">
            <template #icon>
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
              </svg>
            </template>
            Tambah Approver
          </CtaButton>
        </div>
      </div>

      <!-- Filters -->
      <CardContainer padding="default">
        <div class="flex flex-wrap gap-4 items-end">
          <div class="flex-1 min-w-[200px]">
            <SelectField
              v-model="selectedUnit"
              :options="unitOptions"
              label="Filter Unit"
              placeholder="Semua Unit"
              @update:modelValue="applyFilter"
            />
          </div>
        </div>
      </CardContainer>

      <CardContainer padding="none" class="overflow-hidden">
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-neutral-200">
            <thead class="bg-neutral-50">
              <tr>
                <th class="px-5 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Unit</th>
                <th class="px-5 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Tipe Penandatangan</th>
                <th class="px-5 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">User</th>
                <th class="px-5 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Status</th>
                <th class="px-5 py-3 text-right text-xs font-medium text-neutral-500 uppercase tracking-wider">Aksi</th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-neutral-200">
              <tr v-for="approver in approvers.data" :key="approver.id" class="hover:bg-neutral-50">
                <td class="px-5 py-3 text-sm text-neutral-900">
                  {{ approver.organization_unit?.name || 'Pusat' }}
                </td>
                <td class="px-5 py-3 text-sm">
                  <span :class="[
                    'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium capitalize',
                    approver.signer_type === 'ketua' ? 'bg-blue-100 text-blue-800' : 'bg-purple-100 text-purple-800'
                  ]">
                    {{ approver.signer_type }}
                  </span>
                </td>
                <td class="px-5 py-3 text-sm text-neutral-900">{{ approver.user?.name || '-' }}</td>
                <td class="px-5 py-3">
                  <button @click="toggleActive(approver)" :class="[
                    'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium cursor-pointer transition-colors',
                    approver.is_active ? 'bg-green-100 text-green-800 hover:bg-green-200' : 'bg-neutral-100 text-neutral-600 hover:bg-neutral-200'
                  ]">
                    {{ approver.is_active ? 'Aktif' : 'Nonaktif' }}
                  </button>
                </td>
                <td class="px-5 py-3 whitespace-nowrap text-right text-sm font-medium">
                  <div class="flex justify-end gap-2">
                    <IconButton variant="ghost" size="sm" aria-label="Edit" @click="router.visit(`/admin/letter-approvers/${approver.id}/edit`)">
                      <svg class="w-5 h-5 text-brand-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                      </svg>
                    </IconButton>
                    <IconButton variant="ghost" size="sm" aria-label="Delete" @click="confirmDelete(approver)">
                      <svg class="w-5 h-5 text-status-error" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                      </svg>
                    </IconButton>
                  </div>
                </td>
              </tr>
              <tr v-if="approvers.data.length === 0">
                <td colspan="5" class="px-5 py-10 text-center text-neutral-500">Belum ada approver yang terdaftar.</td>
              </tr>
            </tbody>
          </table>
        </div>
        <Pagination :paginator="approvers" />
      </CardContainer>
    </div>

    <ModalBase v-model:show="showDelete" title="Hapus Approver" size="md">
      <div class="space-y-4">
        <p class="text-neutral-600">Anda yakin ingin menghapus <span class="font-semibold">{{ toDelete?.user?.name }}</span> sebagai approver?</p>
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
import { ref, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import CardContainer from '@/Components/UI/CardContainer.vue'
import IconButton from '@/Components/UI/IconButton.vue'
import AlertBanner from '@/Components/UI/AlertBanner.vue'
import ModalBase from '@/Components/UI/ModalBase.vue'
import PrimaryButton from '@/Components/UI/PrimaryButton.vue'
import SecondaryButton from '@/Components/UI/SecondaryButton.vue'
import Pagination from '@/Components/UI/Pagination.vue'
import CtaButton from '@/Components/UI/CtaButton.vue'
import SelectField from '@/Components/UI/SelectField.vue'

const props = defineProps({
  approvers: Object,
  units: Array,
  filters: Object,
})

const selectedUnit = ref(props.filters?.unit_id || '')
const showDelete = ref(false)
const toDelete = ref(null)
const deleting = ref(false)

// Format units for SelectField component
const unitOptions = computed(() => {
  const options = [
    { label: 'Semua Unit', value: '' },
    { label: 'Pusat (Admin Pusat)', value: 'pusat' },
  ]
  
  if (props.units && props.units.length > 0) {
    props.units.forEach(unit => {
      options.push({ label: unit.name, value: unit.id })
    })
  }
  
  return options
})

function applyFilter() {
  const params = {}
  if (selectedUnit.value) {
    params.unit_id = selectedUnit.value
  }
  router.get('/admin/letter-approvers', params, { preserveState: true })
}

function toggleActive(approver) {
  router.post(`/admin/letter-approvers/${approver.id}/toggle-active`, {}, {
    preserveScroll: true,
  })
}

function confirmDelete(approver) {
  toDelete.value = approver
  showDelete.value = true
}

function doDelete() {
  if (!toDelete.value) return
  deleting.value = true
  router.delete(`/admin/letter-approvers/${toDelete.value.id}`, {
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
