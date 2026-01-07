<template>
  <AppLayout page-title="Detail Surat">
    <div class="max-w-3xl mx-auto space-y-6">
      <AlertBanner v-if="$page.props.flash?.success" type="success" :message="$page.props.flash.success" dismissible />

      <!-- Page Actions -->
      <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <SecondaryButton size="sm" @click="goBack">
          <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
          </svg>
          Kembali
        </SecondaryButton>

        <div class="flex flex-wrap gap-2 justify-end">
          <SecondaryButton size="sm" :href="`/letters/${letter.id}/preview`">
            Preview
          </SecondaryButton>
          <Link
            v-if="canEdit"
            :href="`/letters/${letter.id}/edit`"
            class="inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-brand-primary-500"
          >
            Edit
          </Link>
        </div>
      </div>

      <CardContainer padding="lg">
        <!-- Header -->
        <div class="flex items-start justify-between border-b border-neutral-200 pb-4 mb-6">
          <div>
            <div class="flex items-center gap-2 mb-2">
              <ColorBadge :color="letter.category?.color || 'neutral'" :dot="true">
                {{ letter.category?.code || '-' }}
              </ColorBadge>
              <StatusBadge :status="letter.status" />
              <StatusBadge :status="letter.confidentiality" size="sm" :showDot="false" />
              <StatusBadge :status="letter.urgency" size="sm" :showDot="false" />
            </div>
            <h2 class="text-xl font-semibold text-neutral-900">{{ letter.subject }}</h2>
            <p class="text-sm text-neutral-500 mt-1">{{ letter.category?.name }}</p>
          </div>
        </div>

        <!-- Letter Info -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
          <div>
            <p class="text-xs text-neutral-500 uppercase tracking-wider">Nomor Surat</p>
            <p class="text-sm font-mono text-neutral-900">{{ letter.letter_number || 'Belum digenerate' }}</p>
          </div>
          <div>
            <p class="text-xs text-neutral-500 uppercase tracking-wider">Tanggal Dibuat</p>
            <p class="text-sm text-neutral-900">{{ formatDate(letter.created_at) }}</p>
          </div>
          <div>
            <p class="text-xs text-neutral-500 uppercase tracking-wider">Dari</p>
            <p class="text-sm text-neutral-900">{{ letter.from_unit?.name || 'Pusat' }}</p>
          </div>
          <div>
            <p class="text-xs text-neutral-500 uppercase tracking-wider">Kepada</p>
            <p class="text-sm text-neutral-900">{{ getRecipient() }}</p>
          </div>
          <div>
            <p class="text-xs text-neutral-500 uppercase tracking-wider">Penandatangan</p>
            <p class="text-sm text-neutral-900 capitalize">{{ letter.signer_type }}</p>
          </div>
          <div>
            <p class="text-xs text-neutral-500 uppercase tracking-wider">Sifat Surat</p>
            <div class="mt-1">
              <StatusBadge :status="letter.confidentiality" size="sm" :showDot="false" />
            </div>
          </div>
          <div>
            <p class="text-xs text-neutral-500 uppercase tracking-wider">Urgensi</p>
            <div class="mt-1">
              <StatusBadge :status="letter.urgency" size="sm" :showDot="false" />
            </div>
          </div>
          <div>
            <p class="text-xs text-neutral-500 uppercase tracking-wider">Dibuat oleh</p>
            <p class="text-sm text-neutral-900">{{ letter.creator?.name || '-' }}</p>
          </div>
          <div v-if="letter.approved_by">
            <p class="text-xs text-neutral-500 uppercase tracking-wider">Disetujui oleh</p>
            <p class="text-sm text-neutral-900">{{ letter.approved_by?.name }} ({{ formatDate(letter.approved_at) }})</p>
          </div>
          <div v-if="letter.rejected_by">
            <p class="text-xs text-neutral-500 uppercase tracking-wider">Ditolak oleh</p>
            <p class="text-sm text-neutral-900">{{ letter.rejected_by?.name }} ({{ formatDate(letter.rejected_at) }})</p>
          </div>
        </div>

        <!-- Revision Note -->
        <div v-if="letter.revision_note && ['revision', 'rejected'].includes(letter.status)" class="mb-6 p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
          <p class="text-xs text-yellow-700 uppercase tracking-wider mb-1">Catatan</p>
          <p class="text-sm text-yellow-800">{{ letter.revision_note }}</p>
        </div>

        <!-- Letter Body -->
        <div class="border-t border-neutral-200 pt-6">
          <p class="text-xs text-neutral-500 uppercase tracking-wider mb-2">Isi Surat</p>
          <div class="letter-body prose prose-sm max-w-none text-neutral-800" v-html="bodyHtml"></div>
        </div>

        <!-- Creator Actions -->
        <div v-if="canSubmit" class="border-t border-neutral-200 pt-6 mt-6">
          <button @click="submitLetter" class="inline-flex items-center px-4 py-2 bg-green-600 text-white text-sm font-medium rounded-md hover:bg-green-700">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            Ajukan untuk Persetujuan
          </button>
        </div>

        <!-- Approver Actions -->
        <div v-if="canApprove" class="border-t border-neutral-200 pt-6 mt-6">
          <p class="text-sm font-medium text-neutral-700 mb-3">Tindakan Persetujuan</p>
          <div class="flex space-x-3">
            <button @click="showApprove = true" class="inline-flex items-center px-4 py-2 bg-green-600 text-white text-sm font-medium rounded-md hover:bg-green-700">
              <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
              Setujui
            </button>
            <button @click="showRevision = true" class="inline-flex items-center px-4 py-2 bg-yellow-600 text-white text-sm font-medium rounded-md hover:bg-yellow-700">
              <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
              Minta Revisi
            </button>
            <button @click="showReject = true" class="inline-flex items-center px-4 py-2 bg-red-600 text-white text-sm font-medium rounded-md hover:bg-red-700">
              <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
              Tolak
            </button>
          </div>
        </div>

        <!-- Revision History -->
        <div v-if="letter.revisions?.length > 0" class="border-t border-neutral-200 pt-6 mt-6">
          <p class="text-xs text-neutral-500 uppercase tracking-wider mb-3">Riwayat Revisi</p>
          <div class="space-y-3">
            <div v-for="rev in letter.revisions" :key="rev.id" class="p-3 bg-neutral-50 rounded-lg">
              <p class="text-sm text-neutral-800">{{ rev.note }}</p>
              <p class="text-xs text-neutral-500 mt-1">{{ rev.actor?.name }} • {{ formatDate(rev.created_at) }}</p>
            </div>
          </div>
        </div>
      </CardContainer>
    </div>

    <!-- Approve Modal -->
    <ModalBase v-model:show="showApprove" title="Setujui Surat" size="md">
      <p class="text-neutral-600">Anda yakin ingin menyetujui surat ini?</p>
      <p class="text-sm text-neutral-500 mt-2">Nomor surat akan digenerate secara otomatis.</p>
      <template #footer>
        <div class="flex justify-end space-x-3">
          <SecondaryButton @click="showApprove = false">Batal</SecondaryButton>
          <PrimaryButton class="bg-green-600 hover:bg-green-700" @click="doApprove" :loading="processing">Setujui</PrimaryButton>
        </div>
      </template>
    </ModalBase>

    <!-- Revision Modal -->
    <ModalBase v-model:show="showRevision" title="Minta Revisi" size="md">
      <div class="space-y-4">
        <p class="text-neutral-600">Beri catatan untuk perbaikan surat.</p>
        <div>
          <label class="block text-sm font-medium text-neutral-700 mb-1">Catatan Revisi *</label>
          <textarea v-model="revisionNote" rows="4" class="w-full rounded-md border border-neutral-300 px-3 py-2 text-sm" placeholder="Jelaskan apa yang perlu diperbaiki..."></textarea>
        </div>
      </div>
      <template #footer>
        <div class="flex justify-end space-x-3">
          <SecondaryButton @click="showRevision = false">Batal</SecondaryButton>
          <PrimaryButton class="bg-yellow-600 hover:bg-yellow-700" @click="doRevision" :loading="processing" :disabled="!revisionNote.trim()">Minta Revisi</PrimaryButton>
        </div>
      </template>
    </ModalBase>

    <!-- Reject Modal -->
    <ModalBase v-model:show="showReject" title="Tolak Surat" size="md">
      <div class="space-y-4">
        <p class="text-neutral-600">Berikan alasan penolakan surat.</p>
        <div>
          <label class="block text-sm font-medium text-neutral-700 mb-1">Alasan Penolakan *</label>
          <textarea v-model="rejectNote" rows="4" class="w-full rounded-md border border-neutral-300 px-3 py-2 text-sm" placeholder="Jelaskan alasan penolakan..."></textarea>
        </div>
      </div>
      <template #footer>
        <div class="flex justify-end space-x-3">
          <SecondaryButton @click="showReject = false">Batal</SecondaryButton>
          <PrimaryButton class="bg-red-600 hover:bg-red-700" @click="doReject" :loading="processing" :disabled="!rejectNote.trim()">Tolak</PrimaryButton>
        </div>
      </template>
    </ModalBase>
  </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { router, Link, usePage } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import CardContainer from '@/Components/UI/CardContainer.vue'
import AlertBanner from '@/Components/UI/AlertBanner.vue'
import StatusBadge from '@/Components/UI/StatusBadge.vue'
import ColorBadge from '@/Components/UI/ColorBadge.vue'
import ModalBase from '@/Components/UI/ModalBase.vue'
import PrimaryButton from '@/Components/UI/PrimaryButton.vue'
import SecondaryButton from '@/Components/UI/SecondaryButton.vue'

const props = defineProps({ 
  letter: Object,
  bodyHtml: String,
  canApprove: Boolean,
})
const page = usePage()

const showApprove = ref(false)
const showRevision = ref(false)
const showReject = ref(false)
const revisionNote = ref('')
const rejectNote = ref('')
const processing = ref(false)

const canEdit = computed(() => {
  const isCreator = props.letter.creator_user_id === page.props.auth?.user?.id
  return isCreator && ['draft', 'revision'].includes(props.letter.status)
})

const canSubmit = computed(() => canEdit.value)

function formatDate(dateStr) {
  if (!dateStr) return '-'
  return new Date(dateStr).toLocaleDateString('id-ID', { year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit' })
}

function getRecipient() {
  if (props.letter.to_type === 'unit') return props.letter.to_unit?.name || 'Unit'
  if (props.letter.to_type === 'member') return props.letter.to_member?.full_name || 'Anggota'
  if (props.letter.to_type === 'admin_pusat') return 'Admin Pusat'
  return '-'
}

function goBack() {
  if (typeof window !== 'undefined' && window.history && window.history.length > 1) {
    window.history.back()
    return
  }

  if (props.canApprove && props.letter.status === 'submitted') {
    router.visit('/letters/approvals')
    return
  }

  const isCreator = props.letter.creator_user_id === page.props.auth?.user?.id
  router.visit(isCreator ? '/letters/outbox' : '/letters/inbox')
}

function submitLetter() {
  if (!confirm('Ajukan surat ini untuk persetujuan?')) return
  router.post(`/letters/${props.letter.id}/submit`)
}

function doApprove() {
  processing.value = true
  router.post(`/letters/${props.letter.id}/approve`, {}, {
    onSuccess() { showApprove.value = false },
    onFinish() { processing.value = false }
  })
}

function doRevision() {
  if (!revisionNote.value.trim()) return
  processing.value = true
  router.post(`/letters/${props.letter.id}/revise`, { note: revisionNote.value }, {
    onSuccess() { showRevision.value = false; revisionNote.value = '' },
    onFinish() { processing.value = false }
  })
}

function doReject() {
  if (!rejectNote.value.trim()) return
  processing.value = true
  router.post(`/letters/${props.letter.id}/reject`, { note: rejectNote.value }, {
    onSuccess() { showReject.value = false; rejectNote.value = '' },
    onFinish() { processing.value = false }
  })
}
</script>
