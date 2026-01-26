<template>
  <AppLayout page-title="Pengajuan Mutasi">
    <div class="space-y-6">
      <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h2 class="text-lg font-semibold text-neutral-900">Pengajuan Mutasi</h2>
          <p class="text-sm text-neutral-500">Proses mutasi anggota antar unit kerja.</p>
        </div>
        <div>
          <CtaButton href="/admin/mutations/create">
            <template #icon>
              <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            </template>
            Ajukan Mutasi
          </CtaButton>
        </div>
      </div>
      
      <!-- Stats -->
      <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <SummaryCard title="Total Pengajuan" :value="stats.total" color="blue" icon="document" />
        <SummaryCard title="Menunggu" :value="stats.pending" color="yellow" icon="clock" />
        <SummaryCard title="Disetujui" :value="stats.approved" color="green" icon="check" />
        <SummaryCard title="Ditolak" :value="stats.rejected" color="red" icon="x" />
      </div>

    <CardContainer padding="none" shadow="sm" class="overflow-hidden">      
      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-neutral-200">
          <thead class="bg-neutral-50">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Anggota</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Asal → Tujuan</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Status</th>
              <th class="px-6 py-3 text-right text-xs font-medium text-neutral-500 uppercase tracking-wider">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-neutral-200 bg-white">
            <tr v-for="i in items.data" :key="i.id" class="hover:bg-neutral-50">
              <td class="px-6 py-4 text-sm text-neutral-900 font-medium">{{ $toTitleCase(i.member.full_name) }}</td>
              <td class="px-6 py-4 text-sm text-neutral-600">{{ i.from_unit.name }} → {{ i.to_unit.name }}</td>
              <td class="px-6 py-4 text-sm">
                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full" 
                  :class="{
                    'bg-green-100 text-green-800': i.status === 'approved',
                    'bg-red-100 text-red-800': i.status === 'rejected',
                    'bg-yellow-100 text-yellow-800': i.status === 'pending',
                    'bg-neutral-100 text-neutral-600': i.status === 'cancelled'
                  }">
                  {{ statusLabel(i.status) }}
                </span>
              </td>
              <td class="px-6 py-4 text-right text-sm space-x-2">
                <Link :href="`/admin/mutations/${i.id}`" class="text-brand-primary-600 hover:text-brand-primary-900 font-medium">Detail</Link>
                <button
                  v-if="i.status === 'pending'"
                  @click="confirmCancel(i)"
                  class="text-red-600 hover:text-red-800 font-medium"
                  :disabled="cancellingId === i.id"
                >
                  {{ cancellingId === i.id ? 'Membatalkan...' : 'Batalkan' }}
                </button>
              </td>
            </tr>
            <tr v-if="items.data.length === 0">
              <td colspan="4" class="px-6 py-10 text-center text-neutral-500">Tidak ada pengajuan mutasi.</td>
            </tr>
          </tbody>
        </table>
      </div>
      <Pagination :paginator="items" />
    </CardContainer>
  </div>
  </AppLayout>
</template>

<script setup>
import { ref } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import CardContainer from '@/Components/UI/CardContainer.vue';
import Pagination from '@/Components/UI/Pagination.vue';
import SummaryCard from '@/Components/UI/SummaryCard.vue';
import CtaButton from '@/Components/UI/CtaButton.vue';
import { Link, usePage, router } from '@inertiajs/vue3';

const page = usePage();
const items = page.props.items;
const stats = page.props.stats;

const cancellingId = ref(null);

const statusLabel = (status) => {
  const labels = {
    pending: 'Menunggu',
    approved: 'Disetujui',
    rejected: 'Ditolak',
    cancelled: 'Dibatalkan'
  };
  return labels[status] || status;
};

const confirmCancel = (mutation) => {
  if (!confirm(`Batalkan pengajuan mutasi untuk ${this.$toTitleCase(mutation.member.full_name)}?`)) {
    return;
  }
  
  cancellingId.value = mutation.id;
  
  router.post(`/admin/mutations/${mutation.id}/cancel`, {}, {
    preserveScroll: true,
    onFinish: () => {
      cancellingId.value = null;
    }
  });
};
</script>
