<template>
  <AppLayout page-title="Laporan: Pertumbuhan">
    <div class="flex items-center justify-between mb-4">
      <nav class="text-sm text-neutral-600">
        <a href="/dashboard" class="hover:underline">Dashboard</a> / <span>Laporan</span> / <span>Pertumbuhan</span>
      </nav>
      <PrimaryButton @click="openExport">Export</PrimaryButton>
    </div>

    <AlertBanner type="info" :message="`Data terupdate s/d ${lastUpdated}`" />

    <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
      <div class="lg:col-span-3 space-y-4">
        <CardContainer padding="lg" shadow="sm">
          <div class="flex items-start justify-between">
            <div>
              <h3 class="text-lg font-semibold text-neutral-900">Pertumbuhan Anggota</h3>
              <p class="text-sm text-neutral-600">Ringkasan KPI</p>
            </div>
            <div class="flex items-center gap-3">
              <Badge variant="brand">Total: {{ kpi.total }}</Badge>
              <Badge :variant="kpi.yoy>=0 ? 'success' : 'danger'">YoY: {{ kpi.yoy }}%</Badge>
            </div>
          </div>
          <div class="mt-4 overflow-x-auto">
            <table class="min-w-full divide-y divide-neutral-200">
              <thead class="bg-neutral-50 sticky top-0 z-10">
                <tr>
                  <th class="px-4 py-2 text-left text-xs font-medium text-neutral-500">Bulan</th>
                  <th class="px-4 py-2 text-left text-xs font-medium text-neutral-500">Anggota Baru</th>
                  <th class="px-4 py-2 text-left text-xs font-medium text-neutral-500">Total</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-neutral-200 bg-white">
                <tr v-for="(r,i) in rows" :key="i">
                  <td class="px-4 py-2 text-sm">{{ r.month }}</td>
                  <td class="px-4 py-2 text-sm">{{ r.new }}</td>
                  <td class="px-4 py-2 text-sm">{{ r.total }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </CardContainer>

        <CardContainer padding="lg" shadow="sm">
          <h3 class="text-base font-semibold text-neutral-900">Insight</h3>
          <div class="mt-3 grid grid-cols-1 md:grid-cols-3 gap-3">
            <div class="p-3 bg-neutral-50 rounded">
              <div class="text-xs text-neutral-600">Rata-rata registrasi</div>
              <div class="text-lg font-semibold">{{ insight.avg_new }}/bulan</div>
            </div>
            <div class="p-3 bg-neutral-50 rounded">
              <div class="text-xs text-neutral-600">Peak bulan</div>
              <div class="text-lg font-semibold">{{ insight.peak_month }}</div>
            </div>
            <div class="p-3 bg-neutral-50 rounded">
              <div class="text-xs text-neutral-600">Pertumbuhan YoY</div>
              <div class="text-lg font-semibold">{{ kpi.yoy }}%</div>
            </div>
          </div>
        </CardContainer>
      </div>

      <div class="lg:col-span-1">
        <CardContainer padding="lg" shadow="sm" class="sticky top-20">
          <h3 class="text-base font-semibold text-neutral-900">Filter</h3>
          <div class="mt-3 space-y-3">
            <SelectField v-model="filters.period" :options="periodOptions" placeholder="Periode" />
            <SelectField v-model="filters.unit" :options="unitOptions" placeholder="Unit" />
            <SelectField v-model="filters.status" :options="statusOptions" placeholder="Status" />
            <PrimaryButton class="w-full" @click="apply">Terapkan</PrimaryButton>
          </div>
        </CardContainer>
      </div>
    </div>

    <ModalBase v-model:show="exportOpen" title="Konfirmasi Export" size="md">
      <div class="space-y-2 text-sm text-neutral-700">
        <div>Format: {{ exportForm.format }}</div>
        <div>Periode: {{ filters.period }}</div>
        <div>Unit: {{ filters.unit || 'Semua' }}</div>
        <div>Status: {{ filters.status || 'Semua' }}</div>
      </div>
      <template #footer>
        <div class="flex justify-end gap-3">
          <SecondaryButton @click="exportOpen=false">Batal</SecondaryButton>
          <PrimaryButton @click="doExport">Export</PrimaryButton>
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
import SelectField from '@/Components/UI/SelectField.vue';
import ModalBase from '@/Components/UI/ModalBase.vue';
import Toast from '@/Components/UI/Toast.vue';
import AlertBanner from '@/Components/UI/AlertBanner.vue';
import Badge from '@/Components/UI/Badge.vue';
import { reactive, ref } from 'vue';

const filters = reactive({ period: 'last_6_months', unit:'', status:'' });
const unitOptions = [];
const statusOptions = [
  { label: 'Semua', value: '' },
  { label: 'Aktif', value: 'aktif' },
  { label: 'Cuti', value: 'cuti' },
  { label: 'Suspended', value: 'suspended' },
];
const periodOptions = [
  { label: '6 Bulan Terakhir', value: 'last_6_months' },
  { label: '12 Bulan Terakhir', value: 'last_12_months' },
];
const rows = ref([
  { month:'Jan', new:12, total:100 },
  { month:'Feb', new:8, total:108 },
]);
const kpi = reactive({ total: 1234, yoy: 7.4 });
const insight = reactive({ avg_new: 10, peak_month: 'Apr' });
const lastUpdated = new Date().toLocaleDateString('id-ID');

function apply(){ }

const exportOpen = ref(false);
const exportForm = reactive({ format: 'Excel' });
const toast = reactive({ show:false, message:'', type:'info' });
function openExport(){ exportOpen.value = true; }
function doExport(){ exportOpen.value=false; toast.message='Sedang menyiapkan laporan…'; toast.type='info'; toast.show=true; setTimeout(()=>{ toast.message='Laporan siap didownload (lihat Notifikasi)'; toast.type='success'; toast.show=true; setTimeout(()=>toast.show=false,3000); }, 1200); }
</script>
