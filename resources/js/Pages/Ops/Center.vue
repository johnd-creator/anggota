<template>
  <AppLayout page-title="Ops Center">
    <div class="space-y-6">
      <div class="flex items-center justify-between">
        <nav class="text-sm text-neutral-600">
          <a href="/dashboard" class="hover:underline">Dashboard</a> / <span>Ops Center</span>
        </nav>
        <a href="/docs/ops/backup-dr" class="text-sm text-brand-primary-600">Runbook Backup & DR</a>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <CardContainer padding="lg" shadow="sm">
          <div class="flex items-start justify-between">
            <div>
              <h3 class="text-lg font-semibold text-neutral-900">Health Checks</h3>
              <p class="text-sm text-neutral-600">Status komponen inti</p>
            </div>
          </div>
          <div class="mt-4 space-y-3">
            <div class="flex items-center justify-between">
              <div class="flex items-center gap-3">
                <div class="flex-shrink-0 bg-neutral-100 rounded-md p-2">
                  <svg class="h-5 w-5 text-neutral-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>
                </div>
                <div>
                  <div class="text-sm font-medium">Database</div>
                  <div class="text-xs text-neutral-500">Koneksi DB</div>
                </div>
              </div>
              <Badge :variant="statusVariant(health.db)">{{ statusLabel(health.db) }}</Badge>
            </div>
            <div class="flex items-center justify-between">
              <div class="flex items-center gap-3">
                <div class="flex-shrink-0 bg-neutral-100 rounded-md p-2">
                  <svg class="h-5 w-5 text-neutral-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7h18M6 11h12M10 15h4"/></svg>
                </div>
                <div>
                  <div class="text-sm font-medium">Cache</div>
                  <div class="text-xs text-neutral-500">Write/Read</div>
                </div>
              </div>
              <Badge :variant="statusVariant(health.cache)">{{ statusLabel(health.cache) }}</Badge>
            </div>
            <div class="flex items-center justify-between">
              <div class="flex items-center gap-3">
                <div class="flex-shrink-0 bg-neutral-100 rounded-md p-2">
                  <svg class="h-5 w-5 text-neutral-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V7a2 2 0 00-2-2h-5M4 17v-6a2 2 0 012-2h5"/></svg>
                </div>
                <div>
                  <div class="text-sm font-medium">Queue</div>
                  <div class="text-xs text-neutral-500">Worker/Jobs</div>
                </div>
              </div>
              <Badge :variant="statusVariant(health.queue)">{{ statusLabel(health.queue) }}</Badge>
            </div>
            <div class="flex items-center justify-between">
              <div class="flex items-center gap-3">
                <div class="flex-shrink-0 bg-neutral-100 rounded-md p-2">
                  <svg class="h-5 w-5 text-neutral-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7l9 4 9-4-9-4-9 4zm0 0v6l9 4 9-4V7"/></svg>
                </div>
                <div>
                  <div class="text-sm font-medium">Storage</div>
                  <div class="text-xs text-neutral-500">Writable</div>
                </div>
              </div>
              <Badge :variant="statusVariant(health.storage)">{{ statusLabel(health.storage) }}</Badge>
            </div>
          </div>
        </CardContainer>

        <CardContainer padding="lg" shadow="sm">
          <div class="flex items-start justify-between">
            <div>
              <h3 class="text-lg font-semibold text-neutral-900">Backup Terakhir</h3>
              <p class="text-sm text-neutral-600">Monitoring backup harian</p>
            </div>
            <a href="/docs/ops/backup-dr" class="text-sm text-brand-primary-600">Lihat Runbook</a>
          </div>
          <div class="mt-4">
            <div v-if="lastBackup" class="space-y-2">
              <div class="text-sm text-neutral-900">File: {{ lastBackup.path }}</div>
              <div class="text-sm text-neutral-900">Waktu: {{ formatTime(lastBackup.modified_at) }}</div>
              <div class="text-sm text-neutral-900">Ukuran: {{ formatSize(lastBackup.size) }}</div>
            </div>
            <div v-else class="text-sm text-neutral-600">Belum ada data backup yang terdeteksi.</div>
          </div>
        </CardContainer>

        <CardContainer padding="lg" shadow="sm">
          <div class="flex items-start justify-between">
            <div>
              <h3 class="text-lg font-semibold text-neutral-900">KPI Utama</h3>
              <p class="text-sm text-neutral-600">Ringkasan operasional</p>
            </div>
            <a href="/reports/growth" class="text-sm text-brand-primary-600">Laporan</a>
          </div>
          <div class="mt-4 grid grid-cols-2 gap-4">
            <div class="flex items-center">
              <div class="flex-shrink-0 bg-brand-primary-100 rounded-md p-2">
                <svg class="h-5 w-5 text-brand-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3v18h18"/></svg>
              </div>
              <div class="ml-3">
                <div class="text-xs text-neutral-500">Anggota</div>
                <div class="text-sm font-medium text-neutral-900">{{ counters.members_total || 0 }}</div>
              </div>
            </div>
            <div class="flex items-center">
              <div class="flex-shrink-0 bg-brand-primary-100 rounded-md p-2">
                <svg class="h-5 w-5 text-brand-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 17l5-5 5 5M7 7l5 5 5-5"/></svg>
              </div>
              <div class="ml-3">
                <div class="text-xs text-neutral-500">Mutasi Pending</div>
                <div class="text-sm font-medium text-neutral-900">{{ metrics.mutations_pending || counters.mutations_pending || 0 }}</div>
              </div>
            </div>
            <div class="flex items-center">
              <div class="flex-shrink-0 bg-brand-primary-100 rounded-md p-2">
                <svg class="h-5 w-5 text-brand-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
              </div>
              <div class="ml-3">
                <div class="text-xs text-neutral-500">Queue</div>
                <div class="text-sm font-medium text-neutral-900">{{ metrics.queue_length || 0 }}</div>
              </div>
            </div>
            <div class="flex items-center">
              <div class="flex-shrink-0 bg-brand-primary-100 rounded-md p-2">
                <svg class="h-5 w-5 text-brand-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v8m-4-4h8"/></svg>
              </div>
              <div class="ml-3">
                <div class="text-xs text-neutral-500">Units</div>
                <div class="text-sm font-medium text-neutral-900">{{ counters.units_total || 0 }}</div>
              </div>
            </div>
          </div>
        </CardContainer>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import CardContainer from '@/Components/UI/CardContainer.vue';
import Badge from '@/Components/UI/Badge.vue';
import { usePage } from '@inertiajs/vue3';
import { ref, onMounted } from 'vue';

const page = usePage();
const counters = page.props.counters || {};
const lastBackup = page.props.last_backup || null;
const health = ref({ app: null, db: null, cache: null, queue: null, storage: null });
const metrics = ref({ mutations_pending: 0, members_total: 0, queue_length: 0 });

function statusVariant(s){ if (s === 'ok') return 'success'; if (s === 'error') return 'danger'; return 'neutral'; }
function statusLabel(s){ if (s === 'ok') return 'OK'; if (s === 'error') return 'Error'; return 'Unknown'; }
function formatTime(s){ try { return new Date(s).toLocaleString(); } catch(e) { return s; } }
function formatSize(n){ if (!n && n !== 0) return '-'; const kb = n/1024; if (kb < 1024) return `${kb.toFixed(1)} KB`; const mb = kb/1024; return `${mb.toFixed(1)} MB`; }

onMounted(async () => {
  try { const r = await fetch('/health'); if (r.ok) health.value = await r.json(); } catch(e) {}
  try { const r2 = await fetch('/metrics'); if (r2.ok) metrics.value = await r2.json(); } catch(e) {}
});
</script>

