<template>
  <AppLayout page-title="Permintaan Perubahan Data">
    <div class="space-y-6">
      <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h2 class="text-lg font-semibold text-neutral-900">Permintaan Perubahan Data</h2>
          <p class="text-sm text-neutral-500">Daftar permintaan perubahan data anggota yang perlu persetujuan.</p>
        </div>
      </div>

      <!-- Stats -->
      <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <SummaryCard title="Total Request" :value="stats.total" color="blue" icon="document" />
        <SummaryCard title="Menunggu" :value="stats.pending" color="yellow" icon="clock" />
        <SummaryCard title="Disetujui" :value="stats.approved" color="green" icon="check" />
        <SummaryCard title="Ditolak" :value="stats.rejected" color="red" icon="x" />
      </div>

      <CardContainer padding="sm">
        <div class="flex flex-wrap items-center gap-3">
          <SelectField class="w-48" v-model="status" :options="statusOptions" />
        </div>
      </CardContainer>

      <CardContainer padding="none" class="overflow-hidden">
      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-neutral-200">
          <thead class="bg-neutral-50">
            <tr>
              <th class="px-4 py-2 text-left text-xs text-neutral-500">Anggota</th>
              <th class="px-4 py-2 text-left text-xs text-neutral-500">Status</th>
              <th class="px-4 py-2 text-left text-xs text-neutral-500">Perubahan</th>
              <th class="px-4 py-2 text-left text-xs text-neutral-500">Tanggal</th>
              <th class="px-4 py-2 text-right text-xs text-neutral-500">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-neutral-200 bg-white">
            <tr v-for="i in items.data" :key="i.id">
              <td class="px-4 py-2 text-sm">{{ i.member?.full_name || '-' }}</td>
              <td class="px-4 py-2 text-sm">
                <Badge :variant="statusVariant(i.status)">{{ i.status }}</Badge>
              </td>
              <td class="px-4 py-2 text-sm text-neutral-600">
                <div v-if="i.new_data" class="text-xs space-y-1">
                  <div v-if="i.new_data.address"><strong>Alamat:</strong> {{ i.new_data.address }}</div>
                  <div v-if="i.new_data.phone"><strong>Telepon:</strong> {{ i.new_data.phone }}</div>
                  <div v-if="i.new_data.emergency_contact"><strong>Kontak Darurat:</strong> {{ i.new_data.emergency_contact }}</div>
                </div>
                <span v-else class="text-neutral-400">-</span>
              </td>
              <td class="px-4 py-2 text-sm text-neutral-500">{{ formatDate(i.created_at) }}</td>
              <td class="px-4 py-2 text-right text-sm">
                <template v-if="i.status === 'pending'">
                  <button @click="approve(i)" :disabled="processing" class="px-2 py-1 bg-green-100 text-green-700 rounded text-xs hover:bg-green-200 font-medium transition disabled:opacity-50">Approve</button>
                  <button @click="openRejectModal(i)" :disabled="processing" class="ml-2 px-2 py-1 bg-red-100 text-red-700 rounded text-xs hover:bg-red-200 font-medium transition disabled:opacity-50">Reject</button>
                </template>
                <span v-else class="text-neutral-400 text-xs">-</span>
              </td>
            </tr>
            <tr v-if="!items.data?.length">
              <td colspan="5" class="px-4 py-8 text-center text-neutral-500">Tidak ada permintaan perubahan</td>
            </tr>
          </tbody>
        </table>
      </div>
      <Pagination :paginator="items" />
    </CardContainer>
  </div>
  
  <!-- Reject Modal -->
  <ModalBase v-model:show="rejectModalOpen" title="Tolak Permintaan">
      <div class="space-y-4">
        <p class="text-sm text-neutral-600">
          Berikan alasan penolakan untuk permintaan dari <strong>{{ selectedRequest?.member?.full_name }}</strong>
        </p>
        <div>
          <label class="block text-xs text-neutral-600 mb-1">Alasan Penolakan <span class="text-red-500">*</span></label>
          <textarea 
            v-model="rejectNotes" 
            class="w-full rounded border px-3 py-2 text-sm" 
            rows="3" 
            placeholder="Masukkan alasan penolakan..."
          ></textarea>
          <div v-if="rejectNotesError" class="text-xs text-status-error mt-1">{{ rejectNotesError }}</div>
        </div>
      </div>
      <template #footer>
        <div class="flex justify-end gap-2">
          <SecondaryButton @click="closeRejectModal">Batal</SecondaryButton>
          <PrimaryButton @click="confirmReject" :disabled="processing">Tolak Permintaan</PrimaryButton>
        </div>
      </template>
    </ModalBase>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import CardContainer from '@/Components/UI/CardContainer.vue';
import PrimaryButton from '@/Components/UI/PrimaryButton.vue';
import SecondaryButton from '@/Components/UI/SecondaryButton.vue';
import SelectField from '@/Components/UI/SelectField.vue';
import Badge from '@/Components/UI/Badge.vue';
import ModalBase from '@/Components/UI/ModalBase.vue';
import Pagination from '@/Components/UI/Pagination.vue';
import SummaryCard from '@/Components/UI/SummaryCard.vue';
import { usePage, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const page = usePage();
const items = computed(() => page.props.items);
const stats = computed(() => page.props.stats || {});
const status = ref(page.props.filters?.status || '');
const statusOptions = [
  { label: 'Semua', value: '' },
  { label: 'Pending', value: 'pending' },
  { label: 'Approved', value: 'approved' },
  { label: 'Rejected', value: 'rejected' },
];
const processing = ref(false);

// Reject modal state
const rejectModalOpen = ref(false);
const selectedRequest = ref(null);
const rejectNotes = ref('');
const rejectNotesError = ref('');


watch(() => page.props.filters?.status, (val) => {
  const next = val || '';
  if (next !== status.value) status.value = next;
});

watch(status, v => {
  router.get('/admin/updates', { status: v || undefined }, { preserveState: true, replace: true });
});

function approve(i) { 
  processing.value = true;
  router.post(`/admin/updates/${i.id}/approve`, {}, { 
    preserveScroll: true, 
    onSuccess: () => {
      router.reload({ only: ['items'] });
    },
    onFinish: () => { processing.value = false; }
  }); 
}

function openRejectModal(i) {
  selectedRequest.value = i;
  rejectNotes.value = '';
  rejectNotesError.value = '';
  rejectModalOpen.value = true;
}

function closeRejectModal() {
  rejectModalOpen.value = false;
  selectedRequest.value = null;
  rejectNotes.value = '';
  rejectNotesError.value = '';
}

function confirmReject() {
  if (!rejectNotes.value.trim()) {
    rejectNotesError.value = 'Alasan penolakan wajib diisi';
    return;
  }
  
  processing.value = true;
  router.post(`/admin/updates/${selectedRequest.value.id}/reject`, { notes: rejectNotes.value }, { 
    preserveScroll: true, 
    onSuccess: () => {
      closeRejectModal();
      router.reload({ only: ['items'] });
    },
    onFinish: () => { processing.value = false; }
  }); 
}

function statusVariant(s) {
  switch (s) {
    case 'approved': return 'success';
    case 'rejected': return 'danger';
    case 'pending': return 'warning';
    default: return 'neutral';
  }
}

function formatDate(dateStr) {
  if (!dateStr) return '-';
  const d = new Date(dateStr);
  return d.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}
</script>
