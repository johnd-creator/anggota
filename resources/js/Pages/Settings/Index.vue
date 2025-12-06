<template>
  <AppLayout page-title="Pengaturan">
    <div class="flex items-center justify-between mb-4">
      <nav class="text-sm text-neutral-600">
        <a href="/dashboard" class="hover:underline">Dashboard</a> / <span>Pengaturan</span>
      </nav>
      <div class="flex items-center gap-2">
        <PrimaryButton @click="saveAll">Simpan Semua</PrimaryButton>
      </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
      <div class="lg:col-span-1">
        <CardContainer padding="lg" shadow="sm">
          <div class="space-y-2">
            <button :class="tabClass('profile')" @click="setTab('profile')" aria-label="Tab Profil">Profil Pengguna</button>
            <button :class="tabClass('notifications')" @click="setTab('notifications')" aria-label="Tab Notifikasi">Preferensi Notifikasi</button>
            <button :class="tabClass('security')" @click="setTab('security')" aria-label="Tab Keamanan">Keamanan Akun</button>
            <button :class="tabClass('integrations')" @click="setTab('integrations')" aria-label="Tab Integrasi">Integrasi/API</button>
            <button :class="tabClass('privacy')" @click="setTab('privacy')" aria-label="Tab Privasi">Privasi & Data</button>
            <button :class="tabClass('language')" @click="setTab('language')" aria-label="Tab Bahasa">Bahasa</button>
          </div>
        </CardContainer>
        <CardContainer padding="lg" shadow="sm" class="mt-4">
          <div class="text-sm font-semibold text-neutral-900 mb-2">Quick Actions</div>
          <div class="space-y-2">
            <SecondaryButton class="w-full" @click="forceLogout">Force Logout</SecondaryButton>
            <SecondaryButton class="w-full" @click="openRunbook('backup')">Runbook Backup & DR</SecondaryButton>
            <SecondaryButton class="w-full" @click="openRunbook('launch')">Launch Checklist</SecondaryButton>
            <SecondaryButton class="w-full" @click="openRunbook('security')">Security Review</SecondaryButton>
          </div>
        </CardContainer>
      </div>

      <div class="lg:col-span-3 space-y-6">
        <CardContainer padding="lg" shadow="sm" v-show="tab==='profile'">
          <template #header>
            <div class="flex items-center justify-between">
              <div>
                <h3 class="text-lg font-semibold text-neutral-900">Profil Pengguna</h3>
                <p class="text-sm text-neutral-600">Informasi akun saat ini</p>
              </div>
            </div>
          </template>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <InputField v-model="form.name" label="Nama" aria-label="Nama" />
            <InputField v-model="form.email" label="Email" aria-label="Email" />
          </div>
        </CardContainer>

        <CardContainer padding="lg" shadow="sm" v-show="tab==='notifications'">
          <template #header>
            <div class="flex items-center justify-between">
              <div>
                <h3 class="text-lg font-semibold text-neutral-900">Preferensi Notifikasi</h3>
                <p class="text-sm text-neutral-600">Perubahan berlaku maksimal 5 menit</p>
              </div>
            </div>
          </template>
          <div class="space-y-4">
            <div class="text-xs text-neutral-600">Kategori</div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div v-for="c in categories" :key="c.key" class="border rounded-lg p-3">
                <div class="font-semibold text-sm mb-2">{{ c.label }}</div>
                <div class="flex items-center gap-4">
                  <div class="flex items-center gap-2"><span class="text-xs">Email</span><ToggleSwitch v-model="prefs[c.key].email" aria-label="Toggle Email"/></div>
                  <div class="flex items-center gap-2"><span class="text-xs">In-App</span><ToggleSwitch v-model="prefs[c.key].inapp" aria-label="Toggle In-App"/></div>
                  <div class="flex items-center gap-2"><span class="text-xs">WA</span><ToggleSwitch v-model="prefs[c.key].wa" aria-label="Toggle WA"/></div>
                </div>
              </div>
            </div>
            <div class="flex items-center gap-3">
              <ToggleSwitch v-model="digestDaily" aria-label="Digest Harian" />
              <span class="text-xs text-neutral-600">Ringkasan harian via email</span>
              <PrimaryButton class="ml-auto" @click="savePrefs">Simpan</PrimaryButton>
            </div>
            <div class="text-xs text-neutral-500">Terakhir diubah: {{ prefsUpdatedAt || '-' }}</div>
          </div>
        </CardContainer>

        <CardContainer padding="lg" shadow="sm" v-show="tab==='security'">
          <template #header>
            <div class="flex items-center justify-between">
              <div>
                <h3 class="text-lg font-semibold text-neutral-900">Keamanan Akun</h3>
                <p class="text-sm text-neutral-600">Kelola sesi dan opsi keamanan</p>
              </div>
              <AlertBanner type="warning" message="Jaga kerahasiaan akun Anda." />
            </div>
          </template>
          <div class="space-y-4">
            <div class="text-sm">Sesi Aktif: Request ID {{ requestId }}</div>
            <div class="flex gap-3">
              <SecondaryButton @click="forceLogout">Force Logout</SecondaryButton>
              <SecondaryButton :disabled="true">Reset Password Darurat (Nonaktif)</SecondaryButton>
              <ToggleSwitch v-model="mfa" aria-label="Toggle MFA" />
              <span class="text-xs text-neutral-600">MFA (stub)</span>
            </div>
            <div v-if="isSuperAdmin" class="mt-4">
              <div class="text-sm font-semibold mb-2">Sesi Aktif Pengguna</div>
              <div v-if="!sessions.length" class="text-xs text-neutral-600">Tidak ada sesi.</div>
              <div v-else class="space-y-2">
                <div v-for="s in sessions" :key="s.id" class="flex items-center justify-between text-sm">
                  <div>{{ s.name }} • {{ s.email }} • {{ s.ip }} • {{ s.last_activity }}</div>
                  <SecondaryButton @click="revoke(s)">Force Logout</SecondaryButton>
                </div>
                <div class="text-xs"><a href="/admin/sessions" class="text-brand-primary-700">Lihat semua sesi</a></div>
              </div>
            </div>
          </div>
        </CardContainer>

        <CardContainer padding="lg" shadow="sm" v-show="tab==='integrations'">
          <template #header>
            <div class="flex items-center justify-between">
              <div>
                <h3 class="text-lg font-semibold text-neutral-900">Integrasi & API Tokens</h3>
                <p class="text-sm text-neutral-600">Kelola token akses</p>
              </div>
              <AlertBanner type="danger" message="Jaga kerahasiaan token" />
            </div>
          </template>
          <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-neutral-200">
              <thead class="bg-neutral-50">
                <tr>
                  <th class="px-4 py-2 text-left text-xs text-neutral-500">Label</th>
                  <th class="px-4 py-2 text-left text-xs text-neutral-500">Status</th>
                  <th class="px-4 py-2 text-left text-xs text-neutral-500">Kadaluarsa</th>
                  <th class="px-4 py-2 text-left text-xs text-neutral-500">Last Used</th>
                  <th class="px-4 py-2 text-right text-xs text-neutral-500">Aksi</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-neutral-200 bg-white">
                <tr v-for="t in tokens" :key="t.id">
                  <td class="px-4 py-2 text-sm">{{ t.label }}</td>
                  <td class="px-4 py-2 text-sm"><Badge :variant="t.active ? 'success' : 'neutral'">{{ t.active ? 'Active' : 'Inactive' }}</Badge></td>
                  <td class="px-4 py-2 text-sm">{{ t.expires_at || '-' }}</td>
                  <td class="px-4 py-2 text-sm">{{ t.last_used || '-' }}</td>
                  <td class="px-4 py-2 text-right text-sm">
                    <SecondaryButton @click="confirm('regenerate', t)">Regenerate</SecondaryButton>
                    <SecondaryButton class="ml-2" @click="confirm('deactivate', t)">Deactivate</SecondaryButton>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
          <ModalBase v-model:show="modalOpen" title="Konfirmasi" size="md">
            <div class="text-sm text-neutral-700">Aksi: {{ modalAction }} untuk token "{{ modalToken?.label }}"?</div>
            <template #footer>
              <div class="flex justify-end gap-3">
                <SecondaryButton @click="modalOpen=false">Batal</SecondaryButton>
                <PrimaryButton @click="doTokenAction">Lanjut</PrimaryButton>
              </div>
            </template>
          </ModalBase>
        </CardContainer>

        <CardContainer padding="lg" shadow="sm" v-show="tab==='privacy'">
          <template #header>
            <div class="flex items-center justify-between">
              <div>
                <h3 class="text-lg font-semibold text-neutral-900">Privasi & Kontrol Data</h3>
                <p class="text-sm text-neutral-600">SLA penanganan: maks 7 hari</p>
              </div>
            </div>
          </template>
          <div class="space-y-3 text-sm">
            <div>Data disimpan: nama, email, unit, status, riwayat mutasi, dokumen terkait.</div>
            <div><a href="/docs/release/launch-checklist" class="text-brand-primary-700 underline">Kebijakan Privasi (lihat dokumen)</a></div>
            <div class="flex gap-3">
              <SecondaryButton @click="openPrivacy('export')">Request Data Export</SecondaryButton>
              <SecondaryButton @click="openPrivacy('delete')">Ajukan Penghapusan Data</SecondaryButton>
            </div>
            <ModalBase v-model:show="privacyOpen" title="Konfirmasi" size="md">
              <div class="text-sm text-neutral-700">Aksi: {{ privacyAction==='export'?'Export Data':'Penghapusan Data' }}. Lanjutkan?</div>
              <template #footer>
                <div class="flex justify-end gap-3">
                  <SecondaryButton @click="privacyOpen=false">Batal</SecondaryButton>
                  <PrimaryButton @click="doPrivacyAction">Kirim</PrimaryButton>
                </div>
              </template>
            </ModalBase>
          </div>
        </CardContainer>

        <CardContainer padding="lg" shadow="sm" v-show="tab==='language'">
          <template #header>
            <div class="flex items-center justify-between">
              <div>
                <h3 class="text-lg font-semibold text-neutral-900">Bahasa</h3>
                <p class="text-sm text-neutral-600">Pilih bahasa antarmuka</p>
              </div>
            </div>
          </template>
          <div class="flex items-center gap-3">
            <SelectField v-model="lang" :options="langOptions" aria-label="Pilih Bahasa" />
            <Badge variant="brand">Preview: {{ lang==='id'?'Bahasa Indonesia':'English' }}</Badge>
          </div>
        </CardContainer>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import CardContainer from '@/Components/UI/CardContainer.vue';
import InputField from '@/Components/UI/InputField.vue';
import SelectField from '@/Components/UI/SelectField.vue';
import ToggleSwitch from '@/Components/UI/ToggleSwitch.vue';
import PrimaryButton from '@/Components/UI/PrimaryButton.vue';
import SecondaryButton from '@/Components/UI/SecondaryButton.vue';
import AlertBanner from '@/Components/UI/AlertBanner.vue';
import Badge from '@/Components/UI/Badge.vue';
import ModalBase from '@/Components/UI/ModalBase.vue';
import { usePage, router } from '@inertiajs/vue3';
import { reactive, ref } from 'vue';

const page = usePage();
const form = reactive({ name: page.props.auth.user?.name || '', email: page.props.auth.user?.email || '' });
const tab = ref('profile');
function setTab(t){ tab.value = t; }
function tabClass(t){ return ['w-full text-left px-3 py-2 rounded', tab.value===t ? 'bg-brand-primary-50 text-brand-primary-700' : 'bg-neutral-100 text-neutral-700 hover:bg-neutral-200'].join(' '); }

const categories = [
  { key:'mutations', label:'Mutasi' },
  { key:'updates', label:'Perubahan Data' },
  { key:'onboarding', label:'Onboarding' },
  { key:'security', label:'Keamanan' },
];
const prefs = reactive({
  mutations: { email:true, inapp:true, wa:false },
  updates: { email:true, inapp:true, wa:false },
  onboarding: { email:true, inapp:true, wa:false },
  security: { email:true, inapp:true, wa:false },
});
const digestDaily = ref(false);
const prefsUpdatedAt = ref('');
if (page.props.notification_prefs) {
  const np = page.props.notification_prefs;
  if (np.channels) Object.assign(prefs, np.channels);
  digestDaily.value = !!np.digest_daily;
  prefsUpdatedAt.value = np.updated_at || '';
}
async function savePrefs(){ try{ const res = await fetch('/settings/notifications', { method:'PATCH', headers:{ 'Content-Type':'application/json' }, body: JSON.stringify({ channels: prefs, digest_daily: digestDaily.value }) }); if (res.ok){ const j = await res.json(); prefsUpdatedAt.value = j.updated_at || new Date().toISOString(); } }catch(e){} }

const requestId = page.props.request_id || 'unknown';
const mfa = ref(false);
function forceLogout(){ router.post('/logout'); }
function saveAll(){ /* stub save */ }

const tokens = ref([
  { id: 1, label:'HRIS Sync', active:true, expires_at:'-', last_used:'-' },
  { id: 2, label:'Payroll Export', active:false, expires_at:'2026-01-01', last_used:'-' },
]);
const modalOpen = ref(false); const modalAction = ref(''); const modalToken = ref(null);
function confirm(action, token){ modalAction.value = action; modalToken.value = token; modalOpen.value = true; }
function doTokenAction(){ modalOpen.value=false; /* stub */ }

const privacyOpen = ref(false); const privacyAction = ref('');
function openPrivacy(a){ privacyAction.value = a; privacyOpen.value = true; }
function doPrivacyAction(){ privacyOpen.value=false; if (privacyAction.value==='export') router.post('/member/data/export-request'); else router.post('/member/data/delete-request'); }

const langOptions = [ { label:'Bahasa Indonesia', value:'id' }, { label:'English', value:'en' } ];
const lang = ref(localStorage.getItem('lang') || 'id');
function openRunbook(which){
  if (which==='backup') window.location.href = '/docs/ops/backup-dr';
  else if (which==='launch') window.location.href = '/docs/release/launch-checklist';
  else if (which==='security') window.location.href = '/docs/security/review';
}

const isSuperAdmin = (page.props.auth?.user?.role?.name||'') === 'super_admin';
const sessions = ref([]);
if (isSuperAdmin) {
  fetch('/admin/sessions').then(r => r.json().catch(()=>null)).then(data => { if (data && data.sessions) sessions.value = data.sessions.data || []; }).catch(()=>{});
}
function revoke(s){ router.post('/admin/sessions/revoke', { session_id: s.session_id }); }

</script>
