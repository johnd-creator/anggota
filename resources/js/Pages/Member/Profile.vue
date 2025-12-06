<template>
  <AppLayout page-title="Profil Anggota">
    <AlertBanner type="info" title="Privasi" message="Data Anda digunakan untuk keperluan keanggotaan. Dengan melanjutkan, Anda menyetujui pengolahan data sesuai kebijakan privasi." />
    <CardContainer padding="lg" shadow="sm">
      <div v-if="member" class="space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center gap-4">
          <img :src="member?.photo_path ? '/storage/' + member.photo_path : `https://ui-avatars.com/api/?name=${member?.full_name || 'A'}&background=random`" class="h-16 w-16 rounded-full object-cover object-center" />
          <div class="flex-1">
            <h2 class="text-xl font-semibold text-neutral-900">{{ member?.full_name }}</h2>
            <div class="flex items-center gap-2">
              <Badge variant="brand">{{ member?.nra }}</Badge>
              <Badge :variant="statusVariant(member?.status)">{{ member?.status }}</Badge>
              <span class="text-xs text-neutral-600">{{ member?.unit?.name }}</span>
            </div>
            <div class="mt-3">
              <div class="flex items-center justify-between text-xs text-neutral-600 mb-1"><span>Kelengkapan Profil</span><span>{{ completeness }}%</span></div>
              <div class="h-2 bg-neutral-200 rounded overflow-hidden"><div class="h-2 bg-brand-primary-600" :style="{width: completeness+'%'}"></div></div>
            </div>
          </div>
          <div>
            <PrimaryButton @click="openEdit">Lengkapi Profil</PrimaryButton>
          </div>
        </div>

        <div class="border-b border-neutral-200 mb-4 flex gap-4 text-sm overflow-x-auto whitespace-nowrap">
          <button :class="tab==='profil' ? 'text-neutral-900 border-b-2 border-brand-primary-600' : 'text-neutral-500'" @click="tab='profil'">Data Pribadi</button>
          <button :class="tab==='riwayat' ? 'text-neutral-900 border-b-2 border-brand-primary-600' : 'text-neutral-500'" @click="tab='riwayat'">Riwayat Mutasi & Status</button>
          <button :class="tab==='dokumen' ? 'text-neutral-900 border-b-2 border-brand-primary-600' : 'text-neutral-500'" @click="tab='dokumen'">Checklist Dokumen</button>
          <button :class="tab==='privasi' ? 'text-neutral-900 border-b-2 border-brand-primary-600' : 'text-neutral-500'" @click="tab='privasi'">Privasi & Data</button>
        </div>

        <div v-show="tab==='profil'" class="space-y-4">
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="text-xs text-neutral-600">Alamat</label>
              <input v-model="form.address" class="mt-1 rounded border px-3 py-2 text-sm w-full" placeholder="Alamat" />
            </div>
            <div>
              <label class="text-xs text-neutral-600">Nomor Telepon</label>
              <input v-model="form.phone" class="mt-1 rounded border px-3 py-2 text-sm w-full" placeholder="Nomor Telepon" />
              <div v-if="errors.phone" class="text-xs text-status-danger mt-1">{{ errors.phone }}</div>
            </div>
          </div>
          <div>
            <h4 class="text-sm font-semibold text-neutral-700 mb-2">Riwayat Perubahan</h4>
            <div v-if="updateRequests.length" class="border rounded-lg p-3 flex items-center justify-between">
              <div class="text-sm">Terakhir: {{ updateRequests[0].status }} • {{ updateRequests[0].created_at }}</div>
              <Badge :variant="updateRequests[0].status==='approved'?'success':(updateRequests[0].status==='rejected'?'danger':'warning')">{{ updateRequests[0].status }}</Badge>
            </div>
            <div v-else class="text-xs text-neutral-500">Belum ada riwayat perubahan data.</div>
          </div>
          <div class="flex justify-end"><PrimaryButton @click="submitUpdate">Kirim Permintaan Update</PrimaryButton></div>
        </div>

        <div v-show="tab==='riwayat'" class="space-y-3">
          <div class="flex items-center gap-3 mb-2">
            <input v-model="historySearch" placeholder="Cari event" class="rounded border px-3 py-2 text-sm" />
            <select v-model="historyYear" class="rounded border px-3 py-2 text-sm">
              <option value="">Semua Tahun</option>
              <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
            </select>
          </div>
          <div v-for="h in filteredHistory" :key="h.id" class="flex items-start gap-3">
            <div :class="iconClass(h)" class="w-3 h-3 rounded-full mt-1.5"></div>
            <div>
              <div class="text-sm text-neutral-900">{{ h.date }} • {{ h.new_status }}</div>
              <div class="text-xs text-neutral-600">{{ h.notes }}</div>
            </div>
          </div>
        </div>

        <div v-show="tab==='dokumen'" class="space-y-2">
          <div class="flex items-center gap-2"><Badge :variant="hasDoc('KTP') ? 'success':'danger'">KTP</Badge><span class="text-xs text-neutral-600">{{ hasDoc('KTP') ? 'Sudah' : 'Belum' }}</span></div>
          <div class="flex items-center gap-2"><Badge :variant="hasDoc('KK') ? 'success':'danger'">KK</Badge><span class="text-xs text-neutral-600">{{ hasDoc('KK') ? 'Sudah' : 'Belum' }}</span></div>
        </div>

        <div v-show="tab==='privasi'" class="space-y-3">
          <div class="text-sm font-semibold text-neutral-900">Privasi & Data</div>
          <div class="text-xs text-neutral-600">Anda dapat meminta salinan data atau penghapusan data tertentu.</div>
          <div class="flex items-center gap-3">
            <SecondaryButton @click="requestExport">Minta Export Data</SecondaryButton>
            <SecondaryButton @click="requestDelete">Minta Penghapusan Data</SecondaryButton>
          </div>
        </div>

        <ModalBase v-if="editOpen" @close="editOpen=false" title="Lengkapi Profil">
          <div class="space-y-3">
            <div>
              <label class="text-xs text-neutral-600">Alamat</label>
              <input v-model="form.address" class="mt-1 rounded border px-3 py-2 text-sm w-full" placeholder="Alamat" />
            </div>
            <div>
              <label class="text-xs text-neutral-600">Nomor Telepon</label>
              <input v-model="form.phone" class="mt-1 rounded border px-3 py-2 text-sm w-full" placeholder="Nomor Telepon" />
              <div v-if="errors.phone" class="text-xs text-status-danger mt-1">{{ errors.phone }}</div>
            </div>
            <div class="flex justify-end"><PrimaryButton @click="submitUpdate">Simpan</PrimaryButton></div>
          </div>
        </ModalBase>
      </div>
      <div v-else class="text-neutral-600">Data belum lengkap. Silakan hubungi Admin Unit untuk melengkapi onboarding.</div>
    </CardContainer>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import CardContainer from '@/Components/UI/CardContainer.vue';
import Badge from '@/Components/UI/Badge.vue';
import PrimaryButton from '@/Components/UI/PrimaryButton.vue';
import SecondaryButton from '@/Components/UI/SecondaryButton.vue';
import AlertBanner from '@/Components/UI/AlertBanner.vue';
import ModalBase from '@/Components/UI/ModalBase.vue';
import { usePage, router } from '@inertiajs/vue3';
import { reactive, ref, computed } from 'vue';

const page = usePage();
const member = page.props.member;
const updateRequests = page.props.updateRequests || [];
const tab = ref('profil');
try {
  const q = (page.url.split('?')[1] || '').split('&').reduce((a,p)=>{ const [k,v] = p.split('='); if (k) a[k]=decodeURIComponent(v||''); return a; }, {});
  if (q.tab) tab.value = q.tab;
} catch(e) {}
const form = reactive({ address: member?.address || '', phone: member?.phone || '' });
const errors = reactive({ phone:'' });
const editOpen = ref(false);
function openEdit(){ editOpen.value = true; }
function submitUpdate(){ if (form.phone && !/^\+?[1-9]\d{7,14}$/.test(form.phone)) { errors.phone='Format nomor tidak valid'; return; } else { errors.phone=''; } router.post('/member/portal/request-update', form); }
function hasDoc(name){ return (member?.documents || []).some(d => (d.original_name || d.type || '').toLowerCase().includes(name.toLowerCase())); }
function statusVariant(s){ switch (s) { case 'aktif': return 'success'; case 'cuti': return 'warning'; case 'suspended': return 'danger'; case 'resign': return 'neutral'; case 'pensiun': return 'neutral'; default: return 'neutral'; } }
function requestExport(){ router.post('/member/data/export-request'); }
function requestDelete(){ router.post('/member/data/delete-request'); }
const completeness = computed(() => { if (!member) return 0; const items = [member.address, member.phone, member.photo_path, (member.documents||[]).length>0]; const filled = items.filter(Boolean).length; return Math.round((filled/items.length)*100); });
const years = computed(() => { const ys = new Set((member?.status_logs||[]).map(h => new Date(h.date).getFullYear())); return Array.from(ys).sort(); });
const historySearch = ref('');
const historyYear = ref('');
const filteredHistory = computed(() => { let arr = member?.status_logs || []; if (historyYear.value) arr = arr.filter(h => new Date(h.date).getFullYear() == historyYear.value); if (historySearch.value) arr = arr.filter(h => (h.new_status+h.notes).toLowerCase().includes(historySearch.value.toLowerCase())); return arr; });
function iconClass(h){ const s = (h.new_status||'').toLowerCase(); if (s.includes('mutasi')) return 'bg-brand-secondary-600'; if (s.includes('cuti')) return 'bg-status-warning-dark'; if (s.includes('suspend')) return 'bg-status-danger-dark'; if (s.includes('aktif')) return 'bg-status-success-dark'; return 'bg-neutral-400'; }
</script>
