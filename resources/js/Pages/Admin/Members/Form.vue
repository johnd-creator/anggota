<template>
  <AppLayout :page-title="member ? 'Edit Member' : 'Create Member'">
    <CardContainer padding="lg" shadow="sm">
      <div class="flex items-center gap-2 mb-6">
        <button type="button" class="inline-flex items-center px-3 py-1 rounded-full text-xs cursor-pointer" :class="step===1?'bg-brand-primary-50 text-brand-primary-800':'bg-neutral-100 text-neutral-700'" @click="setStep(1)">1. Data Personal</button>
        <button type="button" class="inline-flex items-center px-3 py-1 rounded-full text-xs cursor-pointer" :class="step===2?'bg-brand-primary-50 text-brand-primary-800':'bg-neutral-100 text-neutral-700'" @click="setStep(2)">2. Data Organisasi</button>
        <button type="button" class="inline-flex items-center px-3 py-1 rounded-full text-xs cursor-pointer" :class="step===3?'bg-brand-primary-50 text-brand-primary-800':'bg-neutral-100 text-neutral-700'" @click="setStep(3)">3. Dokumen</button>
      </div>
      <form @submit.prevent="submit" class="space-y-6">
        <div v-show="step===1" class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <div>
            <h3 class="text-sm font-semibold text-neutral-700 mb-3">Data Personal</h3>
            <div class="space-y-3">
              <InputField label="Full Name" v-model="form.full_name" :error="err('full_name')" :disabled="submitting" required />
              <InputField label="NIP" v-model="form.nip" :error="err('nip')" :disabled="submitting" required />
              <InputField label="Email" type="email" v-model="form.email" :error="err('email')" :disabled="submitting" required />
              <InputField label="Phone (+62...)" type="tel" v-model="form.phone" :error="err('phone')" :disabled="submitting" />
              <InputField label="Birth Place" v-model="form.birth_place" :error="err('birth_place')" :disabled="submitting" />
              <InputField label="Birth Date" type="date" v-model="form.birth_date" :error="err('birth_date')" :disabled="submitting" />
              <label class="block text-sm font-semibold text-neutral-700 mb-1">Address</label>
              <textarea v-model="form.address" :disabled="submitting" class="w-full rounded-lg border border-neutral-300 px-3 py-2"></textarea>
              <p v-if="err('address')" class="mt-1 text-sm text-status-error">{{ err('address') }}</p>
              <InputField label="Emergency Contact" v-model="form.emergency_contact" :error="err('emergency_contact')" :disabled="submitting" />
            </div>
          </div>
        </div>
        <div v-show="step===2" class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <div>
            <h3 class="text-sm font-semibold text-neutral-700 mb-3">Data Organisasi</h3>
            <div class="space-y-3">
              <InputField label="Job Title" v-model="form.job_title" :error="err('job_title')" :disabled="submitting" />
              <InputField label="Nomor KTA (Smart ID)" v-model="form.kta_number" :error="err('kta_number')" :disabled="submitting" />
              <InputField label="NIP" v-model="form.nip" :error="err('nip')" :disabled="submitting" />
              <SelectField label="Jabatan Serikat" v-model="form.union_position_id" :options="positionOptions" :error="err('union_position_id')" :disabled="submitting" />
              <SelectField label="Employment Type" v-model="form.employment_type" :options="[{label:'Organik',value:'organik'},{label:'TKWT',value:'tkwt'}]" :error="err('employment_type')" :disabled="submitting" />
              <SelectField label="Status" v-model="form.status" :options="statusOptions" :error="err('status')" :disabled="submitting" />
              <InputField label="Join Date" type="date" v-model="form.join_date" :error="err('join_date')" :disabled="submitting" required />
              <InputField label="Tanggal Gabung Perusahaan" type="date" v-model="form.company_join_date" :error="err('company_join_date')" :disabled="submitting" />
              <SelectField label="Organization Unit" v-model="form.organization_unit_id" :options="unitsOptions" :error="err('organization_unit_id')" :disabled="submitting" required />
              <label class="block text-sm font-semibold text-neutral-700 mb-1">Notes</label>
              <textarea v-model="form.notes" :disabled="submitting" class="w-full rounded-lg border border-neutral-300 px-3 py-2"></textarea>
              <p v-if="err('notes')" class="mt-1 text-sm text-status-error">{{ err('notes') }}</p>
            </div>
          </div>
        </div>
        <div v-show="step===3" class="space-y-4">
          <div>
            <label class="block text-sm font-semibold text-neutral-700 mb-2">Photo</label>
            <input type="file" @change="onPhoto" accept="image/*" :disabled="submitting" />
            <div v-if="photo" class="mt-2 text-xs text-neutral-600">{{ photo.name }}</div>
          </div>
          <div>
            <label class="block text-sm font-semibold text-neutral-700 mb-2">Documents (pdf/jpg, max 2MB)</label>
            <input type="file" multiple @change="onDocs" accept=".pdf,image/*" :disabled="submitting" />
            <div class="mt-3 grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3">
              <div v-for="(d,i) in documents" :key="i" class="border rounded-lg p-3">
                <div class="flex items-center gap-2">
                  <img v-if="isImage(d)" :src="previewUrl(d)" class="h-12 w-12 rounded object-cover" />
                  <svg v-else class="w-6 h-6 text-neutral-500" fill="currentColor" viewBox="0 0 20 20"><path d="M4 2h8l4 4v12a2 2 0 01-2 2H4a2 2 0 01-2-2V4a2 2 0 012-2z"/></svg>
                  <div class="text-sm text-neutral-900 truncate">{{ d.name }}</div>
                </div>
                <div class="mt-2 text-xs text-neutral-500">{{ (d.size/1024).toFixed(1) }} KB</div>
                <div class="mt-2">
                  <span v-if="progress>0" class="inline-block w-full bg-neutral-200 h-2 rounded overflow-hidden"><span class="block h-2 bg-brand-primary-600" :style="{width: progress+'%'}"></span></span>
                </div>
                <div class="mt-2 text-right">
                  <button type="button" class="text-status-danger-dark text-sm" @click="removeDoc(i)" :disabled="submitting">Hapus</button>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div class="flex justify-between gap-2">
          <button type="button" class="px-4 py-2 border rounded-lg" @click="prevStep" :disabled="step===1 || submitting">Back</button>
          <div class="flex gap-2">
            <PrimaryButton type="button" v-if="step<3" @click="nextStep" :disabled="submitting">
              Next
            </PrimaryButton>
            <PrimaryButton type="submit" :loading="submitting">
              {{ member ? 'Update' : 'Save' }}
            </PrimaryButton>
          </div>
        </div>
      </form>
    </CardContainer>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import CardContainer from '@/Components/UI/CardContainer.vue';
import InputField from '@/Components/UI/InputField.vue';
import SelectField from '@/Components/UI/SelectField.vue';
import PrimaryButton from '@/Components/UI/PrimaryButton.vue';
import { router, usePage } from '@inertiajs/vue3';
import { reactive, ref, computed } from 'vue';

const page = usePage();
const member = page.props.member || null;
const units = page.props.units || [];
const unitsOptions = units.map(u => ({ label: `${u.code} - ${u.name}`, value: u.id }));
const positions = page.props.positions || [];
const positionOptions = positions.map(p => ({ label: p.name, value: p.id }));
const statusOptions = [
  {label:'Aktif', value:'aktif'},
  {label:'Cuti', value:'cuti'},
  {label:'Suspended', value:'suspended'},
  {label:'Resign', value:'resign'},
  {label:'Pensiun', value:'pensiun'}
];

function toDateInput(value) {
  if (!value) return '';
  return typeof value === 'string' ? value.substring(0, 10) : value;
}

const form = reactive({
  full_name: member?.full_name || '',
  email: member?.email || '',
  phone: member?.phone || '',
  birth_place: member?.birth_place || '',
  birth_date: toDateInput(member?.birth_date),
  address: member?.address || '',
  emergency_contact: member?.emergency_contact || '',
  job_title: member?.job_title || '',
  kta_number: member?.kta_number || '',
  nip: member?.nip || '',
  union_position_id: member?.union_position_id || '',
  employment_type: member?.employment_type || 'organik',
  status: member?.status || 'aktif',
  join_date: toDateInput(member?.join_date),
  company_join_date: toDateInput(member?.company_join_date),
  organization_unit_id: member?.organization_unit_id || '',
  notes: member?.notes || ''
});

let photo = null;
let documents = [];
const step = ref(1);
const errors = reactive({ nip: '' });
const progress = ref(0);
const submitting = ref(false);
const serverErrors = computed(() => page.props.errors || {});
function err(field){ return errors[field] || serverErrors.value[field] || ''; }

function onPhoto(e){ photo = e.target.files[0]; }
function onDocs(e){ documents = Array.from(e.target.files); }
function removeDoc(i){ documents.splice(i,1); }
function nextStep(){ if (step.value<3) step.value++; }
function prevStep(){ if (step.value>1) step.value--; }
function setStep(n){ step.value = n; }
function isImage(file){ return /^image\/.+/.test(file.type); }
function previewUrl(file){ return URL.createObjectURL(file); }

function submit(){
  const data = new FormData();
  Object.entries(form).forEach(([k,v]) => data.append(k, v ?? ''));
  if (photo) data.append('photo', photo);
  documents.forEach(d => data.append('documents[]', d));

  if (!/^[a-zA-Z0-9]+$/.test(form.nip)) { errors.nip = 'NIP harus alfanumerik'; step.value = 1; return; } else { errors.nip = ''; }

  const options = {
    forceFormData: true,
    onStart(){ submitting.value = true; },
    onProgress: (e) => { if (e && e.percentage) progress.value = e.percentage; },
    onFinish(){ submitting.value = false; },
    onError(errs){
      const personalFields = ['full_name','email','phone','birth_place','birth_date','address','emergency_contact','nip'];
      const orgFields = ['job_title','kta_number','union_position_id','employment_type','status','join_date','company_join_date','organization_unit_id','notes'];
      const keys = Object.keys(errs || {});
      if (keys.some(k => personalFields.includes(k))) step.value = 1; else if (keys.some(k => orgFields.includes(k))) step.value = 2;
    }
  };
  if (member) {
    data.append('_method', 'PUT');
    router.post(`/admin/members/${member.id}`, data, options);
  } else {
    router.post('/admin/members', data, options);
  }
}
</script>
