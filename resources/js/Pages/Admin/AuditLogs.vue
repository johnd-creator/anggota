<template>
  <AppLayout page-title="System: Audit Logs">
    <div class="space-y-6">
      <!-- Filters -->
      <CardContainer padding="sm">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
          <SelectField
            v-model="filters.role"
            placeholder="Filter by Role"
            :options="roleOptions"
            class="w-full"
          />
          <InputField
            v-model="filters.date_start"
            type="date"
            placeholder="Start Date"
            class="w-full"
          />
          <InputField
            v-model="filters.date_end"
            type="date"
            placeholder="End Date"
            class="w-full"
          />
          <div class="flex items-end">
            <SecondaryButton class="w-full" @click="resetFilters">
              Reset Filters
            </SecondaryButton>
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
                  User
                </th>
                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">
                  Role
                </th>
                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">
                  Event
                </th>
                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">
                  IP Address
                </th>
                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">
                  Time
                </th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-neutral-200">
              <tr v-for="log in logs.data" :key="log.id" class="hover:bg-neutral-50">
                <td class="px-6 py-4 whitespace-nowrap">
                  <div class="flex items-center">
                    <div class="flex-shrink-0 h-8 w-8">
                      <img class="h-8 w-8 rounded-full" :src="log.user?.avatar || `https://ui-avatars.com/api/?name=${log.user?.name || 'Unknown'}&background=random`" alt="">
                    </div>
                    <div class="ml-4">
                      <div class="text-sm font-medium text-neutral-900">
                        {{ log.user?.name || 'Unknown User' }}
                      </div>
                      <div class="text-sm text-neutral-500">
                        {{ log.user?.email || '-' }}
                      </div>
                    </div>
                  </div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-neutral-100 text-neutral-800">
                    {{ log.user?.role?.label || '-' }}
                  </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-neutral-500">
                  {{ log.event }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-neutral-500">
                  {{ log.ip_address }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-neutral-500">
                  {{ new Date(log.created_at).toLocaleString() }}
                </td>
              </tr>
              <tr v-if="logs.data.length === 0">
                <td colspan="5" class="px-6 py-10 text-center text-neutral-500">
                  No audit logs found.
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <div v-if="logs.links.length > 3" class="bg-white px-4 py-3 border-t border-neutral-200 flex items-center justify-between sm:px-6">
          <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
            <div>
              <p class="text-sm text-neutral-700">
                Showing <span class="font-medium">{{ logs.from }}</span> to <span class="font-medium">{{ logs.to }}</span> of <span class="font-medium">{{ logs.total }}</span> results
              </p>
            </div>
            <div>
              <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                <template v-for="(link, key) in logs.links" :key="key">
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
  </AppLayout>
</template>

<script setup>
import { ref, watch, reactive } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import debounce from 'lodash/debounce';
import AppLayout from '@/Layouts/AppLayout.vue';
import CardContainer from '@/Components/UI/CardContainer.vue';
import InputField from '@/Components/UI/InputField.vue';
import SelectField from '@/Components/UI/SelectField.vue';
import SecondaryButton from '@/Components/UI/SecondaryButton.vue';

const props = defineProps({
  logs: Object,
  filters: Object,
});

const filters = reactive({
  role: props.filters.role || '',
  date_start: props.filters.date_start || '',
  date_end: props.filters.date_end || '',
});

const roleOptions = [
  { value: 'super_admin', label: 'Super Admin' },
  { value: 'admin_pusat', label: 'Admin Pusat' },
  { value: 'admin_unit', label: 'Admin Unit' },
  { value: 'anggota', label: 'Anggota' },
  { value: 'reguler', label: 'Reguler' },
];

const updateFilters = debounce(() => {
  router.get('/audit-logs', filters, {
    preserveState: true,
    replace: true,
  });
}, 300);

watch(filters, updateFilters, { deep: true });

const resetFilters = () => {
  filters.role = '';
  filters.date_start = '';
  filters.date_end = '';
};
</script>
