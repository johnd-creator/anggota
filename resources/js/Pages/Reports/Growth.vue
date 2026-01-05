<template>
  <AppLayout page-title="Laporan: Pertumbuhan">
    <div class="flex items-center justify-between mb-4">
      <nav class="text-sm text-neutral-600">
        <a href="/dashboard" class="hover:underline">Dashboard</a> / <span>Laporan</span> / <span>Pertumbuhan</span>
      </nav>
      <PrimaryButton @click="openExport">Export CSV</PrimaryButton>
    </div>

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
            </div>
          </div>
          <div class="mt-4 overflow-x-auto">
            <table class="min-w-full divide-y divide-neutral-200">
              <thead class="bg-neutral-50 sticky top-0 z-10">
                <tr>
                  <th class="px-4 py-2 text-left text-xs font-medium text-neutral-500">Bulan</th>
                  <th class="px-4 py-2 text-left text-xs font-medium text-neutral-500">Anggota Baru</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-neutral-200 bg-white">
                <tr v-for="(r,i) in rows" :key="i">
                  <td class="px-4 py-2 text-sm">{{ r.month }}</td>
                  <td class="px-4 py-2 text-sm">{{ r.new }}</td>
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
              <div class="text-xs text-neutral-600">Terakhir diperbarui</div>
              <div class="text-lg font-semibold">{{ lastUpdated }}</div>
            </div>
          </div>
        </CardContainer>
      </div>

      <div class="lg:col-span-1">
        <CardContainer padding="lg" shadow="sm" class="sticky top-20">
          <h3 class="text-base font-semibold text-neutral-900">Filter</h3>
          <div class="mt-3 space-y-3">
            <InputField v-model="filters.date_start" type="date" label="Dari" />
            <InputField v-model="filters.date_end" type="date" label="Sampai" />
            <SelectField v-if="canSelectUnit" v-model="filters.unit_id" :options="unitOptions" label="Unit" />
            <div v-else>
              <label class="block text-sm font-medium text-neutral-700 mb-1">Unit</label>
              <div class="w-full rounded-lg border border-neutral-200 bg-neutral-100 px-3 py-2 text-neutral-600 cursor-not-allowed">
                {{ userUnitName || 'Unit tidak ditemukan' }}
              </div>
              <p class="text-xs text-neutral-500 mt-1">Export dibatasi ke unit Anda.</p>
            </div>
            <PrimaryButton class="w-full" @click="apply">Terapkan</PrimaryButton>
          </div>
        </CardContainer>
      </div>
    </div>

    <ModalBase v-model:show="exportOpen" title="Konfirmasi Export" size="md">
      <div class="space-y-2 text-sm text-neutral-700">
        <div>Format: CSV</div>
        <div>Rentang: {{ filters.date_start || '-' }} → {{ filters.date_end || '-' }}</div>
        <div>Unit: {{ filterUnitLabel }}</div>
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
import InputField from '@/Components/UI/InputField.vue';
import ModalBase from '@/Components/UI/ModalBase.vue';
import Toast from '@/Components/UI/Toast.vue';

import Badge from '@/Components/UI/Badge.vue';
import { computed, reactive, ref } from 'vue';
import { usePage, router } from '@inertiajs/vue3';

const props = defineProps({
  series: { type: Array, default: () => [] },
  kpi: { type: Object, default: () => ({ total: 0 }) },
  filters: { type: Object, default: () => ({}) },
  last_updated: { type: String, default: '' },
  units: { type: Array, default: () => [] },
});

const page = usePage();
const user = computed(() => page.props.auth?.user || {});
const roleName = computed(() => user.value?.role?.name || '');
const canSelectUnit = computed(() => ['super_admin', 'admin_pusat'].includes(roleName.value));
const userUnitName = computed(() => user.value?.organization_unit?.name || '');

const filters = reactive({
  date_start: props.filters?.date_start || '',
  date_end: props.filters?.date_end || '',
  unit_id: props.filters?.unit_id || '',
});

const unitOptions = computed(() => [
  { label: 'Semua', value: '' },
  ...props.units.map(u => ({ label: u.name, value: u.id })),
]);

const rows = computed(() => props.series.map((r) => ({
  month: r.label,
  new: r.value,
})));

const kpi = computed(() => props.kpi || { total: 0 });
const lastUpdated = computed(() => props.last_updated || '-');

const insight = computed(() => {
  const values = props.series.map((r) => Number(r.value || 0));
  if (!values.length) {
    return { avg_new: 0, peak_month: '-' };
  }
  const avg = Math.round(values.reduce((a, b) => a + b, 0) / values.length);
  const peakIndex = values.indexOf(Math.max(...values));
  return {
    avg_new: avg,
    peak_month: props.series[peakIndex]?.label || '-',
  };
});

const exportOpen = ref(false);
const toast = reactive({ show:false, message:'', type:'info' });
function openExport(){ exportOpen.value = true; }

const filterUnitLabel = computed(() => {
  if (canSelectUnit.value) {
    const selected = unitOptions.value.find((u) => String(u.value) === String(filters.unit_id));
    return selected?.label || 'Semua';
  }
  return userUnitName.value || 'Unit Anda';
});

function apply(){
  const params = {};
  if (filters.date_start) params.date_start = filters.date_start;
  if (filters.date_end) params.date_end = filters.date_end;
  if (filters.unit_id) params.unit_id = filters.unit_id;
  router.get('/reports/growth', params, { preserveState: true, replace: true });
}

function submitExportForm() {
  const form = document.createElement('form');
  form.method = 'POST';
  form.action = '/reports/growth/export';
  form.style.display = 'none';

  const token = page.props.csrf_token || '';
  const inputs = {
    _token: token,
    unit_id: filters.unit_id || '',
    date_start: filters.date_start || '',
    date_end: filters.date_end || '',
  };

  Object.entries(inputs).forEach(([name, value]) => {
    if (value === '') return;
    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = name;
    input.value = value;
    form.appendChild(input);
  });

  document.body.appendChild(form);
  form.submit();
  document.body.removeChild(form);
}

function doExport(){
  exportOpen.value = false;
  submitExportForm();
  toast.message = 'Sedang menyiapkan laporan…';
  toast.type = 'info';
  toast.show = true;
  setTimeout(() => { toast.show = false; }, 2500);
}
</script>
