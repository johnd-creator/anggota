<template>
  <AppLayout page-title="Notification Center">
    <CardContainer padding="lg" shadow="sm">
      <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
        <div class="flex items-center gap-2 text-sm w-full overflow-x-auto whitespace-nowrap pb-2 md:pb-0 md:w-auto no-scrollbar">
          <button :class="tabClass('all')" @click="setTab('all')">All</button>
          <button :class="tabClass('surat')" @click="setTab('surat')">Surat</button>
          <button :class="tabClass('mutations')" @click="setTab('mutations')">Mutations</button>
          <button :class="tabClass('updates')" @click="setTab('updates')">Updates</button>
          <button :class="tabClass('onboarding')" @click="setTab('onboarding')">Onboarding</button>
          <button :class="tabClass('security')" @click="setTab('security')">Security</button>
        </div>
        <div class="flex flex-col md:flex-row items-stretch md:items-center gap-2 w-full md:w-auto">
          <SecondaryButton @click="markAllRead" class="justify-center">Tandai semua sudah dibaca</SecondaryButton>
          <div class="flex items-center gap-2">
              <input v-model="search" type="text" placeholder="Cari..." class="flex-1 px-2 py-1 border rounded text-sm w-full md:w-auto" />
              <SecondaryButton @click="applyFilters" class="md:hidden">Go</SecondaryButton>
          </div>
          <div class="flex items-center gap-2">
              <input v-model="dateStart" type="date" class="flex-1 px-2 py-1 border rounded text-sm" />
              <span class="text-sm">s/d</span>
              <input v-model="dateEnd" type="date" class="flex-1 px-2 py-1 border rounded text-sm" />
          </div>
          <SecondaryButton @click="applyFilters" class="hidden md:inline-flex">Terapkan</SecondaryButton>
        </div>
      </div>

      <div v-if="!filtered.length" class="p-8 text-center text-neutral-600">Tidak ada notifikasi untuk tab ini.</div>
      <div v-else class="divide-y divide-neutral-200">
        <!-- Desktop List -->
        <div class="hidden md:block">
            <div v-for="n in filtered" :key="n.id" class="py-3 flex items-start gap-3">
            <div class="mt-1">
                <span :class="n.read_at ? 'bg-neutral-300' : 'bg-brand-primary-600'" class="inline-block w-2 h-2 rounded-full"></span>
            </div>
            <div class="flex-1">
                <div class="text-sm font-medium text-neutral-900 flex items-center gap-2">
                <span>{{ n.title || messageOf(n) }}</span>
                <Badge :variant="n.read_at ? 'neutral' : 'brand'">{{ n.read_at ? 'Dibaca' : 'Baru' }}</Badge>
                </div>
                <div class="text-xs text-neutral-500">{{ relativeTime(n.created_at) }}</div>
                <div class="mt-1 text-sm text-neutral-700">{{ messageOf(n) }}</div>
                <div class="mt-2">
                <a v-if="ctaUrlOf(n)" :href="ctaUrlOf(n)" class="text-brand-primary-600">{{ ctaLabelOf(n) }}</a>
                </div>
            </div>
            <div>
                <IconButton @click="toggleRead(n)">{{ n.read_at ? 'Tandai belum dibaca' : 'Tandai dibaca' }}</IconButton>
            </div>
            </div>
        </div>

        <!-- Mobile Data Cards -->
        <div class="md:hidden space-y-3 pt-2">
            <DataCard
                v-for="n in filtered"
                :key="n.id"
                :title="n.title || messageOf(n)"
                :subtitle="messageOf(n)"
                :status="{ label: n.read_at ? 'Dibaca' : 'Baru', color: n.read_at ? 'neutral' : 'brand' }"
                :meta="[
                    { label: 'Waktu', value: relativeTime(n.created_at) }
                ]"
            >
                <template #actions>
                    <div class="flex justify-between items-center w-full">
                         <a v-if="ctaUrlOf(n)" :href="ctaUrlOf(n)" class="text-xs font-semibold text-brand-primary-600">
                             {{ ctaLabelOf(n) }}
                         </a>
                         <button 
                            @click="toggleRead(n)" 
                            class="text-xs text-neutral-500 hover:text-neutral-800"
                        >
                            {{ n.read_at ? 'Tandai belum dibaca' : 'Tandai dibaca' }}
                        </button>
                    </div>
                </template>
            </DataCard>
        </div>
      </div>

      <div class="mt-4 flex justify-between items-center text-sm text-neutral-600">
        <div>Menampilkan {{ filtered.length }} dari {{ items.total }}</div>
        <div class="space-x-2">
          <Link v-if="items.prev_page_url" :href="items.prev_page_url" class="px-3 py-1 border rounded">Prev</Link>
          <Link v-if="items.next_page_url" :href="items.next_page_url" class="px-3 py-1 border rounded">Next</Link>
        </div>
      </div>
    </CardContainer>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import CardContainer from '@/Components/UI/CardContainer.vue';
import Badge from '@/Components/UI/Badge.vue';
import PrimaryButton from '@/Components/UI/PrimaryButton.vue';
import SecondaryButton from '@/Components/UI/SecondaryButton.vue';
import IconButton from '@/Components/UI/IconButton.vue';
import DataCard from '@/Components/Mobile/DataCard.vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { ref, reactive, computed, onMounted, watchEffect } from 'vue';
const page = usePage();
const items = page.props.items || { data: page.props.notifications || [], total: (page.props.notifications || []).length };
const tab = ref(page.props.filters?.category || 'all');
const search = ref(page.props.filters?.search || '');
const dateStart = ref(page.props.filters?.date_start || '');
const dateEnd = ref(page.props.filters?.date_end || '');
const toast = reactive({ show:false, message:'', type:'info' });

function setTab(t){ tab.value = t; }
function tabClass(t){
  const base = 'px-3 py-1.5 rounded border transition-colors';
  return [base, tab.value===t ? 'bg-brand-primary-50 text-brand-primary-800 border-brand-primary-600 shadow-sm' : 'bg-neutral-100 text-neutral-700 border-neutral-300'].join(' ');
}
function relativeTime(s){
  if (!s) return '';
  const d = new Date(s);
  const now = new Date();
  const diff = Math.floor((now.getTime() - d.getTime()) / 1000);
  if (diff < 60) return `${diff} detik yang lalu`;
  const m = Math.floor(diff / 60);
  if (m < 60) return `${m} menit yang lalu`;
  const h = Math.floor(m / 60);
  if (h < 24) return `${h} jam yang lalu`;
  return d.toLocaleString('id-ID', { dateStyle: 'medium', timeStyle: 'short' });
}
function messageOf(n){ return n.message || (n.data && n.data.message) || ''; }
function ctaUrlOf(n){ return n.cta_url || (n.data && n.data.cta_url) || ''; }
function ctaLabelOf(n){ return n.cta_label || (n.data && n.data.cta_label) || 'Lihat'; }
function typeOf(n){ return (n.data && n.data.category) || n.category || 'other'; }
const filtered = computed(() => (items.data||[]).filter(n => (tab.value==='all'||typeOf(n)===tab.value)));
function markAllRead(){
  router.post('/notifications/read-all', {}, { preserveScroll: true, onSuccess(){ toast.message='Semua notifikasi ditandai dibaca'; toast.type='success'; toast.show=true; setTimeout(()=>toast.show=false, 2000); } });
}
function toggleRead(n){
  if (n.read_at) {
    router.post(`/notifications/${n.id}/unread`, {}, { preserveScroll: true, onSuccess(){ toast.message='Notifikasi ditandai belum dibaca'; toast.type='info'; toast.show=true; setTimeout(()=>toast.show=false, 2000); } });
  } else {
    router.post(`/notifications/${n.id}/read`, {}, { preserveScroll: true, onSuccess(){ toast.message='Notifikasi ditandai dibaca'; toast.type='success'; toast.show=true; setTimeout(()=>toast.show=false, 2000); } });
  }
}
function applyFilters(){
  const params = new URLSearchParams();
  if (tab.value && tab.value!=='all') params.set('category', tab.value);
  if (search.value) params.set('search', search.value);
  if (dateStart.value) params.set('date_start', dateStart.value);
  if (dateEnd.value) params.set('date_end', dateEnd.value);
  router.get(`/notifications?${params.toString()}`, {}, { preserveState: true, preserveScroll: true });
}
onMounted(() => {});
</script>
