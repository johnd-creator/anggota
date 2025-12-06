<template>
  <AppLayout page-title="Activity Logs">
    <CardContainer padding="lg" shadow="sm">
      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-neutral-200">
          <thead class="bg-neutral-50">
            <tr>
              <th class="px-4 py-2 text-left text-xs text-neutral-500">Tanggal</th>
              <th class="px-4 py-2 text-left text-xs text-neutral-500">User</th>
              <th class="px-4 py-2 text-left text-xs text-neutral-500">Action</th>
              <th class="px-4 py-2 text-left text-xs text-neutral-500">Subject</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-neutral-200 bg-white">
            <tr v-for="l in logs.data" :key="l.id">
              <td class="px-4 py-2 text-sm">{{ l.created_at }}</td>
              <td class="px-4 py-2 text-sm">{{ l.actor_id || '-' }}</td>
              <td class="px-4 py-2 text-sm">{{ l.action }}</td>
              <td class="px-4 py-2 text-sm">{{ l.subject_type }}#{{ l.subject_id }}</td>
            </tr>
          </tbody>
        </table>
      </div>
      <div class="mt-4 flex justify-between items-center text-sm text-neutral-600">
        <div>Menampilkan {{ logs.data.length }} dari {{ logs.total }}</div>
        <div class="space-x-2">
          <Link v-if="logs.prev_page_url" :href="logs.prev_page_url" class="px-3 py-1 border rounded">Prev</Link>
          <Link v-if="logs.next_page_url" :href="logs.next_page_url" class="px-3 py-1 border rounded">Next</Link>
        </div>
      </div>
    </CardContainer>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import CardContainer from '@/Components/UI/CardContainer.vue';
import { Link, usePage } from '@inertiajs/vue3';
const page = usePage();
const logs = page.props.logs;
</script>
