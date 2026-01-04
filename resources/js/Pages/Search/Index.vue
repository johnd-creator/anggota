<script setup>
import { computed } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import Pagination from '@/Components/UI/Pagination.vue';
import { MagnifyingGlassIcon } from '@heroicons/vue/24/outline'; // Consistent icon

const props = defineProps({
    query: String,
    activeType: String,
    results: Object, // Map or paginator depending on type
    allowed_types: Array,
});

const typeLabels = {
  announcements: 'Pengumuman',
  letters: 'Surat',
  aspirations: 'Aspirasi',
  finance_ledgers: 'Transaksi',
  dues_payments: 'Iuran',
  members: 'Anggota',
  users: 'User',
};

const tabs = computed(() => {
    const list = [{ id: 'all', label: 'Semua' }];
    props.allowed_types.forEach(t => {
        list.push({ id: t, label: typeLabels[t] || t });
    });
    return list;
});

const switchTab = (type) => {
    router.get('/search', { q: props.query, type: type }, { preserveState: true, replace: true });
};

const submitSearch = (e) => {
    const q = e.target.value;
    if (q.trim().length >= 2) {
        router.get('/search', { q: q, type: props.activeType }, { preserveState: true });
    }
};

</script>

<template>
    <AppLayout title="Pencarian">
        <template #header>
            <h2 class="font-semibold text-xl text-neutral-800 leading-tight">
                Hasil Pencarian
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <!-- Search Box -->
                <div class="mb-6">
                     <div class="relative max-w-xl">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <MagnifyingGlassIcon class="h-5 w-5 text-neutral-400" />
                        </div>
                        <input
                            :value="query"
                            @keydown.enter="submitSearch"
                            type="text"
                            placeholder="Cari..."
                            class="block w-full pl-10 pr-3 py-2 border border-neutral-300 rounded-lg leading-5 bg-white placeholder-neutral-500 focus:outline-none focus:ring-1 focus:ring-brand-primary-500 focus:border-brand-primary-500 sm:text-sm"
                        />
                    </div>
                </div>

                <!-- Tabs -->
                <div class="border-b border-neutral-200 mb-6">
                    <nav class="-mb-px flex space-x-8 overflow-x-auto" aria-label="Tabs">
                        <button
                            v-for="tab in tabs"
                            :key="tab.id"
                            @click="switchTab(tab.id)"
                            :class="[
                                activeType === tab.id
                                    ? 'border-brand-primary-500 text-brand-primary-600'
                                    : 'border-transparent text-neutral-500 hover:text-neutral-700 hover:border-neutral-300',
                                'whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm'
                            ]"
                        >
                            {{ tab.label }}
                        </button>
                    </nav>
                </div>

                <!-- Content -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 min-h-[300px]">
                    <p v-if="!query" class="text-neutral-500 text-center py-8">Silakan masukkan kata kunci pencarian.</p>

                    <div v-else>
                        <!-- Grouped View (All) -->
                        <div v-if="activeType === 'all'">
                             <div v-for="(items, type) in results" :key="type" class="mb-8 last:mb-0">
                                <div class="flex items-center justify-between mb-4 border-b pb-2">
                                    <h3 class="text-lg font-semibold capitalize text-neutral-800">{{ typeLabels[type] || type }}</h3>
                                    <button @click="switchTab(type)" class="text-sm text-brand-primary-600 hover:underline">Lihat semua</button>
                                </div>
                                <div v-if="items.length === 0" class="text-sm text-neutral-500 italic">
                                    Tidak ada hasil.
                                </div>
                                <div class="grid gap-4">
                                    <Link v-for="item in items" :key="item.id" :href="item.url" class="block p-4 border border-neutral-200 rounded-lg hover:bg-neutral-50 transition group">
                                        <div class="font-medium text-brand-primary-600 group-hover:text-brand-primary-700">{{ item.title }}</div>
                                        <div class="text-sm text-neutral-600 mt-1 line-clamp-2">{{ item.snippet }}</div>
                                        <div class="text-xs text-neutral-400 mt-2 flex gap-2 items-center">
                                            <span v-for="(val, key) in item.meta" :key="key" class="bg-neutral-100 px-2 py-0.5 rounded text-neutral-500 font-medium">{{ val }}</span>
                                        </div>
                                    </Link>
                                </div>
                             </div>

                             <div v-if="Object.keys(results).length === 0" class="text-center py-12 text-neutral-500">
                                 Tidak ada hasil ditemukan untuk "{{ query }}".
                             </div>
                        </div>

                        <!-- Paginated View (Specific Type) -->
                        <div v-else>
                            <!-- results[activeType] is the paginator object -->
                            <div v-if="results[activeType]?.data?.length > 0">
                                <div class="grid gap-4 mb-6">
                                    <Link v-for="item in results[activeType].data" :key="item.id" :href="item.url" class="block p-4 border border-neutral-200 rounded-lg hover:bg-neutral-50 transition group">
                                        <div class="font-medium text-brand-primary-600 group-hover:text-brand-primary-700">{{ item.title }}</div>
                                        <div class="text-sm text-neutral-600 mt-1 line-clamp-2">{{ item.snippet }}</div>
                                        <div class="text-xs text-neutral-400 mt-2 flex gap-2 items-center">
                                             <span v-for="(val, key) in item.meta" :key="key" class="bg-neutral-100 px-2 py-0.5 rounded text-neutral-500 font-medium">{{ val }}</span>
                                        </div>
                                    </Link>
                                </div>
                                <Pagination :paginator="results[activeType]" />
                            </div>
                            <div v-else class="text-center py-12 text-neutral-500">
                                Tidak ada hasil {{ typeLabels[activeType] || activeType }} untuk "{{ query }}".
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
