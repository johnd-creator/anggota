<template>
  <AppLayout page-title="Member Detail">
    <div class="mb-4 flex items-center justify-end">
      <div class="flex items-center gap-3 flex-wrap">
        <!-- Edit Button - Primary Action -->
        <PrimaryButton v-if="$page.props.auth.user.role?.name!=='pengurus'" @click="router.get(`/admin/members/${member.id}/edit`)" size="sm">
          <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
          </svg>
          Edit
        </PrimaryButton>
        
        <!-- Ubah Status Button - Secondary Action -->
        <button v-if="$page.props.auth.user.role?.name!=='pengurus'" class="inline-flex items-center gap-2 px-4 py-2 bg-amber-500 text-white rounded-lg text-sm font-medium transition-all duration-200 hover:bg-amber-600 hover:scale-105 hover:shadow-md">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
          </svg>
          Ubah Status
        </button>
        
        <!-- Ajukan Mutasi Button - Specialized Action -->
        <button v-if="$page.props.auth.user.role?.name!=='pengurus'" class="inline-flex items-center gap-2 px-4 py-2 bg-teal-600 text-white rounded-lg text-sm font-medium transition-all duration-200 hover:bg-teal-700 hover:scale-105 hover:shadow-md">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/>
          </svg>
          Ajukan Mutasi
        </button>
      </div>
    </div>
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <CardContainer padding="lg" shadow="sm" class="lg:col-span-2">
        <div class="flex items-center gap-4">
          <OptimizedImage
            :src="member.photo_path ? member.photo_path : null"
            :alt="member.full_name || 'Member photo'"
            size="medium"
            class="h-16 w-16 rounded-full"
            loading="eager"
          />
          <div>
            <h2 class="text-xl font-semibold text-neutral-900">{{ $toTitleCase(member.full_name) }}</h2>
            <div class="flex items-center gap-2">
              <Badge variant="brand">{{ member.nra }}</Badge>
              <Badge :variant="statusVariant(member.status)">{{ member.status }}</Badge>
            </div>
          </div>
        </div>
        <div class="mt-6">
          <div class="border-b border-neutral-200 mb-4 flex gap-4 text-sm">
            <button :class="tab==='profil' ? 'text-neutral-900 border-b-2 border-brand-primary-600' : 'text-neutral-500'" @click="tab='profil'">Profil</button>
            <button :class="tab==='dokumen' ? 'text-neutral-900 border-b-2 border-brand-primary-600' : 'text-neutral-500'" @click="tab='dokumen'">Dokumen</button>
            <button :class="tab==='riwayat' ? 'text-neutral-900 border-b-2 border-brand-primary-600' : 'text-neutral-500'" @click="tab='riwayat'">Riwayat</button>
          </div>

          <div v-show="tab==='profil'" class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <p class="text-xs text-neutral-500">Unit</p>
              <p class="text-sm text-neutral-900">{{ member.unit?.name }}</p>
            </div>
            <div>
              <p class="text-xs text-neutral-500">Join Date</p>
              <p class="text-sm text-neutral-900">{{ formatDate(member.join_date) }}</p>
            </div>
            <div v-if="member.company_join_date">
              <p class="text-xs text-neutral-500">Tanggal Gabung Perusahaan</p>
              <p class="text-sm text-neutral-900">{{ formatDate(member.company_join_date) }}</p>
            </div>
            <div v-if="member.birth_date">
              <p class="text-xs text-neutral-500">Birth Date</p>
              <p class="text-sm text-neutral-900">{{ formatDate(member.birth_date) }}</p>
            </div>
            <div>
              <p class="text-xs text-neutral-500">Email</p>
              <p class="text-sm text-neutral-900">{{ member.email }}</p>
            </div>
            <div v-if="member.union_position">
              <p class="text-xs text-neutral-500">Jabatan Serikat</p>
              <p class="text-sm text-neutral-900">{{ member.union_position?.name }}</p>
            </div>
            <div v-if="member.kta_number">
              <p class="text-xs text-neutral-500">KTA Number</p>
              <p class="text-sm text-neutral-900">{{ member.kta_number }}</p>
            </div>
            <div v-if="member.nip">
              <p class="text-xs text-neutral-500">NIP</p>
              <p class="text-sm text-neutral-900">{{ member.nip }}</p>
            </div>
          </div>

          <div v-show="tab==='dokumen'" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4">
            <div v-for="d in member.documents" :key="d.id" class="border rounded-lg p-3">
              <div class="flex items-center gap-2">
                <svg class="w-5 h-5 text-neutral-500" fill="currentColor" viewBox="0 0 20 20"><path d="M4 2h8l4 4v12a2 2 0 01-2 2H4a2 2 0 01-2-2V4a2 2 0 012-2z"/></svg>
                <div class="text-sm text-neutral-900 truncate">{{ d.original_name || d.type }}</div>
              </div>
              <div class="mt-2 text-xs text-neutral-500">{{ d.size ? (d.size/1024).toFixed(1)+' KB' : '' }}</div>
              <div class="mt-3">
                <a :href="'/storage/' + d.path" target="_blank" class="text-brand-primary-600 text-sm">Unduh</a>
              </div>
            </div>
          </div>

          <div v-show="tab==='riwayat'" class="space-y-4">
            <div v-for="h in member.status_logs" :key="h.id" class="flex items-start gap-3">
              <div class="w-2 h-2 rounded-full bg-brand-primary-600 mt-1.5"></div>
              <div>
                <div class="text-sm text-neutral-900">{{ h.date }} • {{ h.new_status }}</div>
                <div class="text-xs text-neutral-600">{{ h.notes }}</div>
              </div>
            </div>
          </div>
        </div>
      </CardContainer>

      <CardContainer padding="lg" shadow="sm">
        <h3 class="text-sm font-semibold text-neutral-700 mb-2">Documents</h3>
        <ul class="space-y-2">
          <li v-for="d in member.documents" :key="d.id" class="flex justify-between items-center">
            <span class="text-sm text-neutral-700">{{ d.original_name || d.type }}</span>
            <a :href="'/storage/' + d.path" target="_blank" class="text-brand-primary-600 text-sm">View</a>
          </li>
        </ul>
      </CardContainer>

      <CardContainer padding="lg" shadow="sm" class="lg:col-span-3">
        <h3 class="text-sm font-semibold text-neutral-700 mb-2">Status History</h3>
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-neutral-200">
            <thead class="bg-neutral-50"><tr><th class="px-4 py-2 text-left text-xs text-neutral-500">Date</th><th class="px-4 py-2 text-left text-xs text-neutral-500">From</th><th class="px-4 py-2 text-left text-xs text-neutral-500">To</th><th class="px-4 py-2 text-left text-xs text-neutral-500">Notes</th></tr></thead>
            <tbody class="divide-y divide-neutral-200 bg-white">
              <tr v-for="h in member.status_logs" :key="h.id">
                <td class="px-4 py-2 text-sm">{{ h.date }}</td>
                <td class="px-4 py-2 text-sm">{{ h.old_status }}</td>
                <td class="px-4 py-2 text-sm">{{ h.new_status }}</td>
                <td class="px-4 py-2 text-sm">{{ h.notes }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </CardContainer>
    </div>
  </AppLayout>
</template>

<script setup>
 import AppLayout from '@/Layouts/AppLayout.vue';
 import CardContainer from '@/Components/UI/CardContainer.vue';
 import Badge from '@/Components/UI/Badge.vue';
 import PrimaryButton from '@/Components/UI/PrimaryButton.vue';
 import OptimizedImage from '@/Components/OptimizedImage.vue';
 import { router, usePage } from '@inertiajs/vue3';
 import { ref } from 'vue';

const page = usePage();
const member = page.props.member;
const tab = ref('profil');

function statusVariant(s){
  switch (s) {
    case 'aktif': return 'success'
    case 'cuti': return 'warning'
    case 'suspended': return 'danger'
    case 'resign': return 'neutral'
    case 'pensiun': return 'neutral'
    default: return 'neutral'
  }
}

function formatDate(d) {
  if (!d) return '-';
  const date = new Date(d);
  if (Number.isNaN(date.getTime())) return d;
  return date.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
}

function avatarUrl(name){
  return `https://ui-avatars.com/api/?name=${encodeURIComponent(name || '')}&background=0D8ABC&color=fff`;
}

function onPhotoError(e){
  e.target.src = avatarUrl(member.full_name);
}
</script>
