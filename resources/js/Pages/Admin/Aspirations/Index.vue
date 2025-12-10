<template>
  <AppLayout page-title="Kelola Aspirasi">
    <div class="space-y-6">
      <!-- Stats Cards -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="bg-white rounded-xl border border-neutral-200 p-5">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm text-neutral-500">Aspirasi Baru</p>
              <p class="text-2xl font-bold text-yellow-600">{{ stats.new }}</p>
            </div>
            <div class="w-10 h-10 rounded-lg bg-yellow-100 flex items-center justify-center">
              <svg class="w-5 h-5 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
            </div>
          </div>
        </div>
        <div class="bg-white rounded-xl border border-neutral-200 p-5">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm text-neutral-500">Sedang Diproses</p>
              <p class="text-2xl font-bold text-blue-600">{{ stats.in_progress }}</p>
            </div>
            <div class="w-10 h-10 rounded-lg bg-blue-100 flex items-center justify-center">
              <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
              </svg>
            </div>
          </div>
        </div>
        <div class="bg-white rounded-xl border border-neutral-200 p-5">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm text-neutral-500">Selesai</p>
              <p class="text-2xl font-bold text-green-600">{{ stats.resolved }}</p>
            </div>
            <div class="w-10 h-10 rounded-lg bg-green-100 flex items-center justify-center">
              <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
            </div>
          </div>
        </div>
      </div>

      <!-- Filters -->
      <CardContainer padding="sm">
        <div class="flex flex-wrap gap-3 items-center">
          <input
            v-model="localFilters.search"
            type="text"
            placeholder="Cari aspirasi..."
            @keyup.enter="applyFilters"
            class="border border-neutral-300 rounded-lg px-3 py-2 text-sm w-full md:w-64 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
          />
          <select v-if="units.length" v-model="localFilters.unit_id" @change="applyFilters" class="border border-neutral-300 rounded-lg px-3 py-2 text-sm bg-white">
            <option value="">Semua Unit</option>
            <option v-for="u in units" :key="u.id" :value="u.id">{{ u.name }}</option>
          </select>
          <select v-model="localFilters.category_id" @change="applyFilters" class="border border-neutral-300 rounded-lg px-3 py-2 text-sm bg-white">
            <option value="">Semua Kategori</option>
            <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
          </select>
          <select v-model="localFilters.status" @change="applyFilters" class="border border-neutral-300 rounded-lg px-3 py-2 text-sm bg-white">
            <option value="">Semua Status</option>
            <option value="new">Baru</option>
            <option value="in_progress">Diproses</option>
            <option value="resolved">Selesai</option>
          </select>
          <label class="flex items-center gap-2 text-sm text-neutral-600">
            <input type="checkbox" v-model="localFilters.show_merged" @change="applyFilters" class="rounded border-neutral-300" />
            Tampilkan digabungkan
          </label>
          <button @click="resetFilters" class="px-3 py-2 text-sm text-neutral-600 hover:text-neutral-900">
            Reset
          </button>
        </div>
      </CardContainer>

      <!-- Table -->
      <CardContainer padding="none" class="overflow-hidden">
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-neutral-200">
            <thead class="bg-neutral-50">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Aspirasi</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Pengirim</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Unit</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Dukungan</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Status</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Tanggal</th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-neutral-200">
              <tr v-for="asp in aspirations.data" :key="asp.id" class="hover:bg-neutral-50">
                <td class="px-6 py-4">
                  <div class="flex items-center gap-2">
                    <Link :href="'/admin/aspirations/' + asp.id" class="text-sm font-medium text-neutral-900 hover:text-blue-600">
                      {{ asp.title }}
                    </Link>
                    <span v-if="asp.merged_into_id" class="px-2 py-0.5 rounded text-xs bg-gray-100 text-gray-500">Merged</span>
                  </div>
                  <p class="text-xs text-neutral-500 mt-0.5">{{ asp.category?.name }}</p>
                  <span v-if="asp.merged_into_id" class="inline-flex items-center gap-1 mt-1 px-2 py-0.5 bg-orange-100 text-orange-700 rounded-full text-xs">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                    </svg>
                    Digabungkan
                  </span>
                </td>
                <td class="px-6 py-4 text-sm text-neutral-600">{{ asp.member?.full_name || '-' }}</td>
                <td class="px-6 py-4 text-sm text-neutral-600">{{ asp.unit?.name || '-' }}</td>
                <td class="px-6 py-4">
                  <span class="inline-flex items-center gap-1 px-2 py-1 bg-neutral-100 rounded-full text-sm font-medium text-neutral-700">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" />
                    </svg>
                    {{ asp.support_count }}
                  </span>
                </td>
                <td class="px-6 py-4">
                  <span :class="['px-2 py-1 text-xs font-medium rounded-full', statusClass(asp.status)]">
                    {{ statusLabel(asp.status) }}
                  </span>
                </td>
                <td class="px-6 py-4 text-sm text-neutral-500">{{ formatDate(asp.created_at) }}</td>
              </tr>
              <tr v-if="aspirations.data.length === 0">
                <td colspan="6" class="px-6 py-10 text-center text-neutral-500">Tidak ada aspirasi ditemukan</td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <div v-if="aspirations.links?.length > 3" class="bg-white px-4 py-3 border-t border-neutral-200 flex items-center justify-between">
          <div class="text-sm text-neutral-700">
            Menampilkan {{ aspirations.from }} - {{ aspirations.to }} dari {{ aspirations.total }}
          </div>
          <nav class="flex gap-1">
            <template v-for="(link, i) in aspirations.links" :key="i">
              <Link v-if="link.url" :href="link.url" v-html="link.label" class="px-3 py-1.5 text-sm rounded border" :class="link.active ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-neutral-700 border-neutral-300 hover:bg-neutral-50'" />
              <span v-else v-html="link.label" class="px-3 py-1.5 text-sm text-neutral-400" />
            </template>
          </nav>
        </div>
      </CardContainer>
    </div>
  </AppLayout>
</template>

<script setup>
import { reactive } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import CardContainer from '@/Components/UI/CardContainer.vue';

const props = defineProps({
  aspirations: Object,
  stats: Object,
  filters: Object,
  categories: Array,
  units: Array,
});

const localFilters = reactive({
  search: props.filters.search || '',
  category: props.filters.category || '',
  unit: props.filters.unit || '',
  status: props.filters.status || '',
  sort: props.filters.sort || 'latest',
  merged: props.filters.merged || false,
});

let debounceTimeout = null;

function applyFilters() {
  if (debounceTimeout) clearTimeout(debounceTimeout);
  
  debounceTimeout = setTimeout(() => {
    router.get('/admin/aspirations', {
      search: localFilters.search || undefined,
      category: localFilters.category || undefined,
      unit: localFilters.unit || undefined,
      status: localFilters.status || undefined,
      sort: localFilters.sort !== 'latest' ? localFilters.sort : undefined,
      merged: localFilters.merged ? 'true' : undefined,
    }, { preserveState: true, replace: true });
  }, 300);
}

function resetFilters() {
  localFilters.search = '';
  localFilters.unit = '';
  localFilters.category = '';
  localFilters.status = '';
  localFilters.sort = 'latest';
  localFilters.merged = false;
  applyFilters();
}

function statusLabel(status) {
  const labels = { new: 'Baru', in_progress: 'Diproses', resolved: 'Selesai' };
  return labels[status] || status;
}

function statusClass(status) {
  const classes = {
    new: 'bg-yellow-100 text-yellow-800',
    in_progress: 'bg-blue-100 text-blue-800',
    resolved: 'bg-green-100 text-green-800',
  };
  return classes[status] || 'bg-neutral-100 text-neutral-800';
}

function formatDate(date) {
  return new Date(date).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' });
}
</script>
