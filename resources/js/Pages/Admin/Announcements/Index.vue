<script setup>
import { ref, watch } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import debounce from 'lodash/debounce';

const props = defineProps({
    announcements: Object,
    filters: Object,
    can: Object,
});

const search = ref(props.filters.q || '');
const status = ref(props.filters.status || '');
const scope = ref(props.filters.scope_type || '');
const pinned = ref(props.filters.pinned || '');

watch([search, status, scope, pinned], debounce(() => {
    router.get('/admin/announcements', { 
        q: search.value, 
        status: status.value, 
        scope_type: scope.value,
        pinned: pinned.value,
    }, { preserveState: true, replace: true });
}, 300));

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
                    <Link
                        v-if="can?.create !== false"
                        href="/admin/announcements/create"
                        class="inline-flex items-center px-5 py-2.5 bg-blue-600 text-white text-sm font-semibold rounded-full shadow-lg shadow-blue-300/70 hover:bg-blue-700 transition transform hover:-translate-y-0.5"
                    >
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        Tambah Pengumuman
                    </Link>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <!-- Filters -->
                <div class="p-4 border-b border-gray-100 bg-gray-50 flex flex-wrap gap-4">
                    <div class="flex-1 min-w-[200px]">
                        <input 
                            v-model="search" 
                            type="text" 
                            placeholder="Cari judul atau isi..." 
                            class="input-field w-full"
                        >
                    </div>
                    <select v-model="status" class="input-field w-auto">
                        <option value="">Semua Status</option>
                        <option value="active">Aktif</option>
                        <option value="inactive">Non-aktif</option>
                    </select>
                    <select v-model="scope" class="input-field w-auto">
                        <option value="">Semua Scope</option>
                        <option value="global_all">Global (All)</option>
                        <option value="global_officers">Global (Pengurus)</option>
                        <option value="unit">Unit</option>
                    </select>
                    <select v-model="pinned" class="input-field w-auto">
                        <option value="">Semua Pin</option>
                        <option value="pinned">Pinned</option>
                        <option value="not_pinned">Not Pinned</option>
                    </select>
                </div>

                <!-- Table -->
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Judul</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Target Audience</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tanggal</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-if="announcements.data.length === 0">
                                <td colspan="5" class="px-6 py-4 text-center text-gray-500">
                                    Tidak ada data pengumuman.
                                </td>
                            </tr>
                            <tr v-for="item in announcements.data" :key="item.id" class="hover:bg-gray-50">
                                <td class="px-6 py-4">
                                    <div class="text-sm font-medium text-gray-900">{{ item.title }}</div>
                                    <div class="text-xs text-gray-500 truncate max-w-xs">{{ item.body.substring(0, 50) }}...</div>
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
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {{ formatDate(item.created_at) }}
                                    <div class="text-xs">by {{ item.creator?.name }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <Link :href="`/admin/announcements/${item.id}/edit`" class="text-indigo-600 hover:text-indigo-900 mr-3">Edit</Link>
                                    <button @click="deleteItem(item)" class="text-red-600 hover:text-red-900">Hapus</button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div class="px-6 py-4 border-t border-gray-200" v-if="announcements.prev_page_url || announcements.next_page_url">
                    <div class="flex justify-between">
                         <Link 
                            v-if="announcements.prev_page_url" 
                            :href="announcements.prev_page_url" 
                            class="px-3 py-1 border rounded hover:bg-gray-50"
                        >Sebelumnya</Link>
                        <div v-else></div>

                        <Link 
                            v-if="announcements.next_page_url" 
                            :href="announcements.next_page_url" 
                            class="px-3 py-1 border rounded hover:bg-gray-50"
                        >Next</Link>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
