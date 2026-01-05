<template>
  <AppLayout page-title="Admin Sessions">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between mb-6">
                <div>
                    <h2 class="text-lg font-semibold text-neutral-900">Sesi Login</h2>
                    <p class="text-sm text-neutral-500">Pantau dan kelola sesi login pengguna yang aktif.</p>
                </div>
            </div>

            <!-- Filters -->
            <CardContainer padding="sm" class="mb-6">
                <div class="flex flex-wrap gap-3 items-center">
                    <div class="relative w-full md:w-64">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg v-if="!isFiltering" class="w-4 h-4 text-neutral-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                            <svg v-else class="w-4 h-4 text-blue-500 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                        </div>
                        <input 
                            v-model="filterForm.search" 
                            type="text"
                            placeholder="Cari Email/Nama..." 
                            class="pl-10 pr-3 py-2 border border-neutral-300 rounded-lg text-sm w-full focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-shadow duration-200"
                        />
                    </div>
                    
                    <select v-model="filterForm.role" class="border border-neutral-300 rounded-lg px-3 py-2 text-sm bg-white cursor-pointer hover:border-neutral-400 transition-colors focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <option value="">Semua Role</option>
                        <option value="super_admin">Super Admin</option>
                        <option value="admin_pusat">Admin Pusat</option>
                        <option value="admin_unit">Admin Unit</option>
                        <option value="bendahara">Bendahara</option>
                        <option value="anggota">Anggota</option>
                        <option value="reguler">Reguler</option>
                    </select>
                    
                    <div class="flex items-center gap-2">
                        <input type="date" v-model="filterForm.date_start" class="border border-neutral-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500" />
                        <span class="text-sm text-gray-500">s/d</span>
                        <input type="date" v-model="filterForm.date_end" class="border border-neutral-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500" />
                    </div>
                    
                    <!-- Reset Button -->
                    <button
                        v-if="hasActiveFilters"
                        @click="resetFilter"
                        class="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-medium text-neutral-600 hover:text-neutral-900 hover:bg-neutral-100 rounded-lg transition-colors"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                        </svg>
                        Reset
                    </button>
                </div>
            </CardContainer>

            <CardContainer padding="none" class="overflow-hidden border border-neutral-200">

      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-neutral-200">
          <thead class="bg-neutral-50">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Username</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Role</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">IP Address</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Device/UA</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Aktivitas Terakhir</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Aksi</th>
            </tr>
          </thead>
                <tbody class="divide-y divide-neutral-200 bg-white">
                    <tr v-for="s in sessions.data" :key="s.id" class="hover:bg-neutral-50">
                        <td class="px-6 py-4 text-sm">
                            <div class="font-medium text-neutral-900">{{ s.user?.name || 'Unknown' }}</div>
                        </td>
                        <td class="px-6 py-4 text-sm text-neutral-600">
                            <span class="px-2 py-0.5 bg-neutral-100 border border-neutral-200 text-neutral-600 rounded-full text-[10px] font-bold uppercase tracking-wider">
                                {{ s.user?.role?.name || '-' }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-sm text-neutral-600">{{ s.ip }}</td>
                        <td class="px-6 py-4 text-sm text-neutral-600 max-w-xs truncate" :title="s.user_agent">{{ s.user_agent }}</td>
                        <td class="px-6 py-4 text-sm text-neutral-600">
                            <div class="font-medium">{{ formatDate(s.last_activity) }}</div>
                            <div class="text-xs text-neutral-400">{{ timeAgo(s.last_activity) }}</div>
                        </td>
                        <td class="px-6 py-4 text-sm whitespace-nowrap">
                            <div class="flex items-center gap-2">
                                <IconButton
                                    variant="ghost"
                                    aria-label="Terminate Session"
                                    @click="terminate(s.id)"
                                    title="Hentikan Sesi"
                                >
                                    <svg class="w-5 h-5 text-status-error" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </IconButton>
                                <IconButton
                                    variant="ghost"
                                    aria-label="Terminate All User Sessions"
                                    @click="terminateAll(s.user_id)"
                                    title="Hentikan Semua Sesi User"
                                >
                                    <svg class="w-5 h-5 text-status-error" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                </IconButton>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="sessions.data.length === 0">
                        <td colspan="6" class="px-6 py-8 text-center text-sm text-neutral-500 italic">Tidak ada data sesi aktif.</td>
                    </tr>
                </tbody>
        </table>
      </div>
      
                <!-- Pagination -->
                <div class="px-6 py-4 border-t border-neutral-200">
                    <Pagination :paginator="sessions" />
                </div>
            </CardContainer>
  </AppLayout>
</template>

<script setup>
import { ref, reactive, watch, computed } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import CardContainer from '@/Components/UI/CardContainer.vue';
import InputField from '@/Components/UI/InputField.vue';
import PrimaryButton from '@/Components/UI/PrimaryButton.vue';
import IconButton from '@/Components/UI/IconButton.vue';
import Pagination from '@/Components/UI/Pagination.vue';
import debounce from 'lodash/debounce';

const props = defineProps({
  sessions: Object,
  filters: Object,
});

const isFiltering = ref(false);
const filterForm = reactive({
    search: props.filters.search || '',
    role: props.filters.role || '',
    date_start: props.filters.date_start || '',
    date_end: props.filters.date_end || '',
});

const hasActiveFilters = computed(() => {
    return filterForm.search || filterForm.role || filterForm.date_start || filterForm.date_end;
});

const applyFilter = debounce(() => {
    isFiltering.value = true;
    router.get('/admin/sessions', filterForm, { 
        preserveState: true, 
        replace: true,
        onFinish: () => {
            isFiltering.value = false;
        }
    });
}, 300);

watch(() => ({ ...filterForm }), () => {
    applyFilter();
}, { deep: true });

function resetFilter() {
    filterForm.search = '';
    filterForm.role = '';
    filterForm.date_start = '';
    filterForm.date_end = '';
}

function terminate(id) {
  if (confirm('Yakin ingin menghentikan sesi ini?')) {
    router.delete(`/admin/sessions/${id}`);
  }
}

function terminateAll(userId) {
  if (confirm('Yakin ingin menghentikan SEMUA sesi user ini?')) {
    router.delete(`/admin/sessions/user/${userId}`);
  }
}

function formatDate(date) {
    if (!date) return '-';
    return new Date(date).toLocaleString('id-ID', { dateStyle: 'medium', timeStyle: 'short' });
}

function timeAgo(date) {
    if (!date) return '';
    const seconds = Math.floor((new Date() - new Date(date)) / 1000);
    if (seconds < 60) return 'Baru saja';
    const minutes = Math.floor(seconds / 60);
    if (minutes < 60) return `${minutes} menit lalu`;
    const hours = Math.floor(minutes / 60);
    if (hours < 24) return `${hours} jam lalu`;
    const days = Math.floor(hours / 24);
    return `${days} hari lalu`;
}
</script>
