<template>
  <AppLayout :page-title="letter ? 'Edit Surat' : 'Buat Surat Baru'">
    <CardContainer padding="lg" class="max-w-3xl mx-auto">
      <AlertBanner v-if="$page.props.flash?.error" type="error" :message="$page.props.flash.error" />
      <AlertBanner v-if="Object.keys(form.errors).length > 0" type="error" message="Mohon perbaiki kesalahan di bawah ini." />

      <form @submit.prevent="saveDraft">
        <div class="space-y-6">
          <!-- Category -->
          <div>
            <label class="block text-sm font-medium text-neutral-700">Kategori Surat <span class="text-red-500">*</span></label>
            <div class="flex gap-2 mt-1">
              <select v-model="form.letter_category_id" class="flex-1 block rounded-md border border-neutral-300 px-3 py-2 text-sm text-neutral-700 focus:ring-2 focus:ring-brand-primary-500">
                <option value="">Pilih Kategori</option>
                <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.code }} - {{ cat.name }}</option>
              </select>
              <button
                type="button"
                @click="applyTemplate"
                :disabled="!form.letter_category_id || applyingTemplate"
                class="px-3 py-2 text-sm bg-blue-100 text-blue-700 rounded-md hover:bg-blue-200 disabled:opacity-50 disabled:cursor-not-allowed whitespace-nowrap"
              >
                {{ applyingTemplate ? '⏳' : '📄' }} Template
              </button>
            </div>
            <p v-if="form.errors.letter_category_id" class="text-xs text-status-error mt-1">{{ form.errors.letter_category_id }}</p>
            <p v-if="selectedCategoryHasTemplate" class="text-xs text-blue-600 mt-1">💡 Kategori ini memiliki template. Klik "📄 Template" untuk mengisi otomatis.</p>
          </div>

          <!-- Signer Type -->
          <div>
            <label class="block text-sm font-medium text-neutral-700">Penandatangan Utama <span class="text-red-500">*</span></label>
            <select v-model="form.signer_type" class="mt-1 block w-full rounded-md border border-neutral-300 px-3 py-2 text-sm text-neutral-700 focus:ring-2 focus:ring-brand-primary-500">
              <option value="">Pilih Penandatangan</option>
              <option value="ketua">Ketua</option>
              <option value="sekretaris">Sekretaris</option>
            </select>
            <p v-if="form.errors.signer_type" class="text-xs text-status-error mt-1">{{ form.errors.signer_type }}</p>
          </div>

          <!-- Secondary Signer Type (Optional 2nd Approval) -->
          <div>
            <label class="block text-sm font-medium text-neutral-700">Penandatangan Kedua</label>
            <select v-model="form.signer_type_secondary" class="mt-1 block w-full rounded-md border border-neutral-300 px-3 py-2 text-sm text-neutral-700 focus:ring-2 focus:ring-brand-primary-500">
              <option value="">Tidak Ada (1x Approval)</option>
              <option value="bendahara">Bendahara (2x Approval)</option>
            </select>
            <p class="text-xs text-neutral-400 mt-1">Jika dipilih, surat memerlukan 2 tahap persetujuan.</p>
            <p v-if="form.errors.signer_type_secondary" class="text-xs text-status-error mt-1">{{ form.errors.signer_type_secondary }}</p>
          </div>

          <!-- Recipient Type -->
          <div>
            <label class="block text-sm font-medium text-neutral-700">Tujuan <span class="text-red-500">*</span></label>
            <select v-model="form.to_type" class="mt-1 block w-full rounded-md border border-neutral-300 px-3 py-2 text-sm text-neutral-700 focus:ring-2 focus:ring-brand-primary-500">
              <option value="">Pilih Tujuan</option>
              <option value="unit">Unit</option>
              <option value="member">Anggota</option>
              <option value="admin_pusat">Admin Pusat</option>
              <option value="eksternal">Eksternal (Pihak Luar)</option>
            </select>
            <p v-if="form.errors.to_type" class="text-xs text-status-error mt-1">{{ form.errors.to_type }}</p>
          </div>

          <!-- Unit Select (if to_type = unit) -->
          <div v-if="form.to_type === 'unit'">
            <label class="block text-sm font-medium text-neutral-700">Pilih Unit <span class="text-red-500">*</span></label>
            <select v-model="form.to_unit_id" class="mt-1 block w-full rounded-md border border-neutral-300 px-3 py-2 text-sm text-neutral-700 focus:ring-2 focus:ring-brand-primary-500">
              <option value="">Pilih Unit</option>
              <option v-for="u in units" :key="u.id" :value="u.id">{{ u.code }} - {{ u.name }}</option>
            </select>
            <p v-if="form.errors.to_unit_id" class="text-xs text-status-error mt-1">{{ form.errors.to_unit_id }}</p>
          </div>

          <!-- External Recipient (if to_type = eksternal) -->
          <div v-if="form.to_type === 'eksternal'" class="space-y-3 bg-neutral-50 rounded-lg p-4 border border-neutral-200">
            <p class="text-sm font-medium text-neutral-700">📧 Penerima Eksternal</p>
            
            <!-- Jabatan/Nama Penerima -->
            <div>
              <label class="block text-sm font-medium text-neutral-700">Jabatan / Nama Penerima <span class="text-red-500">*</span></label>
              <input 
                v-model="form.to_external_name" 
                type="text"
                placeholder="Contoh: Kepala Dinas Ketenagakerjaan"
                class="mt-1 block w-full rounded-md border border-neutral-300 px-3 py-2 text-sm text-neutral-700 focus:ring-2 focus:ring-brand-primary-500"
              />
              <p class="text-xs text-neutral-400 mt-1">Jabatan atau nama lengkap penerima surat.</p>
              <p v-if="form.errors.to_external_name" class="text-xs text-status-error mt-1">{{ form.errors.to_external_name }}</p>
            </div>
            
            <!-- Instansi/Perusahaan -->
            <div>
              <label class="block text-sm font-medium text-neutral-700">Instansi / Perusahaan</label>
              <input 
                v-model="form.to_external_org" 
                type="text"
                placeholder="Contoh: Dinas Ketenagakerjaan Kota Jakarta"
                class="mt-1 block w-full rounded-md border border-neutral-300 px-3 py-2 text-sm text-neutral-700 focus:ring-2 focus:ring-brand-primary-500"
              />
              <p class="text-xs text-neutral-400 mt-1">Nama instansi/perusahaan tujuan (opsional).</p>
              <p v-if="form.errors.to_external_org" class="text-xs text-status-error mt-1">{{ form.errors.to_external_org }}</p>
            </div>
            
            <!-- Alamat Eksternal -->
            <div>
              <label class="block text-sm font-medium text-neutral-700">Alamat Penerima</label>
              <textarea 
                v-model="form.to_external_address" 
                rows="3"
                placeholder="Contoh:
Jl. Jend. Gatot Subroto Kav. 52
Jakarta Selatan 12950
Telp. (021) 5290-1234"
                class="mt-1 block w-full rounded-md border border-neutral-300 px-3 py-2 text-sm text-neutral-700 focus:ring-2 focus:ring-brand-primary-500"
              ></textarea>
              <p class="text-xs text-neutral-400 mt-1">Alamat lengkap penerima. Gunakan Enter untuk baris baru.</p>
              <p v-if="form.errors.to_external_address" class="text-xs text-status-error mt-1">{{ form.errors.to_external_address }}</p>
            </div>
            
            <p class="text-xs text-blue-600 mt-2">💡 Surat ke eksternal akan disimpan sebagai arsip internal.</p>
          </div>

          <!-- Member Search (if to_type = member) -->
          <div v-if="form.to_type === 'member'">
            <label class="block text-sm font-medium text-neutral-700">Cari Anggota <span class="text-red-500">*</span></label>
            <div class="relative">
              <input 
                v-model="memberSearch" 
                @input="searchMember"
                type="text" 
                placeholder="Ketik nama, email, atau NRA..."
                class="mt-1 block w-full rounded-md border border-neutral-300 px-3 py-2 text-sm text-neutral-700 focus:ring-2 focus:ring-brand-primary-500"
              />
              <div v-if="memberResults.length > 0" class="absolute z-10 w-full mt-1 bg-white border border-neutral-200 rounded-md shadow-lg max-h-48 overflow-y-auto">
                <button 
                  v-for="m in memberResults" 
                  :key="m.id" 
                  type="button"
                  @click="selectMember(m)"
                  class="w-full text-left px-3 py-2 text-sm hover:bg-neutral-100"
                >
                  {{ m.label }}
                </button>
              </div>
            </div>
            <p v-if="selectedMember" class="text-xs text-green-600 mt-1">✓ Terpilih: {{ selectedMember.label }}</p>
            <p v-if="form.errors.to_member_id" class="text-xs text-status-error mt-1">{{ form.errors.to_member_id }}</p>
          </div>

          <!-- Subject -->
          <div>
            <label class="block text-sm font-medium text-neutral-700">Perihal <span class="text-red-500">*</span></label>
            <InputField v-model="form.subject" placeholder="Perihal surat" class="mt-1" maxlength="255" />
            <p v-if="form.errors.subject" class="text-xs text-status-error mt-1">{{ form.errors.subject }}</p>
          </div>

          <!-- Sifat & Urgensi -->
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-neutral-700">Sifat Surat <span class="text-red-500">*</span></label>
              <select v-model="form.confidentiality" class="mt-1 block w-full rounded-md border border-neutral-300 px-3 py-2 text-sm text-neutral-700 focus:ring-2 focus:ring-brand-primary-500">
                <option value="biasa">Biasa</option>
                <option value="terbatas">Terbatas/Konfidensial</option>
                <option value="rahasia">Rahasia</option>
              </select>
              <p v-if="form.errors.confidentiality" class="text-xs text-status-error mt-1">{{ form.errors.confidentiality }}</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-neutral-700">Urgensi <span class="text-red-500">*</span></label>
              <select v-model="form.urgency" class="mt-1 block w-full rounded-md border border-neutral-300 px-3 py-2 text-sm text-neutral-700 focus:ring-2 focus:ring-brand-primary-500">
                <option value="biasa">Biasa</option>
                <option value="segera">Segera</option>
                <option value="kilat">Sangat Segera/Kilat</option>
              </select>
              <p v-if="form.errors.urgency" class="text-xs text-status-error mt-1">{{ form.errors.urgency }}</p>
            </div>
          </div>

          <!-- Body -->
          <div>
            <label class="block text-sm font-medium text-neutral-700">Isi Surat <span class="text-red-500">*</span></label>
            <RichTextEditor 
              v-model="form.body" 
              placeholder="Isi surat..."
              class="mt-1"
            />
            <p v-if="form.errors.body" class="text-xs text-status-error mt-1">{{ form.errors.body }}</p>
          </div>

	          <!-- Tembusan -->
	          <div>
	            <label class="block text-sm font-medium text-neutral-700">Tembusan (opsional)</label>
	            <textarea 
	              v-model="form.cc_text" 
	              rows="6" 
	              placeholder="Satu baris = satu tembusan (3–15+ item). Contoh:&#10;Ketua SP PLN IPS Pusat&#10;Kepala Disnaker...&#10;Arsip"
	              class="mt-1 block w-full rounded-md border border-neutral-300 px-3 py-2 text-sm text-neutral-700 focus:ring-2 focus:ring-brand-primary-500"
	            />
	            <p class="text-xs text-neutral-400 mt-1">Tembusan akan dicetak sebagai daftar bernomor. Maks 5000 karakter.</p>
	            <p v-if="form.errors.cc_text" class="text-xs text-status-error mt-1">{{ form.errors.cc_text }}</p>
	          </div>

          <!-- Attachments Section -->
          <div class="border-t border-neutral-200 pt-4" id="attachments-section">
            <label class="block text-sm font-medium text-neutral-700 mb-2">📎 Lampiran (PDF, opsional)</label>
            
            <!-- Info for new letters (not yet saved) -->
            <div v-if="!letter?.id" class="bg-blue-50 border border-blue-200 rounded p-3 text-sm text-blue-700">
              💡 Lampiran bisa ditambahkan setelah draft disimpan. Simpan draft terlebih dahulu.
            </div>

            <!-- Attachment upload for existing draft/revision -->
            <div v-else-if="canEditAttachments">
              <!-- Existing attachments -->
              <div v-if="letter.attachments?.length" class="mb-4 bg-neutral-50 rounded p-3">
                <p class="text-xs text-neutral-500 mb-2 font-medium">Lampiran saat ini ({{ letter.attachments.length }}):</p>
                <ul class="space-y-1">
                  <li v-for="(att, idx) in letter.attachments" :key="att.id" class="text-sm flex items-center gap-2">
                    <span class="text-neutral-400">{{ idx + 1 }}.</span>
                    <span>📄</span>
                    <a :href="`/letters/${letter.id}/attachments/${att.id}`" class="text-blue-600 hover:underline font-medium" target="_blank">
                      {{ att.original_name }}
                    </a>
                    <span class="text-neutral-400 text-xs">({{ formatAttachmentSize(att.size) }})</span>
                  </li>
                </ul>
              </div>
              <div v-else class="mb-3 text-sm text-neutral-500 italic">
                Belum ada lampiran.
              </div>

              <!-- Upload new -->
              <div class="flex flex-col sm:flex-row items-start sm:items-center gap-2">
                <input 
                  ref="fileInput"
                  type="file" 
                  multiple 
                  accept="application/pdf,.pdf"
                  @change="handleFileSelect"
                  class="flex-1 text-sm text-neutral-700 file:mr-3 file:px-3 file:py-1.5 file:rounded file:border file:border-neutral-300 file:bg-white file:text-neutral-700 file:cursor-pointer hover:file:bg-neutral-50"
                />
                <button 
                  type="button" 
                  @click="uploadAttachments" 
                  :disabled="!selectedFiles.length || uploading"
                  class="px-4 py-2 text-sm bg-blue-600 text-white rounded shadow hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed whitespace-nowrap"
                >
                  {{ uploading ? '⏳ Uploading...' : '⬆️ Upload Lampiran' }}
                </button>
              </div>
              <p class="text-xs text-neutral-400 mt-2">Format: PDF only. Max 10 file, masing-masing max 5MB.</p>
            </div>

            <!-- Info for non-editable status -->
            <div v-else-if="letter?.id" class="text-sm text-neutral-500 italic">
              Lampiran tidak dapat diubah untuk surat dengan status "{{ letter.status }}".
            </div>
          </div>

          <!-- Actions -->
          <div class="flex justify-end space-x-3 pt-4 border-t border-neutral-200">
            <SecondaryButton @click="router.visit('/letters/outbox')">Batal</SecondaryButton>
            <PrimaryButton type="submit" :loading="submitting" class="bg-neutral-600 hover:bg-neutral-700">
              Simpan Draft
            </PrimaryButton>
            <PrimaryButton type="button" @click="submitForApproval" :loading="submittingApproval" :disabled="submitting">
              Ajukan Persetujuan
            </PrimaryButton>
          </div>
        </div>
      </form>
    </CardContainer>
  </AppLayout>
</template>

<script setup>
import { ref, watch, computed } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import CardContainer from '@/Components/UI/CardContainer.vue'
import InputField from '@/Components/UI/InputField.vue'
import PrimaryButton from '@/Components/UI/PrimaryButton.vue'
import SecondaryButton from '@/Components/UI/SecondaryButton.vue'
import AlertBanner from '@/Components/UI/AlertBanner.vue'
import RichTextEditor from '@/Components/UI/RichTextEditor.vue'

const props = defineProps({ 
  letter: Object, 
  categories: Array, 
  units: Array 
})

const form = useForm({
  letter_category_id: props.letter?.letter_category_id || '',
  signer_type: props.letter?.signer_type || '',
  signer_type_secondary: props.letter?.signer_type_secondary || '',
  to_type: props.letter?.to_type || '',
  to_unit_id: props.letter?.to_unit_id || '',
  to_member_id: props.letter?.to_member_id || '',
  to_external_name: props.letter?.to_external_name || '',
  to_external_org: props.letter?.to_external_org || '',
  to_external_address: props.letter?.to_external_address || '',
  subject: props.letter?.subject || '',
  body: props.letter?.body || '',
  cc_text: props.letter?.cc_text || '',
  confidentiality: props.letter?.confidentiality || 'biasa',
  urgency: props.letter?.urgency || 'biasa',
  submit_after_save: false,
})

const submitting = ref(false)
const submittingApproval = ref(false)
const applyingTemplate = ref(false)
const memberSearch = ref('')
const memberResults = ref([])
const selectedMember = ref(props.letter?.to_member ? { id: props.letter.to_member.id, label: props.letter.to_member.full_name } : null)
let searchTimeout = null

// Check if selected category has template
const selectedCategoryHasTemplate = computed(() => {
  if (!form.letter_category_id) return false
  const cat = props.categories.find(c => c.id === form.letter_category_id)
  return cat && (cat.template_subject || cat.template_body)
})

// Apply template from backend
async function applyTemplate() {
  if (!form.letter_category_id) return

  // Confirm if form has content
  if (form.subject?.trim() || form.body?.trim()) {
    if (!confirm('Ini akan mengisi ulang Perihal, Isi, dan Tembusan dari template. Lanjutkan?')) {
      return
    }
  }

  applyingTemplate.value = true
  try {
    const params = new URLSearchParams({
      category_id: form.letter_category_id,
      to_type: form.to_type || '',
      to_unit_id: form.to_unit_id || '',
      to_member_id: form.to_member_id || '',
    })
    const res = await fetch(`/letters/template-render?${params}`)
    const data = await res.json()

    // Apply template content
    if (data.subject) form.subject = data.subject
    if (data.body) {
      // Convert plain text template to HTML for RichTextEditor
      let htmlBody = data.body
        .split('\n\n').join('</p><p>')  // Double newlines become paragraph breaks
        .split('\n').join('<br>')       // Single newlines become line breaks
      form.body = '<p>' + htmlBody + '</p>'
    }
    if (data.cc_text) form.cc_text = data.cc_text

    // Apply defaults only if current value is empty/default
    if (data.defaults?.confidentiality && form.confidentiality === 'biasa') {
      form.confidentiality = data.defaults.confidentiality
    }
    if (data.defaults?.urgency && form.urgency === 'biasa') {
      form.urgency = data.defaults.urgency
    }
    if (data.defaults?.signer_type && !form.signer_type) {
      form.signer_type = data.defaults.signer_type
    }
  } catch (e) {
    console.error('Failed to apply template', e)
  } finally {
    applyingTemplate.value = false
  }
}

// Attachment upload
const fileInput = ref(null)
const selectedFiles = ref([])
const uploading = ref(false)
const canEditAttachments = computed(() => ['draft', 'revision'].includes(props.letter?.status))

function handleFileSelect(event) {
  selectedFiles.value = Array.from(event.target.files || [])
}

function uploadAttachments() {
  if (!selectedFiles.value.length || !props.letter?.id) return
  uploading.value = true
  const formData = new FormData()
  selectedFiles.value.forEach(f => formData.append('attachments[]', f))
  router.post(`/letters/${props.letter.id}/attachments`, formData, {
    forceFormData: true,
    onSuccess() {
      selectedFiles.value = []
      if (fileInput.value) fileInput.value.value = ''
      router.reload({ only: ['letter'] })
    },
    onFinish() {
      uploading.value = false
    }
  })
}

function formatAttachmentSize(bytes) {
  if (!bytes) return ''
  if (bytes < 1024) return bytes + ' B'
  if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB'
  return (bytes / (1024 * 1024)).toFixed(1) + ' MB'
}

// Reset dependent fields when to_type changes
watch(() => form.to_type, (newVal) => {
  if (newVal !== 'unit') form.to_unit_id = ''
  if (newVal !== 'member') {
    form.to_member_id = ''
    selectedMember.value = null
    memberSearch.value = ''
    memberResults.value = []
  }
  if (newVal !== 'eksternal') {
    form.to_external_name = ''
    form.to_external_org = ''
    form.to_external_address = ''
  }
})

function searchMember() {
  if (searchTimeout) clearTimeout(searchTimeout)
  if (memberSearch.value.length < 2) {
    memberResults.value = []
    return
  }
  searchTimeout = setTimeout(async () => {
    try {
      const res = await fetch(`/api/members/search?q=${encodeURIComponent(memberSearch.value)}`)
      memberResults.value = await res.json()
    } catch (e) {
      memberResults.value = []
    }
  }, 300)
}

function selectMember(m) {
  selectedMember.value = m
  form.to_member_id = m.id
  memberSearch.value = ''
  memberResults.value = []
}

function saveDraft() {
  submitting.value = true
  form.submit_after_save = false
  if (props.letter?.id) {
    form.put(`/letters/${props.letter.id}`, {
      onFinish() { submitting.value = false }
    })
  } else {
    form.post('/letters', {
      onFinish() { submitting.value = false }
    })
  }
}

// Save draft first, then submit
async function submitForApproval() {
  submittingApproval.value = true
  
  const savePromise = new Promise((resolve, reject) => {
    if (props.letter?.id) {
      form.put(`/letters/${props.letter.id}`, {
        onSuccess: () => resolve(props.letter.id),
        onError: () => reject(),
        preserveScroll: true,
      })
    } else {
      form.submit_after_save = true
      form.post('/letters', {
        onSuccess: () => resolve(null),
        onError: () => reject(),
        onFinish: () => { form.submit_after_save = false },
      })
    }
  })

  try {
    const letterId = await savePromise
    if (letterId) {
      router.post(`/letters/${letterId}/submit`)
    }
  } catch (e) {
    // Validation error, form.errors will show
  } finally {
    submittingApproval.value = false
  }
}
</script>
