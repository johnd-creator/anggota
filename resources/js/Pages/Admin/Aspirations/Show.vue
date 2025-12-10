<template>
  <AppLayout page-title="Detail Aspirasi">
    <div class="max-w-5xl mx-auto space-y-6">
      <!-- Header -->
      <div class="flex items-start justify-between">
        <!-- Back Button -->
        <div>
          <Link href="/admin/aspirations" class="text-sm text-neutral-500 hover:text-neutral-700 flex items-center gap-1 mb-2">
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
              {{ aspiration.member?.full_name }}
            </span>
            <span class="flex items-center gap-1">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
              </svg>
              {{ aspiration.unit?.name }}
            </span>
            <span class="flex items-center gap-1">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
              </svg>
              {{ aspiration.category?.name }}
            </span>
            <span>{{ formatDate(aspiration.created_at) }}</span>
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
        </div>
      </div>

      <!-- Merged Alert -->
      <div v-if="aspiration.merged_into_id" class="mt-4 p-4 bg-orange-50 rounded-lg border border-orange-200">
        <div class="flex items-start gap-3">
          <svg class="w-5 h-5 text-orange-600 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
          </svg>
          <div>
            <p class="text-sm font-medium text-orange-800">Aspirasi ini telah digabungkan</p>
            <p class="text-sm text-orange-700 mt-1">
              Aspirasi ini telah digabungkan ke 
              <Link :href="'/admin/aspirations/' + aspiration.merged_into_id" class="font-medium underline hover:no-underline">
                #{{ aspiration.merged_into_id }}
              </Link>
              . Dukungan telah dipindahkan.
            </p>
          </div>
        </div>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Main Content -->
        <div class="lg:col-span-2 space-y-6">
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

          <!-- Timeline -->
          <CardContainer padding="lg">
            <h2 class="text-sm font-medium text-neutral-500 uppercase tracking-wider mb-4">Riwayat Perubahan</h2>
            <div v-if="aspiration.updates?.length" class="space-y-4">
              <div v-for="update in aspiration.updates" :key="update.id" class="flex gap-3">
                <div class="w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center flex-shrink-0">
                  <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                  </svg>
                </div>
                <div class="flex-1">
                  <div class="flex items-center gap-2">
                    <span class="text-sm font-medium text-neutral-900">{{ update.user?.name }}</span>
                    <span class="text-xs text-neutral-500">{{ formatDateTime(update.created_at) }}</span>
                  </div>
                  <p class="text-sm text-neutral-600 mt-0.5">
                    Status diubah dari <span class="font-medium">{{ statusLabel(update.old_status) }}</span>
                    ke <span :class="['font-medium', statusTextClass(update.new_status)]">{{ statusLabel(update.new_status) }}</span>
                  </p>
                  <p v-if="update.notes" class="text-sm text-neutral-500 mt-1 italic">"{{ update.notes }}"</p>
                </div>
              </div>
            </div>
            <p v-else class="text-sm text-neutral-500">Belum ada perubahan status</p>
          </CardContainer>

          <!-- Merged From -->
          <CardContainer v-if="aspiration.merged_from?.length" padding="lg">
            <h2 class="text-sm font-medium text-neutral-500 uppercase tracking-wider mb-4">Aspirasi yang Digabungkan</h2>
            <div class="space-y-2">
              <div v-for="merged in aspiration.merged_from" :key="merged.id" class="flex items-center justify-between p-3 bg-neutral-50 rounded-lg">
                <div>
                  <p class="text-sm font-medium text-neutral-900">{{ merged.title }}</p>
                  <p class="text-xs text-neutral-500">Oleh {{ merged.member?.full_name }}</p>
                </div>
                <span class="text-sm text-neutral-500">+{{ merged.support_count }} dukungan</span>
              </div>
            </div>
          </CardContainer>
        </div>

        <!-- Sidebar -->
        <div class="space-y-6">
          <!-- Status Update -->
          <CardContainer v-if="!aspiration.merged_into_id" padding="lg">
            <h2 class="text-sm font-medium text-neutral-500 uppercase tracking-wider mb-4">Ubah Status</h2>
            <form @submit.prevent="updateStatus" class="space-y-4">
              <div>
                <select v-model="statusForm.status" class="w-full border border-neutral-300 rounded-lg px-3 py-2 text-sm">
                  <option value="new">Baru</option>
                  <option value="in_progress">Sedang Diproses</option>
                  <option value="resolved">Selesai</option>
                </select>
              </div>
              <div>
                <textarea v-model="statusForm.notes" rows="3" placeholder="Catatan (opsional)" class="w-full border border-neutral-300 rounded-lg px-3 py-2 text-sm resize-none"></textarea>
              </div>
              <button type="submit" :disabled="statusForm.processing" class="w-full px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition text-sm font-medium disabled:opacity-50">
                Update Status
              </button>
            </form>
          </CardContainer>

          <!-- Merge -->
          <CardContainer v-if="!aspiration.merged_into_id && mergeCandidates.length" padding="lg">
            <h2 class="text-sm font-medium text-neutral-500 uppercase tracking-wider mb-4">Gabungkan Aspirasi</h2>
            <form @submit.prevent="mergeAspiration" class="space-y-4">
              <div>
                <select v-model="mergeForm.target_id" class="w-full border border-neutral-300 rounded-lg px-3 py-2 text-sm">
                  <option value="">Pilih aspirasi target...</option>
                  <option v-for="c in mergeCandidates" :key="c.id" :value="c.id">{{ c.title }} ({{ c.support_count }} dukungan)</option>
                </select>
              </div>
              <button type="submit" :disabled="!mergeForm.target_id || mergeForm.processing" class="w-full px-4 py-2 bg-orange-600 text-white rounded-lg hover:bg-orange-700 transition text-sm font-medium disabled:opacity-50">
                Gabungkan
              </button>
            </form>
          </CardContainer>

          <!-- Supporters -->
          <CardContainer padding="lg">
            <h2 class="text-sm font-medium text-neutral-500 uppercase tracking-wider mb-4">Pendukung ({{ aspiration.support_count }})</h2>
            <div v-if="supporters.data.length" class="space-y-2">
              <div v-for="s in supporters.data" :key="s.id" class="text-sm text-neutral-700">
                {{ s.full_name }}
              </div>
              <Link v-if="supporters.next_page_url" :href="supporters.next_page_url" class="text-sm text-blue-600 hover:text-blue-700">
                Lihat lebih banyak...
              </Link>
            </div>
            <p v-else class="text-sm text-neutral-500">Belum ada pendukung</p>
          </CardContainer>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { useForm, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import CardContainer from '@/Components/UI/CardContainer.vue';

const props = defineProps({
  aspiration: Object,
  supporters: Object,
  mergeCandidates: Array,
});

const statusForm = useForm({
  status: props.aspiration.status || 'new',
  notes: '',
});

const mergeForm = useForm({
  target_id: '',
});

function updateStatus() {
  statusForm.patch(`/admin/aspirations/${props.aspiration.id}/status`, {
    preserveScroll: true,
    onSuccess: () => statusForm.reset('notes'),
  });
}

function mergeAspiration() {
  if (!confirm('Apakah Anda yakin ingin menggabungkan aspirasi ini? Tindakan ini tidak dapat dibatalkan.')) return;
  
  mergeForm.post(`/admin/aspirations/${props.aspiration.id}/merge`);
}

function statusLabel(status) {
  const labels = { new: 'Baru', in_progress: 'Diproses', resolved: 'Selesai', merged: 'Digabungkan' };
  return labels[status] || status;
}

function statusTextClass(status) {
  const classes = { new: 'text-yellow-600', in_progress: 'text-blue-600', resolved: 'text-green-600' };
  return classes[status] || '';
}

function formatDate(date) {
  return new Date(date).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' });
}

function formatDateTime(date) {
  return new Date(date).toLocaleString('id-ID', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}
</script>
