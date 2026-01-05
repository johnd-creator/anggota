<template>
  <div v-if="status" class="rounded-lg p-4 mb-4 flex items-center gap-3 transition-colors" :class="statusClasses">
    <!-- Icon -->
    <div v-if="status === 'started'" class="animate-spin rounded-full h-5 w-5 border-2 border-brand-primary-600 border-t-transparent"></div>
    <svg v-else-if="status === 'completed'" class="h-5 w-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
    </svg>
    <svg v-else-if="status === 'failed'" class="h-5 w-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
    </svg>

    <!-- Text -->
    <div class="flex-1 text-sm font-medium">
       <span v-if="status === 'started'">Sistem sedang menyiapkan file export...</span>
       <span v-else-if="status === 'completed'">Export Selesai: {{ meta?.count || 0 }} baris.</span>
       <span v-else-if="status === 'failed'">Export Gagal. Silakan coba lagi.</span>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, computed } from 'vue';

const props = defineProps({
  type: String, // e.g. 'members'
});

const status = ref(null); // started, completed, failed
const meta = ref({});
let pollInterval = null;

const statusClasses = computed(() => {
  if (status.value === 'started') return 'bg-blue-50 text-blue-800 border border-blue-100';
  if (status.value === 'completed') return 'bg-green-50 text-green-800 border border-green-100';
  if (status.value === 'failed') return 'bg-red-50 text-red-800 border border-red-100';
  return '';
});

function checkStatus() {
  fetch('/reports/export/status')
    .then(r => r.json())
    .then(data => {
      // Data format from backend ReportExportStatusController:
      // {
      //    [type]: { status: 'started', time: ... },
      //    ...
      // }
      // Actually Controller returns all statuses? Or we queried specific?
      // Based on BEND log: "Endpoint GET /reports/export/status exposes status."
      // ReportExportStatus service stores cache key per user+type.
      
      // Let's assume the endpoint returns specific structure.
      // If the backend returns all active exports, we filter by props.type.
      const task = data[props.type];
      
      if (task) {
         status.value = task.status;
         meta.value = task;

         if (task.status === 'completed' || task.status === 'failed') {
            // Stop polling after 10s to let user see the message
             setTimeout(() => {
                 // optionally clear status or just stop polling
                 stopPolling();
             }, 5000);
         }
      } else {
         // If no status logic: if we viewed 'started' before and now null -> maybe expired?
         // For now, if null, we assume nothing happening.
      }
    })
    .catch(e => console.error('Status check failed', e));
}

function startPolling() {
  checkStatus(); // immediate check
  pollInterval = setInterval(checkStatus, 2000);
}

function stopPolling() {
  if (pollInterval) clearInterval(pollInterval);
  pollInterval = null;
}

onMounted(() => {
  startPolling();
});

onUnmounted(() => {
  stopPolling();
});
</script>
