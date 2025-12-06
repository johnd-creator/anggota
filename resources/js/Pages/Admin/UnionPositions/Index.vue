<template>
  <AppLayout page-title="Master Data: Jabatan Serikat">
    <template #actions>
      <a href="/admin/union-positions/create" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white font-medium rounded-lg shadow-sm hover:bg-blue-700 transition-colors duration-200">
        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
        </svg>
        Tambah Jabatan
      </a>
    </template>
    <div class="space-y-6">
      <div class="flex items-center justify-between">
        <div class="text-sm text-neutral-600">Kelola master data jabatan serikat.</div>
      </div>
      <AlertBanner v-if="$page.props.flash.success" type="success" :message="$page.props.flash.success" dismissible @dismiss="$page.props.flash.success=null" />
      <CardContainer padding="sm">
        <div class="flex items-center">
          <div class="w-full max-w-md"><InputField v-model="q" placeholder="Cari nama atau kode" class="w-full" /></div>
        </div>
      </CardContainer>
      <CardContainer padding="none" class="overflow-hidden">
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-neutral-200">
            <thead class="bg-neutral-50">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500">Nama</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500">Kode</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500">Deskripsi</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500">Aksi</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-neutral-200 bg-white">
              <tr v-for="i in items.data" :key="i.id">
                <td class="px-6 py-2 text-sm">{{ i.name }}</td>
                <td class="px-6 py-2 text-sm">{{ i.code || '-' }}</td>
                <td class="px-6 py-2 text-sm">{{ i.description || '-' }}</td>
                <td class="px-6 py-2">
                  <div class="flex items-center gap-2">
                    <a :href="`/admin/union-positions/${i.id}/edit`" class="inline-flex items-center px-2 py-1 border border-neutral-300 rounded text-xs text-neutral-700 hover:bg-neutral-50">Edit</a>
                    <button @click="confirmDelete(i)" class="inline-flex items-center px-2 py-1 border border-red-300 rounded text-xs text-red-700 hover:bg-red-50">Hapus</button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
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
import InputField from '@/Components/UI/InputField.vue';
import AlertBanner from '@/Components/UI/AlertBanner.vue';
import PrimaryButton from '@/Components/UI/PrimaryButton.vue';
import SecondaryButton from '@/Components/UI/SecondaryButton.vue';
import ModalBase from '@/Components/UI/ModalBase.vue';
import { router, usePage } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const page = usePage();
const items = page.props.items;
const q = ref(page.props.search || '');
const delOpen = ref(false);
const delItem = ref(null);

let t=null; watch(q, (v)=>{ if(t) clearTimeout(t); t=setTimeout(()=>router.get('/admin/union-positions', { search: q.value }, { preserveState: true, replace: true }), 300) });
function confirmDelete(i){ delItem.value = i; delOpen.value = true; }
function doDelete(){ router.delete(`/admin/union-positions/${delItem.value.id}`, { onSuccess(){ delOpen.value=false; } }); }
</script>
