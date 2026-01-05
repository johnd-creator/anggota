<script setup>
import { ref, watch, computed } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import debounce from 'lodash/debounce';
import CtaButton from '@/Components/UI/CtaButton.vue';
import IconButton from '@/Components/UI/IconButton.vue';
import Pagination from '@/Components/UI/Pagination.vue';
import CardContainer from '@/Components/UI/CardContainer.vue';

const props = defineProps({
    announcements: Object,
    filters: Object,
    can: Object,
});

const isFiltering = ref(false);
const search = ref(props.filters.q || '');
const status = ref(props.filters.status || '');
const scope = ref(props.filters.scope_type || '');
const pinned = ref(props.filters.pinned || '');

const hasActiveFilters = computed(() => {
    return search.value || status.value || scope.value || pinned.value;
});

watch([search, status, scope, pinned], debounce(() => {
    isFiltering.value = true;
    router.get('/admin/announcements', { 
        q: search.value, 
        status: status.value, 
        scope_type: scope.value,
        pinned: pinned.value,
    }, { 
        preserveState: true, 
        replace: true,
        onFinish: () => {
            isFiltering.value = false;
        }
    });
}, 300));

const resetFilters = () => {
    search.value = '';
    status.value = '';
    scope.value = '';
    pinned.value = '';
};

const formatScope = (type, unitId, unitName) => {
    if (type === 'global_all') return 'Global (Semua)';
    if (type === 'global_officers') return 'Global (Pengurus)';
    if (type === 'unit') return `Unit: ${unitName || '-'}`;
    return type;
};

const formatDate = (date) => {
    return new Date(date).toLocaleDateString('id-ID', {
        day: 'numeric', month: 'short', year: 'numeric',
        hour: '2-digit', minute: '2-digit'
    });
};

const toggleActive = (announcement) => {
    router.patch(`/admin/announcements/${announcement.id}/toggle-active`, {}, {
        preserveScroll: true,
        preserveState: true,
    });
};

const togglePin = (announcement) => {
    router.patch(`/admin/announcements/${announcement.id}/toggle-pin`, {}, {
        preserveScroll: true,
        preserveState: true,
    });
};

const deleteItem = (announcement) => {
    if (confirm('Apakah Anda yakin ingin menghapus pengumuman ini?')) {
        router.delete(`/admin/announcements/${announcement.id}`);
    }
};
</script>

<template>
    <AppLayout page-title="Kelola Pengumuman">
        <div class="space-y-6">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-neutral-900">Pengumuman</h2>
                    <p class="text-sm text-neutral-500">Kelola pengumuman global dan unit.</p>
                </div>
                <div class="flex flex-wrap gap-3">
                    <CtaButton
                        v-if="can?.create !== false"
                        href="/admin/announcements/create"
                    >
                        <template #icon>
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        </template>
                        Tambah Pengumuman
                    </CtaButton>
                </div>
            </div>

            <!-- Filters -->
            <CardContainer padding="sm">
                <div class="flex flex-wrap gap-3 items-center">
                    <!-- Search with icon -->
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
                            v-model="search"
                            type="text"
                            placeholder="Cari judul atau isi..."
                            class="pl-10 pr-3 py-2 border border-neutral-300 rounded-lg text-sm w-full focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-shadow duration-200"
                        />
                    </div>

                    <select v-model="status" class="border border-neutral-300 rounded-lg px-3 py-2 text-sm bg-white cursor-pointer hover:border-neutral-400 transition-colors focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <option value="">Semua Status</option>
                        <option value="active">Aktif</option>
                        <option value="inactive">Non-aktif</option>
                    </select>

                    <select v-model="scope" class="border border-neutral-300 rounded-lg px-3 py-2 text-sm bg-white cursor-pointer hover:border-neutral-400 transition-colors focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <option value="">Semua Scope</option>
                        <option value="global_all">Global (All)</option>
                        <option value="global_officers">Global (Pengurus)</option>
                        <option value="unit">Unit</option>
                    </select>

                    <select v-model="pinned" class="border border-neutral-300 rounded-lg px-3 py-2 text-sm bg-white cursor-pointer hover:border-neutral-400 transition-colors focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <option value="">Semua Pin</option>
                        <option value="pinned">Pinned</option>
                        <option value="not_pinned">Not Pinned</option>
                    </select>

                    <!-- Reset Button -->
                    <button
                        v-if="hasActiveFilters"
                        @click="resetFilters"
                        class="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-medium text-neutral-600 hover:text-neutral-900 hover:bg-neutral-100 rounded-lg transition-colors"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                        </svg>
                        Reset
                    </button>
                </div>
            </CardContainer>

            <!-- Table -->
            <CardContainer padding="none" class="overflow-hidden border border-neutral-200">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-neutral-200">
                        <thead class="bg-neutral-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Judul</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Target Audience</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Tanggal</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-neutral-500 uppercase tracking-wider">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-neutral-200">
                            <tr v-if="announcements.data.length === 0">
                                <td colspan="5" class="px-6 py-4 text-center text-neutral-500">
                                    Tidak ada data pengumuman.
                                </td>
                            </tr>
                            <tr v-for="item in announcements.data" :key="item.id" class="hover:bg-gray-50">
                                <td class="px-6 py-4">
                                    <div class="text-sm font-medium text-neutral-900">{{ item.title }}</div>
                                    <div class="text-xs text-neutral-500 truncate max-w-xs">{{ item.body.substring(0, 50) }}...</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">
                                        {{ formatScope(item.scope_type, item.organization_unit_id, item.organization_unit?.name) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap flex flex-col gap-1 items-start">
                                    <button 
                                        type="button"
                                        @click="toggleActive(item)"
                                        class="px-2 py-0.5 text-xs rounded-full border transition-colors duration-200"
                                        :class="item.is_active ? 'bg-green-100 text-green-800 border-green-200 hover:bg-green-200' : 'bg-gray-100 text-gray-600 border-gray-200 hover:bg-gray-200'"
                                    >
                                        {{ item.is_active ? 'Active' : 'Draft' }}
                                    </button>
                                    <button 
                                        type="button"
                                        @click="togglePin(item)"
                                        class="px-2 py-0.5 text-xs rounded-full border transition-colors duration-200 flex items-center gap-1"
                                        :class="item.pin_to_dashboard ? 'bg-yellow-100 text-yellow-800 border-yellow-200 hover:bg-yellow-200' : 'bg-transparent text-gray-400 border-transparent hover:bg-gray-50'"
                                    >
                                        <span v-if="item.pin_to_dashboard">📌 Pinned</span>
                                        <span v-else>unpinned</span>
                                    </button>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-neutral-500">
                                    {{ formatDate(item.created_at) }}
                                    <div class="text-xs">by {{ item.creator?.name }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <div class="flex justify-end gap-2">
                                        <IconButton
                                            variant="ghost"
                                            aria-label="Edit"
                                            @click="router.visit(`/admin/announcements/${item.id}/edit`)"
                                        >
                                            <svg class="w-5 h-5 text-brand-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2-2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                            </svg>
                                        </IconButton>
                                        <IconButton
                                            variant="ghost"
                                            aria-label="Delete"
                                            @click="deleteItem(item)"
                                        >
                                            <svg class="w-5 h-5 text-status-error" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                        </IconButton>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div class="px-6 py-4 border-t border-neutral-200">
                    <Pagination :paginator="announcements" />
                </div>
            </CardContainer>
        </div>
    </AppLayout>
</template>
