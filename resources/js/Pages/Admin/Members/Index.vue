<template>
  <AppLayout page-title="Anggota Serikat">
    <div class="space-y-6">
      <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h2 class="text-lg font-semibold text-neutral-900">Anggota Serikat</h2>
          <p class="text-sm text-neutral-500">Kelola daftar anggota serikat.</p>
        </div>
        <div class="flex flex-wrap gap-3">
          <SecondaryButton v-if="$page.props.auth.user.role?.name==='admin_unit'" @click="uploadOpen=true">
            <svg class="w-5 h-5 mr-2" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 17v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 9l5-5 5 5M12 4v12"/></svg>
            Upload Anggota
          </SecondaryButton>
          <CtaButton href="/admin/members/create">
            <template #icon>
              <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            </template>
            Tambah Anggota
          </CtaButton>
        </div>
      </div>
    <AlertBanner v-if="$page.props.admin_unit_missing" type="warning" message="Akun admin belum dikaitkan dengan unit" />
    <CardContainer padding="lg" shadow="sm">
      <div class="flex flex-col gap-3 mb-4">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
          <InputField v-model="search" placeholder="Cari nama/nip/email" />
          <SelectField v-if="$page.props.auth.user.role?.name==='super_admin'" v-model="selectedUnit" :options="unitOptions" placeholder="Unit" />
          <!-- <div class="flex items-center justify-end gap-2">
            <PrimaryButton size="md" @click="applyFilters">Filter</PrimaryButton>
            <SecondaryButton size="md" @click="resetFilters">Reset</SecondaryButton>
          </div> -->
        </div>
        <div class="flex items-center gap-2 flex-wrap">
          <Chip v-if="$page.props.auth.user.role?.name==='super_admin'" v-for="u in filters.units" :key="u" removable active @toggle="removeUnit(u)">{{ unitLabel(u) }}</Chip>
          <Chip v-else-if="$page.props.auth.user.role?.name==='admin_unit' && $page.props.auth.user.organization_unit_id" active>Unit: {{ unitLabel($page.props.auth.user.organization_unit_id) }}</Chip>
        </div>
      </div>

      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-neutral-200">
          <thead class="bg-neutral-50 sticky top-0 z-10">
            <tr>
              <th class="px-4 py-2 text-left text-xs font-medium text-neutral-500">No KTA</th>
              <th class="px-4 py-2 text-left text-xs font-medium text-neutral-500">Nama</th>
              <th class="px-4 py-2 text-left text-xs font-medium text-neutral-500">NIP</th>
              <th class="px-4 py-2 text-left text-xs font-medium text-neutral-500">Tanggal Lahir</th>
              <th class="px-4 py-2 text-left text-xs font-medium text-neutral-500">Jabatan Serikat</th>
              <th class="px-4 py-2 text-left text-xs font-medium text-neutral-500">Aksi</th>
            </tr>
          </thead>
          <tbody v-if="members.data.length" class="divide-y divide-neutral-200 bg-white">
            <tr v-for="m in members.data" :key="m.id">
              <td class="px-4 py-2">
                <Badge variant="brand">{{ m.kta_number || m.nra }}</Badge>
              </td>
              <td class="px-4 py-2">
                <div class="font-medium text-neutral-900">{{ m.full_name }}</div>
                <div class="text-xs text-neutral-500">{{ m.email }}</div>
              </td>
              <td class="px-4 py-2">{{ m.nip || '-' }}</td>
              <td class="px-4 py-2 text-sm">{{ formatDate(m.birth_date) }}</td>
              <td class="px-4 py-2">{{ m.union_position?.name || '-' }}</td>
              <td class="px-4 py-2">
                <div class="flex items-center justify-start gap-2">
                  <a :href="`/admin/members/${m.id}`" class="inline-flex items-center px-2 py-1 border border-neutral-300 rounded text-xs text-neutral-700 hover:bg-neutral-50">Detail</a>
                  <a :href="`/admin/members/${m.id}/edit`" class="inline-flex items-center px-2 py-1 border border-neutral-300 rounded text-xs text-neutral-700 hover:bg-neutral-50">Edit</a>
                  <button class="inline-flex items-center px-2 py-1 border border-brand-secondary-300 rounded text-xs text-brand-secondary-700 hover:bg-brand-secondary-50 disabled:opacity-50 disabled:cursor-not-allowed" :disabled="redirectingId===m.id" @click="openMutasi(m)">
                    <span v-if="redirectingId===m.id" class="inline-block w-3 h-3 align-middle mr-1"><svg viewBox="0 0 24 24" class="animate-spin"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" fill="none"/></svg></span>
                    Mutasi
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
        <div v-if="!members.data.length" class="p-8 text-center text-neutral-600">
          <p v-if="search || filters.status || filters.units.length">Tidak ada data sesuai filter.</p>
          <p v-else>Belum ada anggota. Mulai dengan menambahkan anggota baru.</p>
        </div>
      </div>

      <div class="mt-4 flex justify-between items-center text-sm text-neutral-600">
        <div>Menampilkan {{ members.data.length }} dari {{ members.total }}</div>
        <div class="space-x-2">
          <a v-if="members.prev_page_url" :href="members.prev_page_url" class="px-3 py-1 border rounded">Prev</a>
          <a v-if="members.next_page_url" :href="members.next_page_url" class="px-3 py-1 border rounded">Next</a>
        </div>
      </div>
    </CardContainer>
  </div>
  </AppLayout>
  <ModalBase v-model:show="uploadOpen" title="Upload Anggota" size="md">
    <div class="space-y-4 text-sm text-neutral-700">
      <div class="flex items-center justify-between">
        <div>Gunakan template resmi untuk mengimpor anggota.</div>
        <a href="/admin/members/import/template" target="_blank" class="inline-flex items-center px-3 py-1.5 border rounded text-xs">Unduh Template XLSX</a>
      </div>
      <div>
        <input type="file" @change="onFileChange" accept=".csv,.xlsx,.xls" />
        <div class="mt-2 text-xs text-neutral-500">
          Template mencakup data personal & organisasi. 
          * <strong>personal_email</strong>: Email Google (opsional).
          * <strong>company_email</strong>: Wajib untuk SSO Microsoft (@plnipservices.co.id).
          * <strong>personal_gender</strong>: L / P.
          * <strong>personal_phone</strong>: Format +62... (contoh: +628123456789).
          * <strong>Unit Organisasi</strong>: Otomatis mengikuti akun Admin Unit.
        </div>
      </div>
    </div>
    <template #footer>
      <div class="flex justify-end gap-3">
        <SecondaryButton @click="uploadOpen=false">Batal</SecondaryButton>
        <PrimaryButton :disabled="!uploadFile || uploading" @click="doUpload">{{ uploading ? 'Mengunggah...' : 'Upload' }}</PrimaryButton>
      </div>
    </template>
  </ModalBase>
  <Toast v-if="toast.show" :message="toast.message" :type="toast.type" position="top-center" @close="toast.show=false" />
  <div v-if="$page.props.flash?.import_summary" class="mt-3 p-3 border rounded bg-emerald-50 text-emerald-800 text-sm">
    Upload selesai: {{ $page.props.flash.import_summary.success }} sukses, {{ $page.props.flash.import_summary.failed }} gagal.
  </div>
  <div v-if="$page.props.flash?.import_errors?.length" class="mt-2 p-3 border rounded bg-amber-50 text-amber-800 text-sm">
    Beberapa baris gagal diimpor:
    <ul class="list-disc ml-5">
      <li v-for="e in $page.props.flash.import_errors.slice(0,10)" :key="e.row">Baris {{ e.row }}: {{ e.message }}</li>
    </ul>
  </div>
</template>

 <script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import CardContainer from '@/Components/UI/CardContainer.vue';
import CtaButton from '@/Components/UI/CtaButton.vue';
import InputField from '@/Components/UI/InputField.vue';
import SelectField from '@/Components/UI/SelectField.vue';
import PrimaryButton from '@/Components/UI/PrimaryButton.vue';
import SecondaryButton from '@/Components/UI/SecondaryButton.vue';
import AlertBanner from '@/Components/UI/AlertBanner.vue';
import Badge from '@/Components/UI/Badge.vue';
import Chip from '@/Components/UI/Chip.vue';
import ModalBase from '@/Components/UI/ModalBase.vue';
import Toast from '@/Components/UI/Toast.vue';
import { router, usePage } from '@inertiajs/vue3';
import { reactive, ref, watch } from 'vue';

 const page = usePage();
const members = page.props.members;
const units = page.props.units || [];
const unitOptions = units.map(u => ({ label: `${u.code} - ${u.name}`, value: u.id }));
// status filter not used

const initialFilters = page.props.filters || {};
const filters = reactive({ status: '', units: initialFilters.units || [] });
const search = ref(initialFilters.search || '');
const selectedUnit = ref('');
const sort = reactive({ key: initialFilters.sort || 'name', dir: initialFilters.dir || 'asc' });
const redirectingId = ref(null);
const uploadOpen = ref(false);
const uploadFile = ref(null);
const toast = reactive({ show:false, message:'', type:'info' });
const uploading = ref(false);

let t = null;
watch(search, (val) => {
  if (t) clearTimeout(t);
  t = setTimeout(() => applyFilters(), 350);
});

watch(() => filters.units.slice(), () => reload());

function reload(){
  const params = { search: search.value, sort: sort.key, dir: sort.dir, page: 1 };
  if (filters.units && filters.units.length) {
    params['units[]'] = filters.units;
  }
  router.get('/admin/members', params, { preserveState: false, preserveScroll: true, replace: true });
}

function applyFilters(){ reload(); }

function unitLabel(id){ const u = units.find(x => x.id === id); return u ? `${u.code} - ${u.name}` : `Unit ${id}`; }
function removeUnit(id){ filters.units = filters.units.filter(x => x !== id); }

function resetFilters(){
  search.value = '';
  filters.units = [];
  sort.key = 'name';
  sort.dir = 'asc';
  reload();
}

function toggleSort(key){ sort.dir = sort.key === key ? (sort.dir === 'asc' ? 'desc' : 'asc') : 'asc'; sort.key = key; }

function statusVariant(s){
  switch (s) {
    case 'aktif': return 'success'
    case 'cuti': return 'warning'
    case 'suspended': return 'danger'
    case 'resign': return 'neutral'
    case 'pensiun': return 'neutral'
    default: return 'neutral'
  }
}

function openMutasi(m){
  if (redirectingId.value) return;
  redirectingId.value = m.id;
  router.get('/admin/mutations', { member_id: m.id }, { onFinish(){ redirectingId.value = null; } });
}

watch(selectedUnit, (v) => { if (v) { if (!filters.units.includes(v)) filters.units.push(v); selectedUnit.value=''; } });

const SortIcon = {
  props: ['dir','active'],
  template: `<span class="inline-block w-3 h-3" :class="active ? 'text-neutral-700' : 'text-neutral-300'"><svg v-if="dir==='asc'" viewBox="0 0 20 20" fill="currentColor"><path d="M7 7l3-3 3 3M7 13l3 3 3-3"/></svg><svg v-else viewBox="0 0 20 20" fill="currentColor"><path d="M7 13l3 3 3-3M7 7l3-3 3 3"/></svg></span>`
};

function formatDate(d){
  if (!d) return '-';
  try {
    const dt = new Date(d);
    return dt.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
  } catch { return '-'; }
}
function onFileChange(e){ uploadFile.value = e.target.files[0] || null; }
function doUpload(){
  if (!uploadFile.value) return;
  const fd = new FormData();
  fd.append('file', uploadFile.value);
  router.post('/admin/members/import', fd, {
    forceFormData: true,
    onStart(){ uploading.value = true; },
    onFinish(){ uploading.value = false; },
    onSuccess(){ uploadOpen.value=false; uploadFile.value=null; toast.message='Upload berhasil diproses'; toast.type='success'; toast.show=true; reload(); },
    onError(){ toast.message='Upload gagal. Periksa format file dan isian.'; toast.type='danger'; toast.show=true; }
  });
}
</script>
 
