<template>
  <AppLayout page-title="Detail Aspirasi">
    <div class="max-w-4xl mx-auto space-y-6">
      <!-- Header -->
      <div class="flex items-start justify-between">
        <div>
          <Link href="/member/aspirations" class="text-sm text-neutral-500 hover:text-neutral-700 flex items-center gap-1 mb-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
            </svg>
            Kembali ke Daftar
          </Link>
          <h1 class="text-2xl font-bold text-neutral-900">{{ aspiration.title }}</h1>
          <div class="flex flex-wrap items-center gap-3 mt-2 text-sm text-neutral-500">
            <span class="flex items-center gap-1">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
              </svg>
              {{ aspiration.can_view_creator ? ($toTitleCase(aspiration.member?.full_name) || 'Anonym') : 'Anonym' }}
            </span>
            <span class="flex items-center gap-1">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
              </svg>
              {{ aspiration.category?.name }}
            </span>
            <span class="flex items-center gap-1">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
              {{ formatDate(aspiration.created_at) }}
            </span>
          </div>
        </div>
        <div class="flex items-center gap-3">
          <div class="flex items-center gap-2 px-3 py-2 bg-neutral-100 rounded-lg">
            <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" />
            </svg>
            <span class="text-lg font-bold text-neutral-900">{{ aspiration.support_count }}</span>
            <span class="text-sm text-neutral-500">dukungan</span>
          </div>
          <span :class="['px-3 py-2 text-sm font-medium rounded-full', statusClass(aspiration.status)]">
            {{ statusLabel(aspiration.status) }}
          </span>
        </div>
      </div>

      <!-- Merged Alert -->
      <div v-if="aspiration.merged_into_id" class="p-4 bg-orange-50 rounded-lg border border-orange-200">
        <div class="flex items-start gap-3">
          <svg class="w-5 h-5 text-orange-600 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
          </svg>
          <div>
            <p class="text-sm font-medium text-orange-800">Aspirasi ini telah digabungkan</p>
            <p class="text-sm text-orange-700 mt-1">
              Aspirasi ini telah digabungkan ke aspirasi lain. Dukungan telah dipindahkan.
            </p>
          </div>
        </div>
      </div>

      <!-- Body -->
      <CardContainer padding="lg">
        <h2 class="text-sm font-medium text-neutral-500 uppercase tracking-wider mb-3">Isi Aspirasi</h2>
        <div class="prose prose-sm max-w-none text-neutral-700 whitespace-pre-wrap">{{ aspiration.body }}</div>
        <div v-if="aspiration.tags?.length" class="flex flex-wrap gap-2 mt-4 pt-4 border-t border-neutral-100">
          <span v-for="tag in aspiration.tags" :key="tag.id" class="px-2 py-1 bg-neutral-100 text-neutral-600 rounded-full text-xs">
            #{{ tag.name }}
          </span>
        </div>
      </CardContainer>

      <!-- Support Button -->
      <CardContainer v-if="!aspiration.merged_into_id" padding="lg">
        <div class="flex items-center justify-between">
          <div>
            <h3 class="text-sm font-medium text-neutral-900">Dukung aspirasi ini</h3>
            <p class="text-xs text-neutral-500 mt-1">
              {{ aspiration.is_own ? 'Anda tidak dapat mendukung aspirasi sendiri' : 'Klik untuk mendukung atau mencabut dukungan' }}
            </p>
          </div>
          <button
            @click="toggleSupport"
            :disabled="aspiration.is_own"
            :class="[
              'flex items-center gap-2 px-6 py-3 rounded-xl font-medium transition',
              aspiration.is_own ? 'bg-neutral-100 text-neutral-400 cursor-not-allowed' :
              aspiration.is_supported ? 'bg-[#1A2B63] text-white hover:bg-[#2E4080]' : 'bg-neutral-100 text-neutral-700 hover:bg-neutral-200'
            ]"
          >
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" />
            </svg>
            <span>{{ aspiration.is_supported ? 'Didukung' : 'Dukung' }}</span>
          </button>
        </div>
      </CardContainer>
    </div>
  </AppLayout>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import CardContainer from '@/Components/UI/CardContainer.vue';

const props = defineProps({
  aspiration: Object,
});

function toggleSupport() {
  router.post(`/member/aspirations/${props.aspiration.id}/support`, {}, {
    preserveScroll: true,
    onSuccess: () => {
      props.aspiration.is_supported = !props.aspiration.is_supported;
      props.aspiration.support_count += props.aspiration.is_supported ? 1 : -1;
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
