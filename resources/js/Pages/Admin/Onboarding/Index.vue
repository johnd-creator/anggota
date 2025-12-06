<template>
  <AppLayout page-title="Onboarding Requests">
    <div class="mb-4 flex items-center gap-3">
      <InputField v-model="search" placeholder="Cari nama/email" />
      <SelectField v-model="filter.status" :options="statusOptions" />
      <SelectField v-model="filter.unit" :options="unitOptions" />
    </div>
    <div v-if="!items.data.length" class="p-8 text-center text-neutral-600">
      Belum ada permintaan
    </div>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
      <CardContainer v-for="p in items.data" :key="p.id" padding="lg" shadow="sm" hoverable @click="openPanel(p)" class="cursor-pointer">
        <div class="flex items-center justify-between">
          <div>
            <div class="font-semibold text-neutral-900">{{ p.name }}</div>
            <div class="text-sm text-neutral-600">{{ p.email }}</div>
          </div>
          <Badge :variant="statusVariant(p.status)">{{ p.status }}</Badge>
        </div>
        <div class="mt-3 text-sm text-neutral-700">Unit: {{ p.unit?.name || '-' }}</div>
        <div class="mt-2 text-xs text-neutral-500">Tanggal: {{ p.created_at || '-' }}</div>
      </CardContainer>
    </div>

    <ModalBase :show="panelOpen" size="xl" @close="panelOpen=false">
      <template #header>Detail Pengajuan</template>
      <div v-if="activeItem" class="space-y-4">
        <div class="flex items-center justify-between">
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
          <div class="md:col-span-2 flex justify-end gap-2">
            <SecondaryButton type="button" @click="openReject(activeItem)">Tolak</SecondaryButton>
            <PrimaryButton type="submit">Terima</PrimaryButton>
          </div>
        </form>
      </div>
    </ModalBase>

    <ModalBase :show="rejectOpen" @close="rejectOpen=false" title="Konfirmasi Penolakan">
      <form @submit.prevent="submitReject" class="space-y-3">
        <label class="block text-sm font-semibold text-neutral-700 mb-1">Reason</label>
        <textarea v-model="rejectReason" class="w-full rounded-lg border border-neutral-300 px-3 py-2" required></textarea>
        <div class="flex justify-end gap-2" slot="footer">
          <SecondaryButton type="button" @click="rejectOpen=false">Batal</SecondaryButton>
          <PrimaryButton type="submit">Submit</PrimaryButton>
        </div>
      </form>
    </ModalBase>
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
import { usePage, router } from '@inertiajs/vue3';
import Toast from '@/Components/UI/Toast.vue';
import { ref, reactive } from 'vue';

const page = usePage();
const items = page.props.items;
const units = page.props.units || [];
const positions = page.props.positions || [];
const unitOptions = units.map(u => ({ label: `${u.code} - ${u.name}`, value: u.id }));
const positionOptions = positions.map(p => ({ label: p.name, value: p.id }));
const statusOptions = [
  {label:'Semua', value:''},
  {label:'pending', value:'pending'},
  {label:'approved', value:'approved'},
  {label:'rejected', value:'rejected'},
];
const search = ref('');
const filter = reactive({ status:'', unit:'' });

const panelOpen = ref(false);
const activeItem = ref(null);
const rejectOpen = ref(false);
const approveForm = reactive({ full_name:'', nip:'', union_position_id:'', email:'', join_date:'', organization_unit_id:'' });
const rejectReason = ref('');
const toast = reactive({ show:false, message:'', type:'info' });

function openPanel(p){ panelOpen.value = true; activeItem.value = p; approveForm.full_name=p.name; approveForm.email=p.email; approveForm.organization_unit_id=p.organization_unit_id || '' }
function openReject(p){ rejectOpen.value = true; activeItem.value = p; }

function submitApprove(){
  router.post(`/admin/onboarding/${activeItem.value.id}/approve`, approveForm, {
    onSuccess(){ panelOpen.value=false; toast.message='Pengajuan disetujui'; toast.type='success'; toast.show=true; setTimeout(()=>toast.show=false,3000); },
    onError(){ toast.message='Gagal menyetujui pengajuan'; toast.type='error'; toast.show=true; setTimeout(()=>toast.show=false,3000); }
  })
}
function submitReject(){
  router.post(`/admin/onboarding/${activeItem.value.id}/reject`, { reason: rejectReason.value }, {
    onSuccess(){ rejectOpen.value=false; panelOpen.value=false; toast.message='Pengajuan ditolak'; toast.type='warning'; toast.show=true; setTimeout(()=>toast.show=false,3000); },
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
</script>
