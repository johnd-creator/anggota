<script setup>
import { ref } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import InputError from '@/Components/UI/InputError.vue';
import InputLabel from '@/Components/UI/InputLabel.vue';
import PrimaryButton from '@/Components/UI/PrimaryButton.vue';
import InputField from '@/Components/UI/InputField.vue';

const props = defineProps({
    mode: String,
    announcement: Object,
    allowed_scopes: Array,
    units: Array,
    defaults: Object,
});

const form = useForm({
    title: props.announcement?.title || '',
    body: props.announcement?.body || '',
    scope_type: props.announcement?.scope_type || props.defaults?.scope_type || '',
    organization_unit_id: props.announcement?.organization_unit_id || props.defaults?.organization_unit_id || null,
    is_active: props.announcement ? Boolean(props.announcement.is_active) : (props.defaults?.is_active ?? true),
    pin_to_dashboard: props.announcement ? Boolean(props.announcement.pin_to_dashboard) : (props.defaults?.pin_to_dashboard ?? false),
});

const uploading = ref(false);
const uploadFilesList = ref([]);
const uploadError = ref('');
const fileInput = ref(null);

const handleFileChange = (e) => {
    uploadFilesList.value = Array.from(e.target.files);
    uploadError.value = '';
};

const uploadFiles = () => {
    if (!uploadFilesList.value.length) return;

    uploading.value = true;
    const formData = new FormData();
    uploadFilesList.value.forEach(file => {
        formData.append('attachments[]', file);
    });

    router.post(`/admin/announcements/${props.announcement.id}/attachments`, formData, {
        onSuccess: () => {
             uploadFilesList.value = [];
             if (fileInput.value) fileInput.value.value = '';
             uploading.value = false;
        },
        onError: (errors) => {
            uploading.value = false;
            uploadError.value = Object.values(errors).flat().join(', ');
        },
        forceFormData: true,
        preserveScroll: true,
    });
};

const deleteAttachment = (attachment) => {
    if (confirm(`Hapus lampiran "${attachment.original_name}"?`)) {
        router.delete(`/admin/announcements/attachments/${attachment.id}`, {
            preserveScroll: true,
        });
    }
};

const submit = () => {
    if (props.mode === 'create') {
        form.post('/admin/announcements');
    } else {
        form.put(`/admin/announcements/${props.announcement.id}`);
    }
};

// Auto-reset unit ID if scope changes away from 'unit'
const onScopeChange = () => {
    if (form.scope_type !== 'unit') {
        form.organization_unit_id = null;
    }
};
</script>

<template>
    <AppLayout :page-title="mode === 'create' ? 'Buat Pengumuman' : 'Edit Pengumuman'">

        <div class="max-w-4xl mx-auto py-10 sm:px-6 lg:px-8">
            <div v-if="Object.keys(form.errors || {}).length" class="mb-4 rounded-lg bg-red-50 border border-red-200 text-red-800 px-4 py-3 text-sm">
                Pengumuman gagal disimpan. Periksa input yang ditandai merah di bawah.
            </div>

            <form @submit.prevent="submit" class="bg-white shadow sm:rounded-lg p-6 space-y-6">
                
                <!-- Title -->
                <div>
                    <InputLabel for="title" value="Judul Pengumuman" class="font-semibold" />
                    <InputField 
                        id="title" 
                        v-model="form.title" 
                        type="text" 
                        class="mt-1 block w-full" 
                        placeholder="Contoh: Pengumuman Rapat Bulanan, Informasi Iuran, dll."
                        required 
                        autofocus 
                    />
                    <p class="text-xs text-gray-500 mt-1">Masukkan judul yang jelas dan deskriptif untuk pengumuman Anda.</p>
                    <InputError :message="form.errors.title" class="mt-2" />
                </div>

                <!-- Body -->
                <div>
                    <InputLabel for="body" value="Isi Pengumuman" class="font-semibold" />
                    <textarea 
                        id="body" 
                        v-model="form.body" 
                        class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm min-h-[150px]"
                        placeholder="Tulis isi pengumuman di sini. Anda dapat menggunakan format Markdown untuk pemformatan teks (misal: **tebal**, *miring*, dll.)"
                        required
                    ></textarea>
                    <p class="text-xs text-gray-500 mt-1">💡 Mendukung format Markdown sederhana untuk pemformatan teks.</p>
                    <InputError :message="form.errors.body" class="mt-2" />
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Scope Type -->
                    <div>
                        <InputLabel for="scope_type" value="Target Audience (Scope)" class="font-semibold" />
                        <select 
                            id="scope_type" 
                            v-model="form.scope_type" 
                             @change="onScopeChange"
                            class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                            :disabled="allowed_scopes.length === 1"
                        >
                            <option v-for="scope in allowed_scopes" :key="scope.value" :value="scope.value">
                                {{ scope.label }}
                            </option>
                        </select>
                        <InputError :message="form.errors.scope_type" class="mt-2" />
                        <p v-if="allowed_scopes.length === 1" class="text-xs text-gray-500 mt-1">
                            Anda hanya memiliki akses untuk scope ini.
                        </p>
                    </div>

                    <!-- Unit Selection (Only if scope is unit AND we have units list to choose from) -->
                    <div v-if="form.scope_type === 'unit' && units && units.length > 0">
                        <InputLabel for="organization_unit_id" value="Pilih Unit Organisasi" class="font-semibold" />
                        <select 
                            id="organization_unit_id" 
                            v-model="form.organization_unit_id" 
                            class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                            required
                        >
                            <option :value="null" disabled>-- Pilih Unit --</option>
                            <option v-for="unit in units" :key="unit.id" :value="unit.id">
                                {{ unit.name }}
                            </option>
                        </select>
                        <InputError :message="form.errors.organization_unit_id" class="mt-2" />
                    </div>

                    <div v-else-if="form.scope_type === 'unit'" class="md:col-span-1">
                        <InputLabel value="Unit Organisasi" class="font-semibold" />
                        <div class="mt-1 text-sm text-gray-700 bg-gray-50 border border-gray-200 rounded-md px-3 py-2">
                            Mengikuti unit akun Anda
                        </div>
                        <InputError :message="form.errors.organization_unit_id" class="mt-2" />
                        <p class="text-xs text-gray-500 mt-1">
                            Jika unit akun Anda belum diset, hubungi super admin untuk mengaitkan unit.
                        </p>
                    </div>
                </div>

                <!-- Toggles -->
                <div class="flex flex-col gap-4 border-t pt-4">
                    <label class="flex items-center">
                        <input type="checkbox" v-model="form.is_active" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                        <span class="ml-2 text-sm text-gray-600 font-medium">Aktifkan Pengumuman ini</span>
                    </label>

                    <label class="flex items-center">
                        <input type="checkbox" v-model="form.pin_to_dashboard" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                        <span class="ml-2 text-sm text-gray-600 font-medium">Pin ke Dashboard</span>
                    </label>
                    <p class="text-xs text-gray-500 ml-6 -mt-3">
                        Jika di-pin, pengumuman akan muncul di bagian atas Dashboard user yang sesuai target audience.
                    </p>
                </div>

                <!-- Attachments Section (Edit Mode Only) -->
                <div v-if="mode === 'edit'" class="border-t pt-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Lampiran (Attachments)</h3>
                    
                    <!-- Existing Attachments List -->
                    <div v-if="announcement.attachments && announcement.attachments.length > 0" class="space-y-2 mb-6">
                        <div v-for="file in announcement.attachments" :key="file.id" class="flex items-center justify-between p-3 bg-gray-50 rounded-lg border border-gray-100">
                            <div class="flex items-center gap-3 overflow-hidden">
                                <svg class="h-5 w-5 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" />
                                </svg>
                                <div>
                                    <p class="text-sm font-medium text-gray-700 truncate max-w-xs">{{ file.original_name }}</p>
                                    <p class="text-xs text-gray-500">{{ (file.size / 1024).toFixed(0) }} KB</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <a :href="file.download_url" class="text-xs text-indigo-600 hover:text-indigo-900 border border-indigo-200 px-2 py-1 rounded hover:bg-indigo-50" target="_blank">
                                    Download
                                </a>
                                <button type="button" @click="deleteAttachment(file)" class="text-xs text-red-600 hover:text-red-900 border border-red-200 px-2 py-1 rounded hover:bg-red-50">
                                    Hapus
                                </button>
                            </div>
                        </div>
                    </div>
                    <div v-else class="text-sm text-gray-500 italic mb-6">
                        Belum ada lampiran.
                    </div>

                    <!-- Upload Form -->
                     <div class="bg-gray-50 p-4 rounded-lg border border-gray-200">
                        <InputLabel for="attachments" value="Upload Lampiran Baru" />
                        <div class="mt-2 flex items-center gap-4">
                            <input 
                                type="file" 
                                id="attachments" 
                                ref="fileInput"
                                multiple
                                class="block w-full text-sm text-gray-500
                                file:mr-4 file:py-2 file:px-4
                                file:rounded-full file:border-0
                                file:text-sm file:font-semibold
                                file:bg-indigo-50 file:text-indigo-700
                                hover:file:bg-indigo-100"
                                @change="handleFileChange"
                            />
                            <PrimaryButton 
                                type="button" 
                                @click="uploadFiles" 
                                :disabled="uploading || !uploadFilesList.length"
                                :class="{ 'opacity-50': uploading || !uploadFilesList.length }"
                            >
                                {{ uploading ? 'Uploading...' : 'Upload' }}
                            </PrimaryButton>
                        </div>
                        <p class="text-xs text-gray-500 mt-2">
                            Max 5 file. Max 5MB per file. Format: PDF, Docs, Images.
                        </p>
                        <InputError :message="uploadError" class="mt-2" />
                    </div>
                </div>

                <div v-if="mode === 'create'" class="bg-blue-50 border border-blue-200 rounded-md p-4 mt-6">
                    <p class="text-sm text-blue-700">
                        📌 Tip: Anda dapat menambahkan lampiran setelah menyimpan pengumuman ini.
                    </p>
                </div>

                <div class="flex items-center justify-end mt-4 gap-3">
                    <button 
                        type="button" 
                        @click="history.back()"
                        class="px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:opacity-25 transition ease-in-out duration-150"
                    >
                        Batal
                    </button>
                    <PrimaryButton type="submit" :loading="form.processing" :disabled="form.processing">
                        {{ mode === 'create' ? 'Buat Pengumuman' : 'Simpan Perubahan' }}
                    </PrimaryButton>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
