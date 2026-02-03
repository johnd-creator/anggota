<template>
  <AppLayout page-title="Anggota Serikat">
    <div class="space-y-6">
      <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h2 class="text-lg font-semibold text-neutral-900">Anggota Serikat</h2>
          <p class="text-sm text-neutral-500">Kelola daftar anggota serikat.</p>
        </div>
         <div class="flex flex-wrap gap-3">
          <SecondaryButton v-if="!isPengurus && $page.props.auth.user.role?.name==='admin_unit'" href="/admin/members/import">
            <svg class="w-5 h-5 mr-2" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 17v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 9l5-5 5 5M12 4v12"/></svg>
            Import Anggota
          </SecondaryButton>
          <CtaButton v-if="!isPengurus" href="/admin/members/create">
            <template #icon>
              <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            </template>
            Tambah Anggota
          </CtaButton>
        </div>
      </div>
    <AlertBanner v-if="$page.props.admin_unit_missing" type="warning" message="Akun admin belum dikaitkan dengan unit" />
    <CardContainer padding="sm">
      <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
        <InputField v-model="search" placeholder="Cari nama/nip/email" />
        <SelectField v-if="$page.props.auth.user.role?.name==='super_admin'" v-model="selectedUnit" :options="unitOptions" placeholder="Unit" />
      </div>
      <div class="flex items-center gap-2 flex-wrap">
        <Chip v-if="$page.props.auth.user.role?.name==='super_admin'" v-for="u in filters.units" :key="u" removable active @toggle="removeUnit(u)">{{ unitLabel(u) }}</Chip>
        <Chip v-else-if="$page.props.auth.user.role?.name==='admin_unit' && $page.props.auth.user.organization_unit_id" active>Unit: {{ unitLabel($page.props.auth.user.organization_unit_id) }}</Chip>
      </div>
    </CardContainer>
    <CardContainer padding="none" class="overflow-hidden">

      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-neutral-200">
          <thead class="bg-neutral-50 sticky top-0 z-10">
            <tr>
              <th class="px-5 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wide">Foto</th>
              <th class="px-5 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wide">No KTA</th>
              <th class="px-5 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wide">Nama</th>
              <th class="px-5 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wide">NIP</th>
              <th class="px-5 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wide">Tanggal Lahir</th>
              <th class="px-5 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wide">Jabatan Serikat</th>
              <th class="px-5 py-3 text-right text-xs font-medium text-neutral-500 uppercase tracking-wide">Aksi</th>
            </tr>
          </thead>
          <tbody v-if="members.data.length" class="divide-y divide-neutral-200 bg-white">
            <tr v-for="m in members.data" :key="m.id">
              <td class="px-5 py-3">
                <OptimizedImage
                  :src="m.photo_path"
                  :alt="$toTitleCase(m.full_name)"
                  size="thumb"
                  class="h-10 w-10 rounded-full object-cover"
                  loading="lazy"
                />
              </td>
              <td class="px-5 py-3">
                <Badge variant="brand">{{ m.kta_number || m.nra }}</Badge>
              </td>
              <td class="px-5 py-3">
                <div class="font-medium text-neutral-900">{{ $toTitleCase(m.full_name) }}</div>
                <div class="text-xs text-neutral-500">{{ m.email }}</div>
              </td>
              <td class="px-5 py-3">{{ m.nip || '-' }}</td>
              <td class="px-5 py-3 text-sm">{{ formatDate(m.birth_date) }}</td>
              <td class="px-5 py-3">{{ m.union_position?.name || '-' }}</td>
              <td class="px-5 py-3 text-right">
                <div class="flex items-center justify-end gap-2.5">
                  <IconButton :aria-label="`Detail ${$toTitleCase(m.full_name)}`" size="sm" @click="router.get(`/admin/members/${m.id}`)">
                    <svg class="w-5 h-5 text-neutral-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                    </svg>
                  </IconButton>
                  <IconButton v-if="!isPengurus" :aria-label="`Edit ${$toTitleCase(m.full_name)}`" size="sm" @click="router.get(`/admin/members/${m.id}/edit`)">
                    <svg class="w-5 h-5 text-brand-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                    </svg>
                  </IconButton>
                  <IconButton v-if="!isPengurus" :aria-label="`Mutasi ${$toTitleCase(m.full_name)}`" size="sm" :disabled="redirectingId===m.id" @click="openMutasi(m)">
                    <span v-if="redirectingId===m.id" class="inline-block w-4 h-4 animate-spin">
                      <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" fill="none"/></svg>
                    </span>
                    <svg v-else class="w-5 h-5 text-brand-secondary-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h10m0 0l-3-3m3 3l-3 3m3 7H6m0 0l3 3m-3-3l3-3" />
                    </svg>
                  </IconButton>
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

    <Pagination :paginator="members" />
    </CardContainer>
  </div>
  </AppLayout>
</template>

 <script setup>
 import AppLayout from '@/Layouts/AppLayout.vue';
 import CardContainer from '@/Components/UI/CardContainer.vue';
 import Pagination from '@/Components/UI/Pagination.vue';
 import IconButton from '@/Components/UI/IconButton.vue';
 import CtaButton from '@/Components/UI/CtaButton.vue';
 import InputField from '@/Components/UI/InputField.vue';
 import SelectField from '@/Components/UI/SelectField.vue';
 import PrimaryButton from '@/Components/UI/PrimaryButton.vue';
 import SecondaryButton from '@/Components/UI/SecondaryButton.vue';
 import AlertBanner from '@/Components/UI/AlertBanner.vue';
 import Badge from '@/Components/UI/Badge.vue';
 import Chip from '@/Components/UI/Chip.vue';
 import OptimizedImage from '@/Components/OptimizedImage.vue';
 import { router, usePage } from '@inertiajs/vue3';
 import { computed, reactive, ref, watch } from 'vue';

  const page = usePage();
  const members = computed(() => page.props.members);
  const units = page.props.units || [];
  const unitOptions = units.map(u => ({ label: `${u.code} - ${u.name}`, value: u.id }));
  const isPengurus = computed(() => page.props.auth.user.role?.name === 'pengurus');

const initialFilters = page.props.filters || {};
const filters = reactive({ status: '', units: initialFilters.units || [] });
const search = ref(page.props.search || initialFilters.search || '');
const selectedUnit = ref('');
const sort = reactive({ key: initialFilters.sort || 'name', dir: initialFilters.dir || 'asc' });
const redirectingId = ref(null);

let t = null;
watch(search, (val) => {
  if (t) clearTimeout(t);
  t = setTimeout(() => applyFilters(), 350);
});

watch(() => filters.units.slice(), () => reload());
watch(() => page.props.search, (val) => {
  const next = val || '';
  if (next !== search.value) {
    search.value = next;
  }
});

function reload(){
  const params = { search: search.value, sort: sort.key, dir: sort.dir, page: 1 };
  if (filters.units && filters.units.length) {
    params['units[]'] = filters.units;
  }
  router.get('/admin/members', params, { preserveState: true, preserveScroll: true, replace: true });
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

function openMutasi(m){
  if (redirectingId.value) return;
  redirectingId.value = m.id;
  router.get('/admin/mutations', { member_id: m.id }, { onFinish(){ redirectingId.value = null; } });
}

watch(selectedUnit, (v) => { if (v) { if (!filters.units.includes(v)) filters.units.push(v); selectedUnit.value=''; } });

function formatDate(d){
  if (!d) return '-';
  try {
    const dt = new Date(d);
    return dt.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
  } catch { return '-'; }
}
</script>

