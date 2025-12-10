<template>
  <AppLayout page-title="Pengajuan Mutasi">
    <div class="space-y-6">
      <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h2 class="text-lg font-semibold text-neutral-900">Pengajuan Mutasi</h2>
          <p class="text-sm text-neutral-500">Proses mutasi anggota antar unit kerja.</p>
        </div>
      </div>

    <CardContainer padding="lg" shadow="sm">
      <div class="mb-6">
        <div class="flex items-center gap-2 text-sm">
          <span :class="stepClass(1)">1. Pilih Anggota</span>
          <span class="text-neutral-400">→</span>
          <span :class="stepClass(2)">2. Unit Tujuan</span>
          <span class="text-neutral-400">→</span>
          <span :class="stepClass(3)">3. Dokumen</span>
          <span class="text-neutral-400">→</span>
          <span :class="stepClass(4)">4. Review</span>
        </div>
      </div>

      <div v-if="step===1" class="grid grid-cols-1 md:grid-cols-2 gap-3 mb-4">
        <div class="space-y-2">
          <InputField v-model="memberQuery" placeholder="Cari anggota" />
          <SelectField v-model="form.member_id" :options="filteredMemberOptions" placeholder="Pilih Anggota" />
          <div v-if="selectedMemberSummary" class="text-xs text-neutral-600">{{ selectedMemberSummary }}</div>
        </div>
        <div class="flex items-center justify-end gap-2">
          <PrimaryButton @click="next" :disabled="!form.member_id || filteredMemberOptions.length===0">Lanjut</PrimaryButton>
        </div>
        <div v-if="filteredMemberOptions.length===0" class="md:col-span-2 text-sm text-neutral-600">Tidak ada anggota aktif yang dapat dimutasi.</div>
      </div>

      <div v-else-if="step===2" class="grid grid-cols-1 md:grid-cols-2 gap-3 mb-4">
        <SelectField v-model="form.to_unit_id" :options="unitOptions" placeholder="Unit Tujuan" />
        <div class="flex items-center justify-between">
          <SecondaryButton @click="prev">Kembali</SecondaryButton>
          <PrimaryButton @click="next" :disabled="!form.to_unit_id">Lanjut</PrimaryButton>
        </div>
      </div>
      <Toast v-if="toast.show" :message="toast.message" :type="toast.type" position="top-center" @close="toast.show=false" />

      <div v-else-if="step===3" class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-4">
        <InputField v-model="form.effective_date" type="date" placeholder="Tanggal Efektif" />
        <InputField v-model="form.reason" placeholder="Alasan" />
        <input type="file" @change="onDoc" accept=".pdf,image/*" />
        <div class="md:col-span-3 flex items-center justify-between">
          <SecondaryButton @click="prev">Kembali</SecondaryButton>
          <PrimaryButton @click="next" :disabled="!form.effective_date">Lanjut</PrimaryButton>
        </div>
      </div>

      <div v-else class="space-y-3">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
          <div>
            <div class="text-xs text-neutral-600">Anggota</div>
            <div class="text-sm">{{ memberLabel(form.member_id) }}</div>
          </div>
          <div>
            <div class="text-xs text-neutral-600">Unit Tujuan</div>
            <div class="text-sm">{{ unitLabel(form.to_unit_id) }}</div>
          </div>
          <div>
            <div class="text-xs text-neutral-600">Tanggal Efektif</div>
            <div class="text-sm">{{ form.effective_date || '-' }}</div>
          </div>
          <div>
            <div class="text-xs text-neutral-600">Alasan</div>
            <div class="text-sm">{{ form.reason || '-' }}</div>
          </div>
        </div>
        <div class="flex items-center justify-between">
          <SecondaryButton @click="prev">Kembali</SecondaryButton>
          <PrimaryButton @click="submit">Ajukan Mutasi</PrimaryButton>
        </div>
      </div>

      <div class="overflow-x-auto mt-6">
        <table class="min-w-full divide-y divide-neutral-200">
          <thead class="bg-neutral-50"><tr><th class="px-4 py-2 text-left text-xs text-neutral-500">Anggota</th><th class="px-4 py-2 text-left text-xs text-neutral-500">Asal → Tujuan</th><th class="px-4 py-2 text-left text-xs text-neutral-500">Status</th><th class="px-4 py-2"></th></tr></thead>
          <tbody class="divide-y divide-neutral-200 bg-white">
            <tr v-for="i in items.data" :key="i.id">
              <td class="px-4 py-2 text-sm">{{ i.member.full_name }}</td>
              <td class="px-4 py-2 text-sm">{{ i.from_unit.name }} → {{ i.to_unit.name }}</td>
              <td class="px-4 py-2 text-sm">{{ i.status }}</td>
              <td class="px-4 py-2 text-right text-sm"><a :href="`/admin/mutations/${i.id}`" class="text-brand-primary-600">Detail</a></td>
            </tr>
          </tbody>
        </table>
      </div>
    </CardContainer>
  </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import CardContainer from '@/Components/UI/CardContainer.vue';
import PrimaryButton from '@/Components/UI/PrimaryButton.vue';
import SecondaryButton from '@/Components/UI/SecondaryButton.vue';
import InputField from '@/Components/UI/InputField.vue';
import SelectField from '@/Components/UI/SelectField.vue';
import Toast from '@/Components/UI/Toast.vue';
import { usePage, router } from '@inertiajs/vue3';
import { reactive, ref, onMounted, computed } from 'vue';

const page = usePage();
const items = page.props.items;
const units = page.props.units || [];
const members = page.props.members || [];
const selectedMember = page.props.selected_member || '';
const unitOptions = units.map(u => ({ label: `${u.code} - ${u.name}`, value: u.id }));
const memberOptions = members.map(m => ({ label: `${m.nra || '-'} – ${m.full_name}`, value: m.id }));
const memberQuery = ref('');
const filteredMemberOptions = computed(() => {
  const q = (memberQuery.value || '').toLowerCase();
  if (!q) return memberOptions;
  return memberOptions.filter(o => o.label.toLowerCase().includes(q));
});
const selectedMemberSummary = computed(() => {
  if (!form.member_id) return '';
  const m = members.find(x => x.id === form.member_id);
  if (!m) return '';
  const unit = units.find(u => u.id === m.organization_unit_id);
  return `${m.full_name} • ${unit ? unit.code + ' - ' + unit.name : 'Unit -'}`;
});

const form = reactive({ member_id:'', to_unit_id:'', effective_date:'', reason:'' });
const step = ref(1);
const toast = reactive({ show:false, message:'', type:'info' });
onMounted(() => {
  if (selectedMember) {
    form.member_id = selectedMember;
    step.value = 2;
  }
});
let doc = null;
function onDoc(e){ doc = e.target.files[0]; }
function next(){ if (step.value<4) step.value++; }
function prev(){ if (step.value>1) step.value--; }
function stepClass(s){
  const base = 'px-3 py-1.5 rounded border';
  return [base, step.value===s ? 'bg-brand-primary-50 text-brand-primary-800 border-brand-primary-600 shadow-sm' : 'bg-neutral-100 text-neutral-700 border-neutral-300'].join(' ');
}
function memberLabel(id){ const m = memberOptions.find(x=>x.value===id); return m?m.label:'-'; }
function unitLabel(id){ const u = unitOptions.find(x=>x.value===id); return u?u.label:'-'; }
function submit(){
  const data = new FormData();
  Object.entries(form).forEach(([k,v])=>data.append(k, v ?? ''));
  if (doc) data.append('document', doc);
  router.post('/admin/mutations', data, {
    onSuccess(){ toast.message='Pengajuan mutasi dikirim'; toast.type='success'; toast.show=true; setTimeout(()=>toast.show=false,3000); }
  });
}
</script>
