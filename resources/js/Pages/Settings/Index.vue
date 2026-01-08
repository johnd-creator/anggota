<template>
  <AppLayout page-title="Pengaturan">
    <div class="flex items-center justify-between mb-4">
      <nav class="text-sm text-neutral-600">
        <a href="/dashboard" class="hover:underline">Dashboard</a> / <span>Pengaturan</span>
      </nav>
      <!-- "Simpan Semua" stub hidden -->
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
      <div class="lg:col-span-1">
        <CardContainer padding="lg" shadow="sm">
          <div class="space-y-2">
            <button :class="tabClass('profile')" @click="setTab('profile')" aria-label="Tab Profil">Profil Pengguna</button>
            <button :class="tabClass('notifications')" @click="setTab('notifications')" aria-label="Tab Notifikasi">Preferensi Notifikasi</button>
            <button :class="tabClass('security')" @click="setTab('security')" aria-label="Tab Keamanan">Keamanan Akun</button>
            <button :class="tabClass('privacy')" @click="setTab('privacy')" aria-label="Tab Privasi">Privasi & Data</button>
            <!-- Integrations & Language tabs hidden -->
          </div>
        </CardContainer>
        <CardContainer v-if="canQuickActions" padding="lg" shadow="sm" class="mt-4">
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
          <div class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <InputField v-model="form.name" label="Nama" aria-label="Nama" />
              <InputField v-model="form.email" label="Email" aria-label="Email" :disabled="true" helper="Email mengikuti akun pengguna dan tidak dapat diubah di sini." />
            </div>
            <div class="flex items-center justify-end gap-3 border-t pt-4">
              <span v-if="profileMessage" :class="profileSuccess ? 'text-green-600' : 'text-red-500'" class="text-sm font-medium transition-opacity duration-500">{{ profileMessage }}</span>
              <PrimaryButton @click="saveProfile" :disabled="profileLoading">Simpan Profil</PrimaryButton>
            </div>
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
            </div>
            <div class="flex items-center gap-3 mt-4 border-t pt-4">
              <ToggleSwitch v-model="lettersEnabled" aria-label="Notifikasi Surat" />
              <span class="text-sm text-neutral-700">Notifikasi Surat (persetujuan, status, dll)</span>
            </div>
            <div class="flex justify-end mt-4">
              <PrimaryButton @click="savePrefs">Simpan</PrimaryButton>
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
            <AlertBanner
              v-if="sessionMessage"
              :type="sessionMessageType"
              :message="sessionMessage"
              dismissible
              class="mb-2"
              @dismiss="sessionMessage = ''"
            />
            <div class="text-sm">Sesi Aktif: Request ID {{ requestId }}</div>
            <div class="flex gap-3">
              <SecondaryButton @click="forceLogout">Force Logout</SecondaryButton>
              <SecondaryButton @click="openPasswordModal">Reset Password</SecondaryButton>
              <!-- MFA switch hidden -->
            </div>

            <div class="mt-6 border-t pt-4">
              <div class="flex items-center justify-between mb-3">
                <h4 class="text-sm font-semibold text-neutral-900">Sesi Saya</h4>
                <SecondaryButton @click="revokeOthers" class="text-xs">Logout Semua Device Lain</SecondaryButton>
              </div>
              <div v-if="mySessions.length === 0" class="text-xs text-neutral-500 italic">Memuat sesi...</div>
              <div v-else class="space-y-2">
                 <div v-for="s in mySessions" :key="s.id" class="flex items-center justify-between p-2 border rounded bg-neutral-50">
                    <div class="text-sm">
                       <span class="font-medium">{{ s.ip_address }}</span>
                       <span class="text-neutral-500 mx-2">•</span>
                       <span class="text-xs text-neutral-600 truncate max-w-[200px] inline-block align-bottom" :title="s.user_agent">{{ s.user_agent }}</span>
                       <div class="text-xs text-neutral-500 mt-1">Aktif: {{ s.last_activity }}</div>
                    </div>
                    <Badge v-if="s.is_current_device" variant="success">Current</Badge>
                 </div>
              </div>
            </div>

            <div v-if="isSuperAdmin" class="mt-6 border-t pt-4">
              <div class="text-sm font-semibold mb-2">Semua Sesi Pengguna (Admin View)</div>
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

        <!-- Integrations Tab hidden -->

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
            <div><a href="/help" class="text-brand-primary-700 underline">Kebijakan Privasi (lihat di Help Center)</a></div>
            
            <div v-if="isMember" class="flex gap-3">
              <SecondaryButton @click="openPrivacy('export')">Request Data Export</SecondaryButton>
              <SecondaryButton @click="openPrivacy('delete')">Ajukan Penghapusan Data</SecondaryButton>
            </div>
            <div v-else class="text-neutral-500 italic">
               Fitur ini tersedia untuk anggota melalui portal anggota.
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

        <!-- Language Tab hidden -->
      </div>
    </div>

    <ModalBase v-model:show="passwordModalOpen" title="Reset Password" size="md">
      <div class="space-y-3">
        <InputField v-model="passwordForm.current_password" type="password" label="Password Saat Ini" />
        <InputField v-model="passwordForm.password" type="password" label="Password Baru" />
        <InputField v-model="passwordForm.password_confirmation" type="password" label="Konfirmasi Password Baru" />
        <p class="text-xs text-neutral-500">Minimal 8 karakter. Hindari password yang mudah ditebak.</p>
        <p v-if="passwordMessage" :class="passwordSuccess ? 'text-green-600' : 'text-red-500'" class="text-sm font-medium">{{ passwordMessage }}</p>
      </div>
      <template #footer>
        <div class="flex justify-end gap-3">
          <SecondaryButton @click="passwordModalOpen = false">Batal</SecondaryButton>
          <PrimaryButton @click="submitPasswordReset" :disabled="passwordLoading">
            <span v-if="passwordLoading">Menyimpan...</span>
            <span v-else>Simpan Password</span>
          </PrimaryButton>
        </div>
      </template>
    </ModalBase>
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
import { reactive, ref, computed, watch, onMounted } from 'vue';

const page = usePage();
const profileDefaults = page.props.profile || { name: page.props.auth.user?.name || '', email: page.props.auth.user?.email || '' };
const form = reactive({ name: profileDefaults.name || '', email: profileDefaults.email || '' });
const tab = ref('profile');
function setTab(t){ tab.value = t; }
function tabClass(t){ return ['w-full text-left px-3 py-2 rounded', tab.value===t ? 'bg-brand-primary-50 text-brand-primary-700' : 'bg-neutral-100 text-neutral-700 hover:bg-neutral-200'].join(' '); }
const csrfToken = computed(() => page.props?.csrf_token || '');

const categories = [
  { key:'announcements', label:'Pengumuman Penting', desc: 'Info resmi' },
  { key:'mutations', label:'Mutasi Anggota', desc: 'Status mutasi' },
  { key:'updates', label:'Perubahan Data', desc: 'Update profil' },
  { key:'onboarding', label:'Onboarding', desc: 'Anggota baru' },
  { key:'dues', label:'Iuran & Keuangan', desc: 'Tagihan & status' },
  { key:'reports', label:'Laporan', desc: 'Statistik mingguan' },
  { key:'finance', label:'Keuangan (Admin)', desc: 'Approval ledger' },
  { key:'security', label:'Keamanan', desc: 'Login alert' },
];
const prefs = reactive({
  announcements: { email:true, inapp:true, wa:false },
  mutations: { email:true, inapp:true, wa:false },
  updates: { email:true, inapp:true, wa:false },
  onboarding: { email:true, inapp:true, wa:false },
  dues: { email:true, inapp:true, wa:false },
  reports: { email:true, inapp:true, wa:false },
  finance: { email:true, inapp:true, wa:false },
  security: { email:true, inapp:true, wa:false },
});
const digestDaily = ref(false);
const lettersEnabled = ref(true);
const prefsUpdatedAt = ref('');
if (page.props.notification_prefs) {
  const np = page.props.notification_prefs;
  if (np.channels) {
    Object.assign(prefs, np.channels);
    lettersEnabled.value = np.channels.letters !== false;
  }
  digestDaily.value = !!np.digest_daily;
  prefsUpdatedAt.value = np.updated_at || '';
}
async function savePrefs(){
  try {
    const res = await fetch('/settings/notifications', {
      method:'PATCH',
      headers:{ 'Content-Type':'application/json', 'X-CSRF-TOKEN': csrfToken.value },
      body: JSON.stringify({
        channels: { ...prefs, letters: lettersEnabled.value },
        digest_daily: digestDaily.value
      })
    });
    if (res.ok) {
      const j = await res.json();
      prefsUpdatedAt.value = j.updated_at || new Date().toISOString();
    }
  } catch(e) {}
}

const requestId = page.props.request_id || 'unknown';
const mfa = ref(false);
function forceLogout(){ router.post('/logout'); }

const passwordModalOpen = ref(false);
const passwordForm = reactive({
  current_password: '',
  password: '',
  password_confirmation: '',
});
const passwordMessage = ref('');
const passwordSuccess = ref(false);
const passwordLoading = ref(false);

function openPasswordModal() {
  passwordMessage.value = '';
  passwordSuccess.value = false;
  passwordForm.current_password = '';
  passwordForm.password = '';
  passwordForm.password_confirmation = '';
  passwordModalOpen.value = true;
}

async function submitPasswordReset() {
  passwordLoading.value = true;
  passwordMessage.value = '';
  passwordSuccess.value = false;
  try {
    const res = await fetch('/settings/password', {
      method: 'PATCH',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken.value },
      body: JSON.stringify({
        current_password: passwordForm.current_password,
        password: passwordForm.password,
        password_confirmation: passwordForm.password_confirmation,
      })
    });
    const data = await res.json().catch(() => ({}));
    if (res.ok) {
      passwordSuccess.value = true;
      passwordMessage.value = 'Password berhasil diperbarui.';
      passwordModalOpen.value = false;
    } else {
      passwordMessage.value = data.message || 'Gagal memperbarui password.';
      passwordSuccess.value = false;
    }
  } catch (e) {
    passwordMessage.value = 'Gagal memperbarui password.';
    passwordSuccess.value = false;
  } finally {
    passwordLoading.value = false;
  }
}

const mySessions = ref([]);
const mySessionsLoading = ref(false);

async function fetchMySessions(){
  if(mySessionsLoading.value) return;
  mySessionsLoading.value = true;
  try {
    const r = await fetch('/settings/sessions');
    if(r.ok) {
        const d = await r.json();
        mySessions.value = d.sessions || [];
    }
  } catch(e){} finally { mySessionsLoading.value=false; }
}

async function revokeOthers(){
  if(!confirm('Logout semua device lain? Sesi ini tetap aktif.')) return;
  try {
    const r = await fetch('/settings/sessions/revoke-others', {
       method:'POST',
       headers:{ 'X-CSRF-TOKEN': csrfToken.value, 'Content-Type':'application/json' }
    });
    if(r.ok) {
        sessionMessageType.value = 'success';
        sessionMessage.value = 'Sesi lain berhasil dikeluarkan.';
        fetchMySessions();
    } else {
        sessionMessageType.value = 'error';
        sessionMessage.value = 'Gagal mengeluarkan sesi lain.';
    }
  } catch(e){}
}

watch(tab, (v) => { if(v==='security') fetchMySessions(); });
onMounted(() => { if(tab.value==='security') fetchMySessions(); });

const privacyOpen = ref(false); const privacyAction = ref('');
function openPrivacy(a){ privacyAction.value = a; privacyOpen.value = true; }
function doPrivacyAction(){ privacyOpen.value=false; if (privacyAction.value==='export') router.post('/member/data/export-request'); else router.post('/member/data/delete-request'); }

function openRunbook(which){
  if (which==='backup') window.location.href = '/docs/ops/backup-dr';
  else if (which==='launch') window.location.href = '/docs/release/launch-checklist';
  else if (which==='security') window.location.href = '/docs/security/review';
}

const canQuickActions = !!page.props.can_quick_actions;
const isSuperAdmin = (page.props.auth?.user?.role?.name||'') === 'super_admin';
const sessions = ref([]);
if (isSuperAdmin) {
  fetch('/admin/sessions').then(r => r.json().catch(()=>null)).then(data => { if (data && data.sessions) sessions.value = data.sessions.data || []; }).catch(()=>{});
}
function revoke(s){ router.post('/admin/sessions/revoke', { session_id: s.session_id }); }

const sessionMessage = ref('');
const sessionMessageType = ref('success');

// Check if user is effectively a member (has member_id or role anggota)
const isMember = computed(() => !!page.props.auth?.user?.is_member || (page.props.auth?.user?.role?.name === 'anggota'));

const profileMessage = ref('');
const profileSuccess = ref(false);
const profileLoading = ref(false);

async function saveProfile(){
  profileLoading.value = true;
  profileMessage.value = '';
  try {
    const res = await fetch('/settings/profile', {
      method:'PATCH',
      headers:{ 'Content-Type':'application/json', 'Accept':'application/json', 'X-CSRF-TOKEN': csrfToken.value },
      body: JSON.stringify({ name: form.name })
    });
    const d = await res.json();
    if(res.ok){
      profileMessage.value = 'Tersimpan';
      profileSuccess.value = true;
      router.reload(); // Refresh layout user name
    } else {
      profileMessage.value = d.message || 'Gagal menyimpan';
      profileSuccess.value = false;
    }
  } catch(e){
    profileMessage.value = 'Gagal menyimpan';
    profileSuccess.value = false;
  } finally {
    profileLoading.value = false;
    setTimeout(()=>{ profileMessage.value=''; }, 3000);
  }
}
</script>
