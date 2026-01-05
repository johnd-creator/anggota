<template>
  <AppLayout pageTitle="Reports: Keuangan (CSV)">
    <div class="space-y-6">
      <!-- Header -->
      <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
          <h2 class="text-xl font-bold text-neutral-900">Export Keuangan</h2>
          <p class="text-sm text-neutral-500">Download data transaksi keuangan dalam format CSV.</p>
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
                placeholder="Deskripsi Transaksi" 
              />
            </div>

            <!-- Unit Selector -->
            <div v-if="canSelectUnit">
              <SelectField
                label="Unit"
                v-model="form.unit_id"
                :options="unitOptions"
              />
            </div>
             <div v-else>
               <label class="block text-sm font-medium text-neutral-700 mb-1">Unit Anda</label>
               <div class="w-full rounded-lg border border-neutral-200 bg-neutral-100 px-3 py-2 text-neutral-600 cursor-not-allowed">
                  {{ userUnitName || 'Unit tidak ditemukan' }}
               </div>
            </div>

            <!-- Ledger Type -->
             <div>
              <SelectField 
                label="Jenis Transaksi" 
                v-model="form.ledger_type" 
                :options="ledgerTypeOptions"
              />
            </div>

            <!-- Category -->
             <div>
              <SelectField 
                label="Kategori" 
                v-model="form.category_id" 
                :options="categoryOptions"
              />
            </div>

            <!-- Status -->
            <div>
              <SelectField 
                label="Status Approval" 
                v-model="form.status" 
                :options="statusOptions"
              />
            </div>

             <!-- Year -->
            <div>
               <InputField label="Tahun Anggaran" type="number" v-model="form.year" placeholder="YYYY" />
            </div>

            <!-- Date Range -->
            <div>
               <InputField label="Tanggal (Dari)" type="date" v-model="form.date_start" />
            </div>
            <div>
               <InputField label="Tanggal (Sampai)" type="date" v-model="form.date_end" />
            </div>
            
            <!-- Options -->
            <div class="md:col-span-2 flex flex-col gap-2 mt-2">
                <div class="flex items-center gap-2">
                   <Checkbox v-model:checked="form.only_approved" id="only_approved" />
                   <label for="only_approved" class="text-sm text-neutral-700">Hanya yang sudah disetujui (Approved)</label>
                </div>
                 <div class="flex items-center gap-2">
                   <Checkbox v-model:checked="form.include_attachment_url" id="include_attachment_url" />
                   <label for="include_attachment_url" class="text-sm text-neutral-700">Sertakan Link Lampiran</label>
                </div>
            </div>
          </div>
        </CardContainer>

        <!-- Actions -->
        <CardContainer>
           <template #header>
             <h3 class="text-lg font-semibold text-neutral-900">Aksi</h3>
           </template>
           
           <div class="space-y-4">
              <ExportStatusBanner type="finance" />

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
  finance_categories: { type: Array, default: () => [] },
});

const page = usePage();
const user = computed(() => page.props.auth.user);
const roleName = computed(() => user.value?.role?.name || '');
const canSelectUnit = computed(() => ['super_admin', 'admin_pusat'].includes(roleName.value));
const userUnitName = computed(() => user.value?.organization_unit?.name);

const unitOptions = computed(() => [
  { value: '', label: 'Semua Unit' },
  ...props.units.map(u => ({ value: u.id, label: u.name }))
]);

const categoryOptions = computed(() => [
  { value: '', label: 'Semua Kategori' },
  ...props.finance_categories.map(c => ({ value: c.id, label: c.name }))
]);

const statusOptions = [
  { value: '', label: 'Semua Status' },
  { value: 'pending', label: 'Pending' },
  { value: 'approved', label: 'Approved' },
  { value: 'rejected', label: 'Rejected' },
];

const ledgerTypeOptions = [
    { value: '', label: 'Semua Tipe' },
    { value: 'debit', label: 'Pemasukan (Debit)' },
    { value: 'credit', label: 'Pengeluaran (Credit)' },
];

const form = ref({
   q: '',
   unit_id: '',
   status: '',
   ledger_type: '',
   category_id: '',
   year: '',
   date_start: '',
   date_end: '',
   only_approved: false,
   include_attachment_url: false,
});

function resetFilters() {
   form.value = {
      q: '',
      unit_id: '',
      status: '',
      ledger_type: '',
      category_id: '',
      year: '',
      date_start: '',
      date_end: '',
      only_approved: false,
      include_attachment_url: false,
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
   params.append('type', 'finance');
   if (form.value.q) params.append('q', form.value.q);
   if (form.value.unit_id) params.append('unit_id', form.value.unit_id);
   if (form.value.status) params.append('status', form.value.status);
   if (form.value.ledger_type) params.append('ledger_type', form.value.ledger_type);
   if (form.value.category_id) params.append('category_id', form.value.category_id);
   if (form.value.year) params.append('year', form.value.year);
   if (form.value.date_start) params.append('date_start', form.value.date_start);
   if (form.value.date_end) params.append('date_end', form.value.date_end);
   if (form.value.only_approved) params.append('only_approved', '1');
   if (form.value.include_attachment_url) params.append('include_attachment_url', '1');

   const url = `/reports/export?${params.toString()}`;
   window.location.href = url;
   
   exporting.value = true;
   setTimeout(() => { exporting.value = false; }, 3000);
}
</script>
