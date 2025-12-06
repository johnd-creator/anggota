<template>
  <AppLayout page-title="Active Sessions">
    <div class="flex items-center gap-3 mb-4">
      <InputField v-model="q" placeholder="Filter email" />
      <SecondaryButton @click="apply">Terapkan</SecondaryButton>
    </div>
    <CardContainer padding="lg" shadow="sm">
      <div v-if="!sessions.data.length" class="p-8 text-center text-neutral-600">Tidak ada sesi.</div>
      <table v-else class="min-w-full divide-y divide-neutral-200">
        <thead class="bg-neutral-50">
          <tr>
            <th class="px-4 py-2 text-left text-xs text-neutral-500">User</th>
            <th class="px-4 py-2 text-left text-xs text-neutral-500">Email</th>
            <th class="px-4 py-2 text-left text-xs text-neutral-500">IP</th>
            <th class="px-4 py-2 text-left text-xs text-neutral-500">Last Activity</th>
            <th class="px-4 py-2 text-right text-xs text-neutral-500">Aksi</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-neutral-200 bg-white">
          <tr v-for="s in sessions.data" :key="s.id">
            <td class="px-4 py-2 text-sm">{{ s.name }}</td>
            <td class="px-4 py-2 text-sm">{{ s.email }}</td>
            <td class="px-4 py-2 text-sm">{{ s.ip }}</td>
            <td class="px-4 py-2 text-sm">{{ s.last_activity }}</td>
            <td class="px-4 py-2 text-right text-sm">
              <SecondaryButton @click="revoke(s)">Force Logout</SecondaryButton>
            </td>
          </tr>
        </tbody>
      </table>
    </CardContainer>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import CardContainer from '@/Components/UI/CardContainer.vue';
import InputField from '@/Components/UI/InputField.vue';
import SecondaryButton from '@/Components/UI/SecondaryButton.vue';
import { usePage, router } from '@inertiajs/vue3';
import { ref } from 'vue';
const page = usePage();
const sessions = page.props.sessions;
const q = ref('');
function apply(){ router.get('/admin/sessions', { user: q.value }, { preserveState: true }); }
function revoke(s){ router.post('/admin/sessions/revoke', { session_id: s.session_id }); }
</script>

