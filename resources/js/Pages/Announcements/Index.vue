<script setup>
import { ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import debounce from 'lodash/debounce';

const props = defineProps({
    announcements: Object,
    filters: Object,
});

const search = ref(props.filters.q || '');

watch(search, debounce((val) => {
    router.get('/announcements', { q: val }, { preserveState: true, replace: true });
}, 300));

const formatDate = (date) => {
    return new Date(date).toLocaleDateString('id-ID', {
        day: 'numeric', month: 'long', year: 'numeric'
    });
};
</script>

<template>
    <AppLayout title="Pengumuman">
        <template #header>
            <h2 class="font-semibold text-xl text-neutral-800 leading-tight">
                Pengumuman
            </h2>
        </template>

        <div class="max-w-7xl mx-auto py-10 sm:px-6 lg:px-8">
            <div class="mb-6 max-w-md">
                <input 
                    v-model="search" 
                    type="text" 
                    placeholder="Cari pengumuman..." 
                    class="w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                >
            </div>

            <div class="space-y-6">
                <!-- Empty State -->
                <div v-if="announcements.data.length === 0" class="text-center py-12 bg-white rounded-lg shadow-sm">
                    <p class="text-gray-500">Tidak ada pengumuman ditemukan.</p>
                </div>

                <!-- Announcement Cards -->
                <div v-for="item in announcements.data" :key="item.id" class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="flex items-center gap-2 mb-2">
                             <span v-if="item.scope_type === 'global_all'" class="px-2 py-0.5 rounded text-[10px] font-bold bg-blue-100 text-blue-800 uppercase">Global</span>
                            <span v-else-if="item.scope_type === 'global_officers'" class="px-2 py-0.5 rounded text-[10px] font-bold bg-amber-100 text-amber-800 uppercase">Pengurus</span>
                            <span v-else-if="item.scope_type === 'unit'" class="px-2 py-0.5 rounded text-[10px] font-bold bg-green-100 text-green-800 uppercase">
                                {{ item.organization_unit_name || 'Unit' }}
                            </span>
                            <span class="text-xs text-gray-500">{{ formatDate(item.created_at) }}</span>
                            <span v-if="item.is_pinned" class="ml-auto text-xs text-indigo-600 font-semibold flex items-center gap-1">
                                📌 Pinned
                            </span>
                        </div>

                        <h3 class="text-xl font-bold text-gray-900 mb-2">{{ item.title }}</h3>
                        <div class="prose prose-sm max-w-none text-gray-700 whitespace-pre-line mb-4">
                            {{ item.body }}
                        </div>

                        <!-- Attachments -->
                        <div v-if="item.attachments && item.attachments.length > 0" class="border-t pt-3 mt-4">
                            <h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">Lampiran</h4>
                            <div class="flex flex-wrap gap-2">
                                <a 
                                    v-for="file in item.attachments" 
                                    :key="file.id" 
                                    :href="file.download_url" 
                                    class="inline-flex items-center gap-2 px-3 py-1.5 rounded-md bg-gray-50 border border-gray-200 text-sm text-gray-700 hover:bg-gray-100 transition-colors"
                                    target="_blank"
                                >
                                    <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                    {{ file.original_name }}
                                    <span class="text-xs text-gray-400">({{ (file.size / 1024).toFixed(0) }} KB)</span>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Pagination -->
                 <div v-if="announcements.links && announcements.links.length > 3" class="flex justify-center mt-6">
                    <div class="flex gap-1">
                        <Component 
                            v-for="(link, key) in announcements.links" 
                            :key="key"
                            :is="link.url ? Link : 'span'"
                            :href="link.url"
                            v-html="link.label"
                            class="px-3 py-1 rounded border text-sm"
                            :class="{ 
                                'bg-indigo-600 text-white border-indigo-600': link.active,
                                'bg-white text-gray-700 border-gray-300 hover:bg-gray-50': !link.active && link.url,
                                'text-gray-400 border-gray-200': !link.url
                            }"
                        />
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
