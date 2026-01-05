<template>
  <AppLayout pageTitle="Reports: Keanggotaan (CSV)">
    <div class="space-y-6">
      <!-- Header -->
      <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
          <h2 class="text-xl font-bold text-neutral-900">Export Keanggotaan</h2>
          <p class="text-sm text-neutral-500">Download data anggota dalam format CSV sesuai filter.</p>
        </div>
        <div>
          <a href="/docs/help/reports-csv" target="_blank" class="text-sm text-brand-primary-600 hover:underline">
            Baca panduan export CSV &rarr;
          </a>
        </div>
      </div>

      <!-- Main Content -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Filters -->
        <CardContainer class="lg:col-span-2">
          <template #header>
            <h3 class="text-lg font-semibold text-neutral-900">Filter Data</h3>
          </template>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <!-- Search -->
            <div class="md:col-span-2">
              <InputField 
                label="Pencarian" 
                v-model="form.q" 
                placeholder="Nama / KTA / NRA / Employee ID" 
              />
            </div>

            <!-- Unit Selector (Role Aware) -->
            <div v-if="canSelectUnit">
              <SelectField
                label="Unit Pembangkit"
                v-model="form.unit_id"
                :options="unitOptions"
              />
            </div>
            <div v-else>
               <label class="block text-sm font-medium text-neutral-700 mb-1">Unit Anda</label>
               <div class="w-full rounded-lg border border-neutral-200 bg-neutral-100 px-3 py-2 text-neutral-600 cursor-not-allowed">
                  {{ userUnitName || 'Unit tidak ditemukan' }}
               </div>
               <p class="text-xs text-neutral-500 mt-1">Export otomatis dibatasi ke unit Anda.</p>
            </div>

            <!-- Status -->
            <div>
              <SelectField 
                label="Status Anggota" 
                v-model="form.status" 
                :options="statusOptions"
              />
            </div>

            <!-- Join Date Range -->
            <div>
               <InputField label="Tanggal Gabung (Dari)" type="date" v-model="form.date_start" />
            </div>
            <div>
               <InputField label="Tanggal Gabung (Sampai)" type="date" v-model="form.date_end" />
            </div>

            <!-- Union Position -->
            <div>
               <SelectField 
                 label="Jabatan Serikat"
                 v-model="form.union_position_id"
                 :options="positionOptions"
               />
            </div>

            <!-- Options -->
            <div class="md:col-span-2 flex items-center gap-2 mt-2">
               <Checkbox v-model:checked="form.include_documents" id="include_documents" />
               <label for="include_documents" class="text-sm text-neutral-700">Sertakan informasi kelengkapan dokumen</label>
            </div>
          </div>
        </CardContainer>

        <!-- Actions -->
        <CardContainer>
           <template #header>
             <h3 class="text-lg font-semibold text-neutral-900">Aksi</h3>
           </template>
           
           <div class="space-y-4">
              <ExportStatusBanner type="members" />

              <div class="pt-2">
                <PrimaryButton @click="downloadCsv" class="w-full justify-center" :disabled="isInvalidDate || exporting">
                   <span v-if="exporting">Memproses...</span>
                   <span v-else>Download CSV</span>
                </PrimaryButton>
                <p v-if="isInvalidDate" class="text-xs text-red-500 mt-1 text-center">
                   Tanggal mulai tidak boleh lebih besar dari tanggal akhir.
                </p>
              </div>

              <SecondaryButton @click="resetFilters" class="w-full justify-center">
                 Reset Filter
              </SecondaryButton>
           </div>
        </CardContainer>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import CardContainer from '@/Components/UI/CardContainer.vue';
import InputField from '@/Components/UI/InputField.vue';
import SelectField from '@/Components/UI/SelectField.vue';
import Checkbox from '@/Components/UI/Checkbox.vue';
import PrimaryButton from '@/Components/UI/PrimaryButton.vue';
import SecondaryButton from '@/Components/UI/SecondaryButton.vue';
import ExportStatusBanner from '@/Components/Reports/ExportStatusBanner.vue';

const props = defineProps({
  units: { type: Array, default: () => [] },
  union_positions: { type: Array, default: () => [] },
});

const page = usePage();
const user = computed(() => page.props.auth.user);
const roleName = computed(() => user.value?.role?.name || '');
const canSelectUnit = computed(() => ['super_admin', 'admin_pusat'].includes(roleName.value));
const userUnitName = computed(() => user.value?.organization_unit?.name);

// Options
const unitOptions = computed(() => [
  { value: '', label: 'Semua Unit' },
  ...props.units.map(u => ({ value: u.id, label: u.name }))
]);

const positionOptions = computed(() => [
  { value: '', label: 'Semua Jabatan' },
  ...props.union_positions.map(p => ({ value: p.id, label: p.name }))
]);

const statusOptions = [
  { value: '', label: 'Semua Status' },
  { value: 'aktif', label: 'Aktif' },
  { value: 'cuti', label: 'Cuti' },
  { value: 'suspended', label: 'Suspended' },
  { value: 'resign', label: 'Resign' },
  { value: 'pensiun', label: 'Pensiun' },
  { value: 'meninggal', label: 'Meninggal' },
];

// Initial State
const form = ref({
   q: '',
   unit_id: '',
   status: '',
   date_start: '',
   date_end: '',
   union_position_id: '',
   include_documents: false,
});

function resetFilters() {
   form.value = {
      q: '',
      unit_id: '',
      status: '',
      date_start: '',
      date_end: '',
      union_position_id: '',
      include_documents: false,
   };
}

const isInvalidDate = computed(() => {
   if (form.value.date_start && form.value.date_end) {
      return form.value.date_start > form.value.date_end;
   }
   return false;
});

const exporting = ref(false);

function downloadCsv() {
   if (isInvalidDate.value) return;

   const params = new URLSearchParams();
   params.append('type', 'members');
   if (form.value.q) params.append('q', form.value.q);
   if (form.value.unit_id) params.append('unit_id', form.value.unit_id);
   if (form.value.status) params.append('status', form.value.status);
   if (form.value.date_start) params.append('date_start', form.value.date_start);
   if (form.value.date_end) params.append('date_end', form.value.date_end);
   if (form.value.union_position_id) params.append('union_position_id', form.value.union_position_id);
   if (form.value.include_documents) params.append('include_documents', '1');

   const url = `/reports/export?${params.toString()}`;
   window.location.href = url;
   
   exporting.value = true;
   setTimeout(() => { exporting.value = false; }, 3000);
}
</script>
