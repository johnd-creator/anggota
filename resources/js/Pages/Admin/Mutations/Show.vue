<template>
  <AppLayout page-title="Detail Mutasi">
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
      <CardContainer padding="lg" shadow="sm">
        <div class="space-y-2">
          <div class="text-sm text-neutral-700">Anggota</div>
          <div class="text-lg font-semibold">{{ mutation.member.full_name }}</div>
          <div class="text-xs text-neutral-600">{{ mutation.from_unit.name }} → {{ mutation.to_unit.name }}</div>
          <div class="mt-2 text-sm text-neutral-700">Alasan: {{ mutation.reason || '-' }}</div>
          <div class="mt-2"><a v-if="mutation.document_path" :href="'/storage/'+mutation.document_path" class="text-brand-primary-600 text-sm">Lihat Dokumen</a></div>
        </div>
      </CardContainer>
      <CardContainer padding="lg" shadow="sm">
        <div class="flex items-center justify-between">
          <div class="text-base font-semibold text-neutral-900">Persetujuan</div>
          <div class="text-xs text-neutral-600">SLA: 3 hari</div>
        </div>
        <div class="mt-3">
          <div class="text-sm text-neutral-700">Timeline:</div>
          <ul class="mt-1 text-xs text-neutral-600 space-y-1">
            <li>Pengajuan: {{ mutation.created_at }}</li>
            <li>Due: {{ dueDate }}</li>
          </ul>
        </div>
        <div class="mt-4 text-right">
          <PrimaryButton v-if="$page.props.auth.user.role?.name==='super_admin'" @click="approveOpen=true">Approve</PrimaryButton>
          <SecondaryButton v-if="$page.props.auth.user.role?.name==='super_admin'" class="ml-2" @click="rejectOpen=true">Tolak</SecondaryButton>
        </div>
      </CardContainer>
    </div>

    <ModalBase v-model:show="approveOpen" title="Approve Mutasi" size="md">
      <div class="space-y-2">
        <div class="text-sm text-neutral-700">Komentar</div>
        <textarea v-model="approveNote" class="w-full border rounded p-2 text-sm" rows="4" placeholder="Tuliskan komentar"></textarea>
      </div>
      <template #footer>
        <div class="flex justify-end gap-3">
          <SecondaryButton @click="approveOpen=false">Batal</SecondaryButton>
          <PrimaryButton @click="doApprove">Submit</PrimaryButton>
        </div>
      </template>
    </ModalBase>

    <ModalBase v-model:show="rejectOpen" title="Tolak Mutasi" size="md">
      <div class="space-y-2">
        <div class="text-sm text-neutral-700">Komentar</div>
        <textarea v-model="rejectNote" class="w-full border rounded p-2 text-sm" rows="4" placeholder="Alasan penolakan"></textarea>
      </div>
      <template #footer>
        <div class="flex justify-end gap-3">
          <SecondaryButton @click="rejectOpen=false">Batal</SecondaryButton>
          <PrimaryButton @click="doReject">Submit</PrimaryButton>
        </div>
      </template>
    </ModalBase>

    <Toast v-if="toast.show" :message="toast.message" :type="toast.type" position="top-center" @close="toast.show=false" />
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import CardContainer from '@/Components/UI/CardContainer.vue';
import PrimaryButton from '@/Components/UI/PrimaryButton.vue';
import SecondaryButton from '@/Components/UI/SecondaryButton.vue';
import ModalBase from '@/Components/UI/ModalBase.vue';
import Toast from '@/Components/UI/Toast.vue';
import { usePage, router } from '@inertiajs/vue3';
import { ref, reactive, computed } from 'vue';

const page = usePage();
const mutation = page.props.mutation;
const dueDate = computed(() => mutation?.due_date || '-');
const approveOpen = ref(false);
const rejectOpen = ref(false);
const approveNote = ref('');
const rejectNote = ref('');
const toast = reactive({ show:false, message:'', type:'info' });

function doApprove(){ router.post(`/admin/mutations/${mutation.id}/approve`, { note: approveNote.value }, { onSuccess(){ approveOpen.value=false; toast.message='Mutasi disetujui'; toast.type='success'; toast.show=true; setTimeout(()=>toast.show=false,3000); } }); }
function doReject(){ router.post(`/admin/mutations/${mutation.id}/reject`, { note: rejectNote.value }, { onSuccess(){ rejectOpen.value=false; toast.message='Mutasi ditolak'; toast.type='warning'; toast.show=true; setTimeout(()=>toast.show=false,3000); } }); }
</script>
