<template>
  <AppLayout page-title="Master Data: Organization Units">
    <template #actions>
      <a href="/admin/units/create" v-if="['super_admin','admin_unit'].includes($page.props.auth.user.role.name)" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white font-medium rounded-lg shadow-sm hover:bg-blue-700 transition-colors duration-200">
        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
        </svg>
        Tambah Unit
      </a>
    </template>
      <div class="space-y-6">
        <div class="flex items-center justify-between">
          <div class="text-sm text-neutral-600">Kelola daftar unit organisasi.</div>
        </div>
      <!-- Success Message -->
      <AlertBanner
        v-if="$page.props.flash.success"
        type="success"
        :message="$page.props.flash.success"
        dismissible
        @dismiss="$page.props.flash.success = null"
      />

      <!-- Search & Filter -->
      <CardContainer padding="sm">
        <div class="flex items-center">
          <div class="w-full max-w-md">
            <InputField
              v-model="search"
              placeholder="Search by name or code..."
              class="w-full"
            />
          </div>
        </div>
      </CardContainer>

      <!-- Data Table -->
      <CardContainer padding="none" class="overflow-hidden">
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-neutral-200">
            <thead class="bg-neutral-50">
              <tr>
                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">
                  Code
                </th>
                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">
                  Name
                </th>
                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">
                  Address
                </th>
                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-neutral-500 uppercase tracking-wider">
                  Actions
                </th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-neutral-200">
              <tr v-for="unit in units.data" :key="unit.id" class="hover:bg-neutral-50">
                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-neutral-900">
                  {{ unit.code }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-neutral-700">
                  {{ unit.name }}
                </td>
                <td class="px-6 py-4 text-sm text-neutral-500 truncate max-w-xs">
                  {{ unit.address || '-' }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                  <div class="flex justify-end space-x-2">
                    <IconButton
                      v-if="$page.props.auth.user.role.name === 'super_admin'"
                      variant="ghost"
                      aria-label="Edit"
                      @click="router.visit(`/admin/units/${unit.id}/edit`)"
                    >
                      <svg class="w-5 h-5 text-brand-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                      </svg>
                    </IconButton>
                    <IconButton
                      v-if="$page.props.auth.user.role.name === 'super_admin'"
                      variant="ghost"
                      aria-label="Delete"
                      @click="confirmDelete(unit)"
                    >
                      <svg class="w-5 h-5 text-status-error" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                      </svg>
                    </IconButton>
                  </div>
                </td>
              </tr>
              <tr v-if="units.data.length === 0">
                <td colspan="4" class="px-6 py-10 text-center text-neutral-500">
                  No organization units found.
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        
        <!-- Pagination -->
        <div v-if="units.links.length > 3" class="bg-white px-4 py-3 border-t border-neutral-200 flex items-center justify-between sm:px-6">
          <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
            <div>
              <p class="text-sm text-neutral-700">
                Showing <span class="font-medium">{{ units.from }}</span> to <span class="font-medium">{{ units.to }}</span> of <span class="font-medium">{{ units.total }}</span> results
              </p>
            </div>
            <div>
              <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                <template v-for="(link, key) in units.links" :key="key">
                  <component
                    :is="link.url ? 'Link' : 'span'"
                    :href="link.url"
                    v-html="link.label"
                    class="relative inline-flex items-center px-4 py-2 border text-sm font-medium"
                    :class="{
                      'z-10 bg-brand-primary-50 border-brand-primary-500 text-brand-primary-600': link.active,
                      'bg-white border-neutral-300 text-neutral-500 hover:bg-neutral-50': !link.active && link.url,
                      'bg-neutral-100 border-neutral-300 text-neutral-400 cursor-not-allowed': !link.url
                    }"
                  />
                </template>
              </nav>
            </div>
          </div>
        </div>
      </CardContainer>
    </div>

    <!-- Delete Confirmation Modal -->
    <ModalBase
      v-model:show="showDeleteModal"
      title="Delete Organization Unit"
      size="md"
    >
      <div class="space-y-4">
        <p class="text-neutral-600">
          Are you sure you want to delete <span class="font-semibold">{{ unitToDelete?.name }}</span>? This action cannot be undone.
        </p>
        <AlertBanner type="warning" message="Deleting this unit may affect associated users." />
      </div>
      <template #footer>
        <div class="flex justify-end space-x-3">
          <SecondaryButton @click="showDeleteModal = false">Cancel</SecondaryButton>
          <PrimaryButton 
            class="bg-status-error hover:bg-status-error-dark focus:ring-status-error" 
            @click="deleteUnit"
            :loading="deleting"
          >
            Delete Unit
          </PrimaryButton>
        </div>
      </template>
    </ModalBase>
  </AppLayout>
</template>

<script setup>
import { ref, watch } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import debounce from 'lodash/debounce';
import AppLayout from '@/Layouts/AppLayout.vue';
import CardContainer from '@/Components/UI/CardContainer.vue';
import PrimaryButton from '@/Components/UI/PrimaryButton.vue';
import SecondaryButton from '@/Components/UI/SecondaryButton.vue';
import IconButton from '@/Components/UI/IconButton.vue';
import InputField from '@/Components/UI/InputField.vue';
import AlertBanner from '@/Components/UI/AlertBanner.vue';
import ModalBase from '@/Components/UI/ModalBase.vue';

const props = defineProps({
  units: Object,
  filters: Object,
});

const search = ref(props.filters.search || '');
const showDeleteModal = ref(false);
const unitToDelete = ref(null);
const deleting = ref(false);

watch(search, debounce((value) => {
  router.get('/admin/units', { search: value }, {
    preserveState: true,
    replace: true,
  });
}, 300));

const confirmDelete = (unit) => {
  unitToDelete.value = unit;
  showDeleteModal.value = true;
};

const deleteUnit = () => {
  if (!unitToDelete.value) return;
  
  deleting.value = true;
  router.delete(`/admin/units/${unitToDelete.value.id}` , {
    onSuccess: () => {
      showDeleteModal.value = false;
      unitToDelete.value = null;
    },
    onFinish: () => {
      deleting.value = false;
    },
  });
};
</script>
