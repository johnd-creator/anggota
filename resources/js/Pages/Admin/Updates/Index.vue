<template>
  <AppLayout page-title="Permintaan Perubahan Data">
    <CardContainer padding="lg" shadow="sm">
      <div class="mb-3 flex items-center gap-3">
        <SelectField v-model="status" :options="[{label:'Semua',value:''},{label:'Pending',value:'pending'},{label:'Approved',value:'approved'},{label:'Rejected',value:'rejected'}]" />
      </div>
      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-neutral-200">
          <thead class="bg-neutral-50"><tr><th class="px-4 py-2 text-left text-xs text-neutral-500">Anggota</th><th class="px-4 py-2 text-left text-xs text-neutral-500">Status</th><th class="px-4 py-2 text-left text-xs text-neutral-500">Aksi</th></tr></thead>
          <tbody class="divide-y divide-neutral-200 bg-white">
            <tr v-for="i in items.data" :key="i.id">
              <td class="px-4 py-2 text-sm">{{ i.member.full_name }}</td>
              <td class="px-4 py-2 text-sm">{{ i.status }}</td>
              <td class="px-4 py-2 text-right text-sm">
                <PrimaryButton @click="approve(i)">Approve</PrimaryButton>
                <SecondaryButton class="ml-2" @click="reject(i)">Reject</SecondaryButton>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </CardContainer>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import CardContainer from '@/Components/UI/CardContainer.vue';
import PrimaryButton from '@/Components/UI/PrimaryButton.vue';
import SecondaryButton from '@/Components/UI/SecondaryButton.vue';
import SelectField from '@/Components/UI/SelectField.vue';
import { usePage, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const page = usePage();
const items = page.props.items;
const status = ref('');
watch(status, v => router.get('/admin/updates', { status: v }, { preserveState: true, replace: true }));

function approve(i){ router.post(`/admin/updates/${i.id}/approve`); }
function reject(i){ const notes = prompt('Alasan penolakan'); if (!notes) return; router.post(`/admin/updates/${i.id}/reject`, { notes }); }
</script>

