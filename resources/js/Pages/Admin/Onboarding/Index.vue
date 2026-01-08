<template>
  <AppLayout page-title="Onboarding Requests">
    <div class="space-y-6">
      <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h2 class="text-lg font-semibold text-neutral-900">Onboarding Request</h2>
          <p class="text-sm text-neutral-500">Daftar permintaan registrasi anggota baru (Reguler).</p>
        </div>
      </div>

      <!-- Stats -->
      <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <SummaryCard title="Total Request" :value="stats.total" color="blue" icon="document" />
        <SummaryCard title="Menunggu" :value="stats.pending" color="yellow" icon="clock" />
        <SummaryCard title="Diterima" :value="stats.approved" color="green" icon="check" />
        <SummaryCard title="Ditolak" :value="stats.rejected" color="red" icon="x" />
      </div>

    <CardContainer padding="sm">
      <div class="flex flex-wrap items-center gap-3">
        <div class="w-full max-w-xs">
          <InputField v-model="search" placeholder="Cari nama/email" class="w-full" />
        </div>
        <div>
          <SelectField v-model="filter.status" :options="statusOptions" class="w-40" />
        </div>
        <div>
          <SelectField v-model="filter.unit" :options="unitOptions" class="w-64" />
        </div>
      </div>
    </CardContainer>

    <CardContainer padding="none" class="overflow-hidden">
      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-neutral-200">
          <thead class="bg-neutral-50">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Nama</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Email</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Unit</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Status</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Tanggal</th>
              <th class="px-6 py-3 text-right text-xs font-medium text-neutral-500 uppercase tracking-wider">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-neutral-200 bg-white">
            <tr v-for="p in items.data" :key="p.id" class="hover:bg-neutral-50">
              <td class="px-6 py-4 text-sm font-medium text-neutral-900">{{ p.name }}</td>
              <td class="px-6 py-4 text-sm text-neutral-600">{{ p.email }}</td>
              <td class="px-6 py-4 text-sm text-neutral-600">{{ p.unit?.name || '-' }}</td>
              <td class="px-6 py-4 text-sm">
                <Badge :variant="statusVariant(p.status)">{{ p.status }}</Badge>
              </td>
              <td class="px-6 py-4 text-sm text-neutral-600">{{ formatDate(p.created_at) }}</td>
              <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                <div v-if="p.status === 'pending'" class="flex justify-end gap-2">
                  <button @click="openPanel(p)" class="px-2 py-1 bg-green-100 text-green-700 rounded text-xs hover:bg-green-200 font-medium transition">Approve</button>
                  <button @click="openReject(p)" class="px-2 py-1 bg-red-100 text-red-700 rounded text-xs hover:bg-red-200 font-medium transition">Reject</button>
                </div>
                <span v-else class="text-neutral-400">-</span>
              </td>
            </tr>
            <tr v-if="items.data.length === 0">
              <td colspan="6" class="px-6 py-10 text-center text-neutral-500">Belum ada permintaan.</td>
            </tr>
          </tbody>
        </table>
      </div>
      <Pagination :paginator="items" />
    </CardContainer>

    <ModalBase :show="panelOpen" size="xl" @close="panelOpen=false">
      <template #header>Detail & Persetujuan Pengajuan</template>
      <div v-if="activeItem" class="space-y-4">
        <div class="flex items-center justify-between p-4 bg-neutral-50 rounded-lg">
          <div>
            <div class="font-semibold text-neutral-900">{{ activeItem.name }}</div>
            <div class="text-sm text-neutral-600">{{ activeItem.email }}</div>
          </div>
          <Badge :variant="statusVariant(activeItem.status)">{{ activeItem.status }}</Badge>
        </div>

        <form @submit.prevent="submitApprove" class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <InputField label="Full Name" v-model="approveForm.full_name" required />
          <InputField label="NIP" v-model="approveForm.nip" required />
          <SelectField label="Jabatan Serikat" v-model="approveForm.union_position_id" :options="positionOptions" required />
          <InputField label="Email" type="email" v-model="approveForm.email" required />
          <InputField label="Join Date" type="date" v-model="approveForm.join_date" required />
          <SelectField label="Unit" v-model="approveForm.organization_unit_id" :options="unitOptions" required />
          <div class="md:col-span-2 flex justify-end gap-2 pt-4 border-t border-neutral-100">
            <SecondaryButton type="button" @click="panelOpen=false">Batal</SecondaryButton>
            <PrimaryButton type="submit">Terima Anggota</PrimaryButton>
          </div>
        </form>
      </div>
    </ModalBase>

    <ModalBase :show="rejectOpen" @close="rejectOpen=false" title="Konfirmasi Penolakan">
      <form @submit.prevent="submitReject" class="space-y-3">
        <p class="text-sm text-neutral-600">Pastikan alasan penolakan jelas agar calon anggota dapat memperbaikinya.</p>
        <div>
          <label class="block text-sm font-semibold text-neutral-700 mb-1">Alasan Penolakan</label>
          <textarea v-model="rejectReason" class="w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm focus:ring-brand-primary-500 focus:border-brand-primary-500" rows="3" required placeholder="Contoh: Dokumen tidak lengkap..."></textarea>
        </div>
        <div class="flex justify-end gap-2 pt-2">
          <SecondaryButton type="button" @click="rejectOpen=false">Batal</SecondaryButton>
          <PrimaryButton type="submit" class="bg-red-600 hover:bg-red-700 focus:ring-red-500">Tolak Permintaan</PrimaryButton>
        </div>
      </form>
    </ModalBase>
  </div>
  </AppLayout>
  <Toast v-if="toast.show" :message="toast.message" :type="toast.type" position="top-center" @close="toast.show=false" />
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import CardContainer from '@/Components/UI/CardContainer.vue';
import InputField from '@/Components/UI/InputField.vue';
import SelectField from '@/Components/UI/SelectField.vue';
import ModalBase from '@/Components/UI/ModalBase.vue';
import Badge from '@/Components/UI/Badge.vue';
import PrimaryButton from '@/Components/UI/PrimaryButton.vue';
import SecondaryButton from '@/Components/UI/SecondaryButton.vue';
import Pagination from '@/Components/UI/Pagination.vue';
import SummaryCard from '@/Components/UI/SummaryCard.vue';
import { usePage, router } from '@inertiajs/vue3';
import Toast from '@/Components/UI/Toast.vue';
import { ref, reactive, watch } from 'vue';

const page = usePage();
const items = page.props.items;
const units = page.props.units || [];
const positions = page.props.positions || [];
const stats = page.props.stats || [];
const unitOptions = units.map(u => ({ label: `${u.code} - ${u.name}`, value: u.id }));
const positionOptions = positions.map(p => ({ label: p.name, value: p.id }));
const statusOptions = [
  {label:'Semua Status', value:''},
  {label:'Pending', value:'pending'},
  {label:'Approved', value:'approved'},
  {label:'Rejected', value:'rejected'},
];
const search = ref('');
const filter = reactive({ status:'', unit:'' });

const panelOpen = ref(false);
const activeItem = ref(null);
const rejectOpen = ref(false);
const approveForm = reactive({ full_name:'', nip:'', union_position_id:'', email:'', join_date:'', organization_unit_id:'' });
const rejectReason = ref('');
const toast = reactive({ show:false, message:'', type:'info' });

watch([search, () => filter.status, () => filter.unit], ([s, st, u]) => {
    router.get('/admin/onboarding', { search: s, status: st, unit: u }, { preserveState: true, replace: true });
});


function openPanel(p){ 
    panelOpen.value = true; 
    activeItem.value = p; 
    approveForm.full_name=p.name; 
    approveForm.email=p.email; 
    approveForm.organization_unit_id=p.organization_unit_id || '';
    approveForm.nip = '';
    approveForm.union_position_id = '';
    approveForm.join_date = new Date().toISOString().split('T')[0];
}
function openReject(p){ rejectOpen.value = true; activeItem.value = p; rejectReason.value = ''; }

function submitApprove(){
  router.post(`/admin/onboarding/${activeItem.value.id}/approve`, approveForm, {
    onSuccess(){ panelOpen.value=false; },
    onError(){ toast.message='Gagal menyetujui pengajuan'; toast.type='error'; toast.show=true; setTimeout(()=>toast.show=false,3000); }
  })
}
function submitReject(){
  router.post(`/admin/onboarding/${activeItem.value.id}/reject`, { reason: rejectReason.value }, {
    onSuccess(){ rejectOpen.value=false; panelOpen.value=false; },
    onError(){ toast.message='Gagal menolak pengajuan'; toast.type='error'; toast.show=true; setTimeout(()=>toast.show=false,3000); }
  })
}

function statusVariant(s){
  switch (s) {
    case 'pending': return 'warning';
    case 'approved': return 'success';
    case 'rejected': return 'danger';
    default: return 'neutral';
  }
}

function formatDate(dateStr) {
  if (!dateStr) return '-'
  return new Date(dateStr).toLocaleDateString('id-ID', { year: 'numeric', month: 'short', day: 'numeric' })
}
</script>
