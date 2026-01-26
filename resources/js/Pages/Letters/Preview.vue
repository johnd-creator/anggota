<template>
  <div class="min-h-screen bg-neutral-200 py-8 print:bg-white print:py-0">
    <!-- Print/Back buttons - hide on print -->
    <div class="max-w-[210mm] mx-auto mb-4 flex gap-2 print:hidden">
      <button @click="goBack" class="px-4 py-2 text-sm bg-white border border-neutral-300 rounded shadow hover:bg-neutral-50">
        ← Kembali
      </button>
      <button @click="printPage" class="px-4 py-2 text-sm bg-blue-600 text-white rounded shadow hover:bg-blue-700">
        🖨️ Cetak
      </button>
      <a v-if="isFinal" :href="`/letters/${letter.id}/pdf`" class="px-4 py-2 text-sm bg-green-600 text-white rounded shadow hover:bg-green-700">
        📄 Unduh PDF
      </a>
    </div>

    <!-- A4 Paper -->
    <div class="w-[210mm] min-h-[297mm] mx-auto bg-white shadow-lg print:shadow-none p-[20mm] relative flex flex-col">
      <!-- Header / Letterhead -->
      <div class="border-b-2 border-neutral-900 pb-3 mb-6">
        <div class="flex items-center gap-4">
          <!-- Logo -->
          <div class="w-24 h-24 flex-shrink-0">
            <img v-if="letterheadLogo" :src="letterheadLogo" alt="Logo" class="w-full h-full object-contain" />
            <img v-else :src="defaultLogo" alt="Logo" class="w-full h-full object-contain" />
          </div>

          <!-- Title & Contact -->
          <div class="flex-1 text-center leading-snug">
            <div class="text-[16px] font-semibold tracking-wide text-neutral-900">SERIKAT PEKERJA</div>
            <div class="text-[14px] font-bold uppercase text-neutral-900">
              PT PLN INDONESIA POWER SERVICES (SP PIPS)
            </div>
            <div class="flex justify-center gap-x-2">
            <div class="text-[12px] font-semibold uppercase text-neutral-900">{{ unitOrgTypeLine }}</div>
            <div class="text-[12px] font-semibold uppercase text-neutral-900">{{ unitNameLine }}</div>
                </div>
            <div v-if="unitAddressLine" class="mt-1 text-[10px] text-neutral-700">
              {{ unitAddressLine }}
            </div>
            <div v-if="unitContactLine" class="text-[10px] text-neutral-700">
              {{ unitContactLine }}
            </div>
            <div class="text-[10px] text-neutral-700">
              No Bukti Pencatatan Disnaker : 951/SP/JS/X/2024, Tanggal 1 Oktober 2024
            </div>
          </div>

          <!-- Spacer so text stays centered like official letterhead -->
          <div class="w-24 h-24 flex-shrink-0" aria-hidden="true"></div>
        </div>
      </div>

      <!-- Letter Number / Date -->
      <div class="flex justify-between mb-4">
        <div>
          <p class="text-sm"><strong>Nomor:</strong> {{ letter.letter_number || '(Belum digenerate)' }}</p>
          <p class="text-sm"><strong>Lampiran:</strong> {{ attachmentsLabel }}</p>
          <p class="text-sm"><strong>Perihal:</strong> {{ letter.subject }}</p>
        </div>
        <div class="text-right text-sm">
          <p>{{ cityDateLine }}</p>
        </div>
      </div>

      <!-- Recipient -->
      <div class="mb-4 text-sm">
        <p><strong>Kepada Yth,</strong></p>
        <p class="whitespace-pre-wrap">{{ recipientName }}</p>
      </div>

      <!-- Body -->
      <div class="letter-body text-sm leading-relaxed" v-html="bodyHtml"></div>

      <!-- Signature Block -->
      <div class="mt-10">
        <!-- Dual signature layout (when secondary signer exists) -->
        <div v-if="letter.signer_type_secondary" class="flex justify-between gap-8">
          <!-- Primary Signer (Ketua/Sekretaris) -->
          <div class="text-center w-56">
            <p class="text-sm">{{ cityDateLine }}</p>
            <p class="text-sm font-semibold mt-1">{{ primarySignerTitle }}</p>
            
            <!-- QR only if final -->
            <template v-if="isFinal">
              <div class="mt-2 flex justify-center">
                <img :src="qrSrc" alt="QR" class="w-16 h-16 block bg-white" @error="qrError = true" />
              </div>
              <p v-if="qrError" class="mt-1 text-xs text-neutral-500">
                <a :href="verifyUrl" class="underline text-blue-600">Link Verifikasi</a>
              </p>
            </template>
            <template v-else>
              <div class="mt-2 h-16 flex items-center justify-center">
                <span v-if="letter.approved_by" class="text-xs text-green-600 italic">✓ Disetujui</span>
                <span v-else class="text-xs text-neutral-400 italic">Menunggu Persetujuan</span>
              </div>
            </template>
            
            <p class="mt-2 text-sm font-semibold underline">{{ primarySignerName }}</p>
          </div>
          
          <!-- Secondary Signer (Bendahara) -->
          <div class="text-center w-56">
            <p class="text-sm">{{ cityDateLine }}</p>
            <p class="text-sm font-semibold mt-1">{{ secondarySignerTitle }}</p>
            
            <div class="mt-2 h-16 flex items-center justify-center">
              <span v-if="letter.approved_secondary_by" class="text-xs text-green-600 italic">✓ Disetujui</span>
              <span v-else class="text-xs text-neutral-400 italic">Menunggu Persetujuan</span>
            </div>
            
            <p class="mt-2 text-sm font-semibold underline">{{ secondarySignerName }}</p>
          </div>
        </div>
        
        <!-- Single signature layout (existing behavior) -->
        <div v-else class="ml-auto text-center w-56">
          <p class="text-sm">{{ cityDateLine }}</p>
          <p class="text-sm font-semibold mt-1">{{ signerTitle }}</p>

          <!-- QR as approval stamp (only for final letters) -->
          <template v-if="isFinal">
            <div class="mt-2 flex justify-center">
              <img :src="qrSrc" alt="QR" class="w-20 h-20 block bg-white" @error="qrError = true" />
            </div>
            <p v-if="qrError" class="mt-1 text-xs text-neutral-500">
              <a :href="verifyUrl" class="underline text-blue-600">Link Verifikasi</a>
            </p>
            <p v-else class="mt-1 text-[10px] text-neutral-400">Scan untuk verifikasi</p>
          </template>
          <template v-else>
            <div class="mt-2 h-20 flex items-center justify-center">
              <span class="text-xs text-neutral-400 italic">Menunggu Persetujuan</span>
            </div>
          </template>

          <p class="mt-2 text-sm font-semibold underline">{{ signerName }}</p>
        </div>
      </div>

      <!-- Spacer pushes tembusan to bottom for short letters -->
      <div class="flex-1 min-h-8"></div>

      <!-- Tembusan (selalu di bawah kiri) -->
      <div v-if="tembusanList.length" class="text-sm leading-relaxed">
        <p class="font-semibold mb-1">Tembusan:</p>
        <ol class="list-decimal list-inside space-y-0.5">
          <li v-for="(item, idx) in tembusanList" :key="idx">{{ item }}</li>
        </ol>
      </div>

      <!-- Footer -->
      <div v-if="letter.from_unit?.letterhead_footer_text" class="absolute bottom-[15mm] left-[20mm] right-[20mm] text-center text-xs text-neutral-500 border-t border-neutral-200 pt-2">
        {{ letter.from_unit.letterhead_footer_text }}
      </div>
    </div>

    <!-- Attachments (below paper, screen only) -->
    <div v-if="letter.attachments?.length" class="max-w-[210mm] mx-auto mt-4 print:hidden">
      <div class="bg-white shadow rounded p-4">
        <h3 class="text-sm font-semibold mb-2 border-b pb-1">📎 Lampiran ({{ letter.attachments.length }})</h3>
        <ol class="space-y-1 list-decimal list-inside">
          <li v-for="att in letter.attachments" :key="att.id" class="text-sm">
            <a :href="`/letters/${letter.id}/attachments/${att.id}`" class="text-blue-600 hover:underline font-medium">
              {{ att.original_name }}
            </a>
            <span class="text-neutral-400 text-xs ml-1">({{ formatFileSize(att.size) }})</span>
          </li>
        </ol>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, ref } from 'vue'

const props = defineProps({
  letter: Object,
  bodyHtml: String,
  verifyUrl: String,
  qrBase64: String,
  qrMime: String,
  isFinal: Boolean,
})

const qrError = ref(false)

// Default logo fallback
const defaultLogo = new URL('../../../images/logo.png', import.meta.url).href

const letterheadLogo = computed(() => {
  const path = props.letter.from_unit?.letterhead_logo_path
  if (path) {
    return path.startsWith('http') ? path : `/storage/${path}`
  }
  return null
})

const qrSrc = computed(() => {
  if (props.qrBase64) {
    const mime = props.qrMime || 'image/png'
    return `data:${mime};base64,${props.qrBase64}`
  }
  return `/letters/${props.letter.id}/qr.png`
})

const unitAddressLine = computed(() => {
  const u = props.letter.from_unit
  if (!u) return ''
  const address = u.letterhead_address || u.address
  const parts = [address, u.letterhead_city, u.letterhead_postal_code].filter(Boolean)
  return parts.join(', ')
})

const unitContactLine = computed(() => {
  const u = props.letter.from_unit
  if (!u) return ''
  // Prefer explicit letterhead overrides if set
  const phone = u.letterhead_phone || u.phone
  const email = u.letterhead_email || u.email
  const parts = []
  if (phone) parts.push(`No Telepon : ${phone}`)
  if (email) parts.push(`Email : ${email}`)
  return parts.join(', ')
})

const unitOrgTypeLine = computed(() => {
  const u = props.letter.from_unit
  const type = u?.organization_type
  if (type === 'DPP') return 'DEWAN PIMPINAN PUSAT (DPP)'
  return 'DEWAN PIMPINAN DAERAH (DPD)'
})

const unitNameLine = computed(() => {
  const u = props.letter.from_unit
  return u?.name || 'PUSAT'
})

const cityName = computed(() => {
  const u = props.letter.from_unit
  if (u?.letterhead_city) return u.letterhead_city
  // Default Jakarta only for DPP/pusat
  if (u?.organization_type === 'DPP') return 'Jakarta'
  return ''
})

const formattedDate = computed(() => {
  const d = props.letter.approved_at || props.letter.created_at
  if (!d) return '-'
  return new Date(d).toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' })
})

const cityDateLine = computed(() => {
  if (!cityName.value) return formattedDate.value
  return `${cityName.value}, ${formattedDate.value}`
})

const recipientName = computed(() => {
  if (props.letter.to_type === 'unit') return props.letter.to_unit?.name || 'Unit'
  if (props.letter.to_type === 'member') return this.$toTitleCase(props.letter.to_member?.full_name) || 'Anggota'
  if (props.letter.to_type === 'admin_pusat') return 'Admin Pusat'
  if (props.letter.to_type === 'eksternal') {
    const parts = []
    if (props.letter.to_external_name) parts.push(props.letter.to_external_name)
    if (props.letter.to_external_org) parts.push(props.letter.to_external_org)
    if (props.letter.to_external_address) parts.push(props.letter.to_external_address)
    return parts.length > 0 ? parts.join('\n') : 'Pihak Eksternal'
  }
  return '-'
})

const tembusanList = computed(() => {
  if (!props.letter.cc_text) return []
  return props.letter.cc_text
    .split('\n')
    .map(line => line.trim())
    .filter(line => line.length > 0)
    .map(line => line.replace(/^\s*(?:\d+\s*[\)\.\-]|[-•])\s*/u, ''))
})

const signerTitle = computed(() => {
  return props.letter.signer_type === 'ketua' ? 'Ketua' : 'Sekretaris'
})

const signerName = computed(() => {
  if (['approved', 'sent', 'archived'].includes(props.letter.status)) {
    return props.letter.approved_by?.name || '(nama)'
  }
  return '(Menunggu Persetujuan)'
})

// Dual signature computed properties
const primarySignerTitle = computed(() => {
  return props.letter.signer_type === 'ketua' ? 'Ketua' : 'Sekretaris'
})

const primarySignerName = computed(() => {
  // Show name if primary approval done (even if status is still 'submitted' in dual flow)
  return props.letter.approved_by?.name || '(Menunggu Persetujuan)'
})

const secondarySignerTitle = computed(() => {
  // Currently only bendahara supported
  return props.letter.signer_type_secondary === 'bendahara' ? 'Bendahara' : 'Penandatangan 2'
})

const secondarySignerName = computed(() => {
  return props.letter.approved_secondary_by?.name || '(Menunggu Persetujuan)'
})

const attachmentsLabel = computed(() => {
  const count = props.letter.attachments?.length || 0
  if (count === 0) return '-'
  return `${count} berkas`
})

function goBack() {
  window.history.back()
}

function printPage() {
  window.print()
}

function formatFileSize(bytes) {
  if (bytes < 1024) return bytes + ' B'
  if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB'
  return (bytes / (1024 * 1024)).toFixed(1) + ' MB'
}
</script>

<style scoped>
@media print {
  @page {
    size: A4;
    margin: 0;
  }
}
</style>
