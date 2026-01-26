<template>
  <AppLayout page-title="Aspirasi & Saran">
    <div class="max-w-5xl mx-auto space-y-6">
      <!-- Header -->
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <h1 class="text-2xl font-bold text-neutral-900">Aspirasi Unit</h1>
          <p class="text-neutral-600 text-sm mt-1">Sampaikan saran dan dukung aspirasi anggota lain</p>
        </div>
        <CtaButton href="/member/aspirations/create">
          <template #icon>
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
            </svg>
          </template>
          Buat Aspirasi
        </CtaButton>
      </div>

      <!-- Filters -->
      <CardContainer padding="sm">
        <div class="flex flex-wrap gap-3 items-center">
          <select v-model="localFilters.category" @change="applyFilters" class="border border-neutral-300 rounded-lg px-3 py-2 text-sm bg-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            <option value="">Semua Kategori</option>
            <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
          </select>
          <select v-model="localFilters.status" @change="applyFilters" class="border border-neutral-300 rounded-lg px-3 py-2 text-sm bg-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            <option value="">Semua Status</option>
            <option value="new">Baru</option>
            <option value="in_progress">Diproses</option>
            <option value="resolved">Selesai</option>
          </select>
          <div class="flex gap-1 bg-neutral-100 rounded-lg p-1">
            <button @click="setSort('latest')" :class="['px-3 py-1.5 text-sm rounded-md transition', localFilters.sort === 'latest' ? 'bg-white shadow text-neutral-900' : 'text-neutral-600 hover:text-neutral-900']">
              Terbaru
            </button>
            <button @click="setSort('popular')" :class="['px-3 py-1.5 text-sm rounded-md transition', localFilters.sort === 'popular' ? 'bg-white shadow text-neutral-900' : 'text-neutral-600 hover:text-neutral-900']">
              Populer
            </button>
          </div>
        </div>
      </CardContainer>

      <!-- Aspirations List -->
      <div v-if="aspirations.data.length > 0" class="space-y-4">
        <CardContainer v-for="asp in aspirations.data" :key="asp.id" padding="lg" class="hover:shadow-md transition-shadow">
          <div class="flex gap-4">
            <!-- Support Button -->
            <div class="flex flex-col items-center">
              <IconButton
                aria-label="Dukung aspirasi"
                size="lg"
                :disabled="asp.is_own"
                :class="[
                  'w-12 h-12 rounded-xl',
                  asp.is_own ? 'bg-neutral-100 text-neutral-400' :
                  asp.is_supported ? 'bg-[#1A2B63] text-white hover:bg-[#2E4080]' : 'bg-neutral-100 text-neutral-600 hover:bg-neutral-200'
                ]"
                @click="toggleSupport(asp)"
              >
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" />
                </svg>
              </IconButton>
              <span class="text-sm font-semibold text-neutral-700 mt-1">{{ asp.support_count }}</span>
            </div>

            <!-- Content -->
            <div class="flex-1 min-w-0">
              <div class="flex items-start justify-between gap-2">
                <Link :href="'/member/aspirations/' + asp.id" class="text-lg font-semibold text-neutral-900 hover:text-blue-600 transition">
                  {{ asp.title }}
                </Link>
                <span :class="['px-2 py-1 text-xs font-medium rounded-full whitespace-nowrap', statusClass(asp.status)]">
                  {{ statusLabel(asp.status) }}
                </span>
              </div>
              <p class="text-neutral-600 text-sm mt-2 line-clamp-2">{{ asp.body }}</p>
              <div class="flex flex-wrap items-center gap-3 mt-3 text-xs text-neutral-500">
                <span class="flex items-center gap-1">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                  </svg>
                  {{ $toTitleCase(asp.member?.full_name) || 'Anonym' }}
                </span>
                <span class="flex items-center gap-1">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
                  </svg>
                  {{ asp.category?.name }}
                </span>
                <span class="flex items-center gap-1">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                  </svg>
                  {{ formatDate(asp.created_at) }}
                </span>
                <template v-if="asp.tags?.length">
                  <span v-for="tag in asp.tags" :key="tag.id" class="px-2 py-0.5 bg-neutral-100 rounded-full text-neutral-600">
                    #{{ tag.name }}
                  </span>
                </template>
              </div>
            </div>
          </div>
        </CardContainer>
      </div>

      <CardContainer v-else padding="lg" class="text-center">
        <div class="py-8">
          <svg class="w-16 h-16 mx-auto text-neutral-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
          </svg>
          <p class="text-neutral-500">Belum ada aspirasi di unit Anda</p>
          <SecondaryButton href="/member/aspirations/create" size="sm" class="mt-4 gap-2">
            Buat aspirasi pertama
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
            </svg>
          </SecondaryButton>
        </div>
      </CardContainer>

      <!-- Pagination -->
      <div v-if="aspirations.links?.length > 3" class="flex justify-center">
        <nav class="flex gap-1">
          <template v-for="(link, i) in paginationLinks" :key="i">
            <Link
              v-if="link.url"
              :href="link.url"
              v-html="link.label"
              class="px-3 py-2 text-sm rounded-lg border transition"
              :class="link.active ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-neutral-700 border-neutral-300 hover:bg-neutral-50'"
            />
            <span v-else v-html="link.label" class="px-3 py-2 text-sm text-neutral-400" />
          </template>
        </nav>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { reactive, computed } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import CardContainer from '@/Components/UI/CardContainer.vue';
import CtaButton from '@/Components/UI/CtaButton.vue';
import SecondaryButton from '@/Components/UI/SecondaryButton.vue';
import IconButton from '@/Components/UI/IconButton.vue';
import { useMobile } from '@/Composables/useMobile';

const props = defineProps({
  aspirations: Object,
  categories: Array,
  filters: Object,
});

const localFilters = reactive({
  category: props.filters.category || '',
  status: props.filters.status || '',
  sort: props.filters.sort || 'latest',
});

const { isMobile } = useMobile();

const paginationLinks = computed(() => {
  if (!props.aspirations.links) return [];
  
  if (isMobile.value) {
    // On mobile: show only Previous, current page (active), and Next
    return props.aspirations.links.filter(link => 
      link.label.includes('Previous') || 
      link.label.includes('Next') || 
      link.active
    );
  }
  
  // On desktop: show all links
  return props.aspirations.links;
});

function applyFilters() {
  router.get('/member/aspirations', {
    category: localFilters.category || undefined,
    status: localFilters.status || undefined,
    sort: localFilters.sort !== 'latest' ? localFilters.sort : undefined,
  }, { preserveState: true, replace: true });
}

function setSort(sort) {
  localFilters.sort = sort;
  applyFilters();
}

function toggleSupport(asp) {
  router.post(`/member/aspirations/${asp.id}/support`, {}, {
    preserveScroll: true,
    onSuccess: () => {
      asp.is_supported = !asp.is_supported;
      asp.support_count += asp.is_supported ? 1 : -1;
    },
  });
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
