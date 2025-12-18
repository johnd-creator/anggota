<template>
  <AppLayout page-title="Master Data: Jabatan Serikat">
    <div class="space-y-6">
      <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h2 class="text-lg font-semibold text-neutral-900">Jabatan Serikat</h2>
          <p class="text-sm text-neutral-500">Kelola master data jabatan serikat.</p>
        </div>
        <div class="flex flex-wrap gap-3">
          <CtaButton href="/admin/union-positions/create">
            <template #icon>
              <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            </template>
            Tambah Jabatan
          </CtaButton>
        </div>
      </div>
      <CardContainer padding="sm">
        <div class="flex items-center">
          <div class="w-full max-w-md">
            <InputField 
              v-model="q" 
              placeholder="Cari nama atau kode" 
              class="w-full" 
            />
          </div>
        </div>
      </CardContainer>
      <CardContainer padding="none" class="overflow-hidden">
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-neutral-200">
            <thead class="bg-neutral-50">
              <tr>
                <th class="px-5 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Nama</th>
                <th class="px-5 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Kode</th>
                <th class="px-5 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Deskripsi</th>
                <th class="px-5 py-3 text-right text-xs font-medium text-neutral-500 uppercase tracking-wider">Aksi</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-neutral-200 bg-white">
              <tr v-for="i in items.data" :key="i.id">
                <td class="px-5 py-3 text-sm">{{ i.name }}</td>
                <td class="px-5 py-3 text-sm">{{ i.code || '-' }}</td>
                <td class="px-5 py-3 text-sm">{{ i.description || '-' }}</td>
                <td class="px-5 py-3 text-right">
                  <div class="flex items-center justify-end gap-2">
                    <IconButton :aria-label="`Edit ${i.name}`" size="sm" @click="router.get(`/admin/union-positions/${i.id}/edit`)">
                      <svg class="w-5 h-5 text-brand-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                      </svg>
                    </IconButton>
                    <IconButton :aria-label="`Hapus ${i.name}`" size="sm" @click="confirmDelete(i)">
                      <svg class="w-5 h-5 text-status-error" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                      </svg>
                    </IconButton>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      <Pagination :paginator="items" />
      </CardContainer>

      <ModalBase v-model:show="delOpen" title="Konfirmasi Hapus">
        <div class="text-sm">Apakah Anda yakin ingin menghapus jabatan <b>{{ delItem?.name }}</b>?</div>
        <template #footer>
          <div class="flex justify-end gap-2">
            <SecondaryButton @click="delOpen=false">Batal</SecondaryButton>
            <PrimaryButton @click="doDelete">Hapus</PrimaryButton>
          </div>
        </template>
      </ModalBase>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import CardContainer from '@/Components/UI/CardContainer.vue';
import Pagination from '@/Components/UI/Pagination.vue';
import CtaButton from '@/Components/UI/CtaButton.vue';
import InputField from '@/Components/UI/InputField.vue';
import PrimaryButton from '@/Components/UI/PrimaryButton.vue';
import SecondaryButton from '@/Components/UI/SecondaryButton.vue';
import ModalBase from '@/Components/UI/ModalBase.vue';
import IconButton from '@/Components/UI/IconButton.vue';
import { router, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const page = usePage();
const items = computed(() => page.props.items);
const q = ref(page.props.search || '');
const delOpen = ref(false);
const delItem = ref(null);

watch(() => page.props.search, (val) => {
  if ((val || '') !== q.value) {
    q.value = val || '';
  }
});

let t=null;
watch(q, (v)=>{
  if(t) clearTimeout(t);
  t=setTimeout(()=>{
    const params = v ? { search: v } : {};
    router.get('/admin/union-positions', params, { preserveState: true, replace: true });
  }, 300);
});
function confirmDelete(i){ delItem.value = i; delOpen.value = true; }
function doDelete(){ router.delete(`/admin/union-positions/${delItem.value.id}`, { onSuccess(){ delOpen.value=false; } }); }
</script>
