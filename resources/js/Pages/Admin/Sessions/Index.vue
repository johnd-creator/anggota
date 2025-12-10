<template>
  <AppLayout page-title="Admin Sessions">
    <CardContainer padding="lg" shadow="sm">
      <div class="mb-4 flex flex-wrap gap-2 items-center">
        <input v-model="filterForm.search" placeholder="Cari Email/Nama" class="border rounded px-3 py-2 text-sm w-full md:w-64" @keyup.enter="applyFilter" />
        
        <select v-model="filterForm.role" class="border rounded px-3 py-2 text-sm bg-white">
           <option value="">Semua Role</option>
           <option value="super_admin">Super Admin</option>
           <option value="admin_pusat">Admin Pusat</option>
           <option value="admin_unit">Admin Unit</option>
           <option value="bendahara">Bendahara</option>
           <option value="anggota">Anggota</option>
           <option value="reguler">Reguler</option>
        </select>
        
        <input type="date" v-model="filterForm.date_start" class="border rounded px-3 py-2 text-sm" />
        <span class="text-sm text-gray-500">-</span>
        <input type="date" v-model="filterForm.date_end" class="border rounded px-3 py-2 text-sm" />
        
        <button @click="applyFilter" class="px-4 py-2 bg-blue-600 text-white rounded text-sm hover:bg-blue-700 transition">Filter</button>
        <button @click="resetFilter" class="px-4 py-2 bg-gray-200 text-gray-700 rounded text-sm hover:bg-gray-300 transition">Reset</button>
      </div>

      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-neutral-200">
          <thead class="bg-neutral-50">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Username</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Role</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">IP Address</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Device/UA</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Aktivitas Terakhir</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-neutral-200 bg-white">
            <tr v-for="s in sessions.data" :key="s.id" class="hover:bg-neutral-50">
              <td class="px-6 py-4 text-sm">
                 <div class="font-medium text-neutral-900">{{ s.user?.name || 'Unknown' }}</div>
              </td>
              <td class="px-6 py-4 text-sm text-neutral-600">
                <span class="px-2 py-1 bg-gray-100 rounded text-xs">{{ s.user?.role?.name || '-' }}</span>
              </td>
              <td class="px-6 py-4 text-sm text-neutral-600">{{ s.ip }}</td>
              <td class="px-6 py-4 text-sm text-neutral-600 max-w-xs truncate" :title="s.user_agent">{{ s.user_agent }}</td>
              <td class="px-6 py-4 text-sm text-neutral-600">
                <div>{{ formatDate(s.last_activity) }}</div>
                <div class="text-xs text-neutral-400">{{ timeAgo(s.last_activity) }}</div>
              </td>
              <td class="px-6 py-4 text-sm space-y-1">
                <button @click="terminate(s.id)" class="text-red-600 hover:text-red-800 text-xs font-semibold block">Terminate</button>
                <button @click="terminateAll(s.user_id)" class="text-red-600 hover:text-red-800 text-xs font-semibold block">Terminate All User Sessions</button>
              </td>
            </tr>
            <tr v-if="sessions.data.length === 0">
                <td colspan="6" class="px-6 py-4 text-center text-sm text-neutral-500">Tidak ada data sesi.</td>
            </tr>
          </tbody>
        </table>
      </div>
      
      <div v-if="sessions.links && sessions.links.length > 3" class="mt-4 flex justify-between items-center text-sm text-neutral-600">
        <div>Menampilkan {{ sessions.from || 0 }} - {{ sessions.to || 0 }} dari {{ sessions.total }}</div>
        <div class="flex gap-1">
           <template v-for="(link, k) in sessions.links" :key="k">
               <Link 
                  v-if="link.url" 
                  :href="link.url" 
                  v-html="link.label" 
                  class="px-3 py-1 border rounded text-xs" 
                  :class="{'bg-blue-50 border-blue-500 text-blue-600': link.active, 'bg-white text-gray-700': !link.active}" 
               />
               <span v-else v-html="link.label" class="px-3 py-1 border rounded text-xs bg-gray-50 text-gray-400"></span>
           </template>
        </div>
      </div>
    </CardContainer>
  </AppLayout>
</template>

<script setup>
import { reactive } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import CardContainer from '@/Components/UI/CardContainer.vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
  sessions: Object,
  filters: Object,
});

const filterForm = reactive({
    search: props.filters.search || '',
    role: props.filters.role || '',
    date_start: props.filters.date_start || '',
    date_end: props.filters.date_end || '',
});

function applyFilter() {
  router.get(route('admin.sessions.index'), filterForm, { preserveState: true, replace: true });
}

function resetFilter() {
    filterForm.search = '';
    filterForm.role = '';
    filterForm.date_start = '';
    filterForm.date_end = '';
    applyFilter();
}

function terminate(id) {
  if (confirm('Yakin ingin menghentikan sesi ini?')) {
    router.delete(route('admin.sessions.destroy', id));
  }
}

function terminateAll(userId) {
  if (confirm('Yakin ingin menghentikan SEMUA sesi user ini?')) {
    router.delete(route('admin.sessions.destroy_user', userId));
  }
}

function formatDate(date) {
    if (!date) return '-';
    return new Date(date).toLocaleString('id-ID', { dateStyle: 'medium', timeStyle: 'short' });
}

function timeAgo(date) {
    if (!date) return '';
    const seconds = Math.floor((new Date() - new Date(date)) / 1000);
    if (seconds < 60) return 'Baru saja';
    const minutes = Math.floor(seconds / 60);
    if (minutes < 60) return `${minutes} menit lalu`;
    const hours = Math.floor(minutes / 60);
    if (hours < 24) return `${hours} jam lalu`;
    const days = Math.floor(hours / 24);
    return `${days} hari lalu`;
}
</script>
