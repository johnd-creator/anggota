<script setup>
import { ref, watch, computed, onMounted, onUnmounted } from 'vue';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import debounce from 'lodash/debounce';
import { MagnifyingGlassIcon } from '@heroicons/vue/24/outline'; // Using same icon style as layout

const props = defineProps({
  placeholder: { type: String, default: 'Search...' },
  minChars: { type: Number, default: 2 },
  apiUrl: { type: String, default: '/api/search' },
  pageUrl: { type: String, default: '/search' },
});

const query = ref('');
const open = ref(false);
const loading = ref(false);
const results = ref({});
const allowedTypes = ref([]);
const activeIndex = ref(-1);
const searchContainer = ref(null);

const typeLabels = {
  announcements: 'Pengumuman',
  letters: 'Surat',
  aspirations: 'Aspirasi',
  finance_ledgers: 'Transaksi',
  dues_payments: 'Iuran',
  members: 'Anggota',
  users: 'User',
};

const flatItems = computed(() => {
  let items = [];
  // Use order of API response or fixed order if desired, but here we iterate keys present
  Object.keys(results.value).forEach(type => {
      if (results.value[type] && results.value[type].length > 0) {
          items = items.concat(results.value[type].map(item => ({ ...item, group: type })));
      }
  });
  return items;
});

const performSearch = debounce(async (q) => {
    if (q.trim().length < props.minChars) {
        results.value = {};
        loading.value = false;
        return;
    }
    loading.value = true;
    try {
        const res = await axios.get(props.apiUrl, { params: { q } });
        results.value = res.data.results || {};
        allowedTypes.value = res.data.allowed_types || [];
        // Only open if we have input, even if results are empty (to show "No results")
        if (query.value.length >= props.minChars) {
            open.value = true;
        }
    } catch (e) {
        console.error("Search failed", e);
    } finally {
        loading.value = false;
    }
}, 300);

watch(query, (newVal) => {
    if (newVal.trim().length < props.minChars) {
        open.value = false;
        activeIndex.value = -1;
    } else {
        performSearch(newVal);
    }
});

const onInputFocus = () => {
    if (query.value.trim().length >= props.minChars) {
        open.value = true;
    }
};

const close = () => {
    open.value = false;
    activeIndex.value = -1;
};

const navigateToItem = (item) => {
    if (!item.url) return;
    close();
    router.visit(item.url);
};

const navigateToSearchPage = () => {
    if (!query.value.trim()) return;
    close();
    router.get(props.pageUrl, { q: query.value.trim() }, { preserveState: true });
};

const onKeyDown = (e) => {
    if (!open.value) return;

    if (e.key === 'ArrowDown') {
        e.preventDefault();
        if (activeIndex.value < flatItems.value.length - 1) {
            activeIndex.value++;
        }
    } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        if (activeIndex.value > -1) {
            activeIndex.value--;
        }
    } else if (e.key === 'Enter') {
        e.preventDefault();
        if (activeIndex.value >= 0 && flatItems.value[activeIndex.value]) {
            navigateToItem(flatItems.value[activeIndex.value]);
        } else {
            navigateToSearchPage();
        }
    } else if (e.key === 'Escape') {
        e.preventDefault();
        close();
    }
};

const onClickOutside = (e) => {
    if (searchContainer.value && !searchContainer.value.contains(e.target)) {
        close();
    }
};

onMounted(() => {
    document.addEventListener('click', onClickOutside);
});

onUnmounted(() => {
    document.removeEventListener('click', onClickOutside);
});
</script>

<template>
  <div class="relative w-full max-w-xl" ref="searchContainer">
    <div class="relative">
      <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
        <MagnifyingGlassIcon class="h-5 w-5 text-neutral-400" />
      </div>
      <input
        v-model="query"
        type="text"
        :placeholder="placeholder"
        class="block w-full pl-10 pr-3 py-2 border border-neutral-300 rounded-lg leading-5 bg-neutral-50 placeholder-neutral-500 focus:outline-none focus:bg-white focus:border-brand-primary-500 focus:ring-1 focus:ring-brand-primary-500 sm:text-sm transition duration-150 ease-in-out"
        @focus="onInputFocus"
        @keydown="onKeyDown"
      />
      <div v-if="loading" class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
          <svg class="animate-spin h-4 w-4 text-neutral-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
          </svg>
      </div>
    </div>

    <!-- Dropdown -->
    <div
      v-if="open"
      class="absolute z-50 mt-1 w-full bg-white shadow-lg max-h-96 rounded-md py-1 text-base ring-1 ring-black ring-opacity-5 overflow-auto focus:outline-none sm:text-sm"
    >
        <template v-if="flatItems.length > 0">
            <template v-for="(items, type) in results" :key="type">
                <div v-if="items && items.length > 0" class="border-b last:border-b-0 border-neutral-100">
                    <div class="px-3 py-2 bg-neutral-50 text-xs font-semibold text-neutral-500 uppercase tracking-wider sticky top-0">
                        {{ typeLabels[type] || type }}
                    </div>
                    <ul>
                        <li
                            v-for="item in items"
                            :key="item.id + '_' + item.type"
                            @click="navigateToItem(item)"
                            class="cursor-pointer select-none relative py-2 pl-3 pr-4 hover:bg-neutral-50"
                            :class="{ 'bg-brand-primary-50': flatItems[activeIndex] && flatItems[activeIndex].id === item.id && flatItems[activeIndex].type === item.type }"
                        >
                            <div class="flex flex-col">
                                <span class="font-medium text-neutral-900 truncate">{{ item.title }}</span>
                                <span class="text-xs text-neutral-500 truncate">{{ item.snippet }}</span>
                                <div class="flex items-center gap-2 mt-1">
                                    <span v-for="(val, k) in item.meta" :key="k" class="text-[10px] text-neutral-400 bg-neutral-100 px-1 rounded">
                                        {{ val }}
                                    </span>
                                </div>
                            </div>
                        </li>
                    </ul>
                </div>
            </template>
        </template>
        
        <div v-else-if="!loading" class="px-4 py-8 text-center text-neutral-500">
            No results found for "{{ query }}"
        </div>

        <div class="px-3 py-2 bg-neutral-50 border-t border-neutral-100 text-xs text-neutral-400 flex justify-between">
            <span>Press <strong>Enter</strong> to see all results</span>
            <span><strong>Esc</strong> to close</span>
        </div>
    </div>
  </div>
</template>
