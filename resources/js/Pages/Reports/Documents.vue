<template>
  <AppLayout page-title="Laporan: Monitoring Dokumen">
    <div class="flex items-center justify-between mb-4">
      <nav class="text-sm text-neutral-600">
        <a href="/dashboard" class="hover:underline">Dashboard</a> / <span>Laporan</span> / <span>Dokumen</span>
      </nav>
      <PrimaryButton @click="openExport">Export</PrimaryButton>
    </div>

    <AlertBanner type="info" :message="`Data terupdate s/d ${lastUpdated}`" />

    <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
      <div class="lg:col-span-3">
        <CardContainer padding="lg" shadow="sm">
          <div class="flex items-start justify-between">
            <div>
              <h3 class="text-lg font-semibold text-neutral-900">Monitoring Dokumen</h3>
              <p class="text-sm text-neutral-600">Status kelengkapan dokumen anggota</p>
            </div>
            <div class="flex items-center gap-3">
              <Badge variant="brand">Lengkap: {{ kpi.complete }}</Badge>
              <Badge variant="warning">Kurang: {{ kpi.missing }}</Badge>
            </div>
          </div>
          <div class="mt-4 overflow-x-auto">
            <table class="min-w-full divide-y divide-neutral-200">
              <thead class="bg-neutral-50 sticky top-0 z-10">
                <tr>
                  <th class="px-4 py-2 text-left text-xs font-medium text-neutral-500">Anggota</th>
                  <th class="px-4 py-2 text-left text-xs font-medium text-neutral-500">Dokumen</th>
                  <th class="px-4 py-2 text-left text-xs font-medium text-neutral-500">Status</th>
                  <th class="px-4 py-2"></th>
                </tr>
              </thead>
              <tbody class="divide-y divide-neutral-200 bg-white">
                <tr v-for="(r,i) in rows" :key="i">
                  <td class="px-4 py-2 text-sm">{{ r.member }}</td>
                  <td class="px-4 py-2 text-sm">{{ r.docs }}</td>
                  <td class="px-4 py-2 text-sm">
                    <Badge :variant="r.status==='lengkap' ? 'success' : 'warning'">{{ r.status }}</Badge>
                  </td>
                  <td class="px-4 py-2 text-right text-sm">
                    <a href="#" class="text-brand-primary-600" @click.prevent="toggle(i)">{{ expanded[i] ? 'Tutup' : 'Detail' }}</a>
                  </td>
                </tr>
                <tr v-if="expanded[i]" :key="`exp-${i}`">
                  <td colspan="4" class="px-4 py-3 bg-neutral-50">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                      <div>
                        <div class="text-xs text-neutral-600">Catatan</div>
                        <div class="text-sm">{{ r.notes }}</div>
                      </div>
                      <div>
                        <div class="text-xs text-neutral-600">Lampiran</div>
                        <a v-if="r.link" :href="r.link" class="text-brand-primary-600 text-sm">Lihat Dokumen</a>
                      </div>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </CardContainer>
      </div>
      <div class="lg:col-span-1">
        <CardContainer padding="lg" shadow="sm" class="sticky top-20">
          <h3 class="text-base font-semibold text-neutral-900">Filter</h3>
          <div class="mt-3 space-y-3">
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

const filters = reactive({ unit:'', status:'' });
const unitOptions = [];
const statusOptions = [
  { label: 'Semua', value: '' },
  { label: 'Lengkap', value: 'lengkap' },
  { label: 'Kurang', value: 'kurang' },
];
const rows = ref([
  { member:'Budi', docs:'KTP, Foto', status:'lengkap', notes:'', link:'#' },
  { member:'Sari', docs:'KTP', status:'kurang', notes:'Butuh foto terbaru', link:'' },
]);
const expanded = ref({});
function toggle(i){ expanded.value[i] = !expanded.value[i]; }
const kpi = reactive({ complete: 1, missing: 1 });
const lastUpdated = new Date().toLocaleDateString('id-ID');
function apply(){ }

const exportOpen = ref(false);
const exportForm = reactive({ format: 'Excel' });
const toast = reactive({ show:false, message:'', type:'info' });
function openExport(){ exportOpen.value = true; }
function doExport(){ exportOpen.value=false; toast.message='Sedang menyiapkan laporan…'; toast.type='info'; toast.show=true; setTimeout(()=>{ toast.message='Laporan siap didownload (lihat Notifikasi)'; toast.type='success'; toast.show=true; setTimeout(()=>toast.show=false,3000); }, 1200); }
</script>
