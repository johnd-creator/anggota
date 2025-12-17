<template>
  <AppLayout page-title="Kategori Aspirasi">
    <div class="space-y-6">
      
      <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h2 class="text-lg font-semibold text-neutral-900">Kategori Aspirasi</h2>
          <p class="text-sm text-neutral-500">Kelola kategori untuk pengelompokan aspirasi anggota.</p>
        </div>
        <div class="flex flex-wrap gap-3">
          <CtaButton @click="toggleCreate">
             <template #icon>
              <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            </template>
            Tambah Kategori
          </CtaButton>
        </div>
      </div>

      <!-- Add Form -->
      <transition name="fade">
      <CardContainer padding="lg" v-if="showCreate">
        <div class="flex justify-between items-center mb-4">
           <h2 class="text-lg font-semibold text-neutral-900">Tambah Kategori Baru</h2>
           <button @click="showCreate=false" class="text-neutral-400 hover:text-neutral-600"><svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button>
        </div>
        <form @submit.prevent="createCategory" class="flex flex-col sm:flex-row gap-3">
          <input
            v-model="createForm.name"
            type="text"
            placeholder="Nama kategori"
            class="flex-1 border border-neutral-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
          />
          <input
            v-model="createForm.description"
            type="text"
            placeholder="Deskripsi (opsional)"
            class="flex-1 border border-neutral-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
          />
          <button type="submit" :disabled="createForm.processing || !createForm.name" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition text-sm font-medium disabled:opacity-50 whitespace-nowrap">
            Simpan
          </button>
        </form>
        <p v-if="createForm.errors.name" class="text-red-500 text-xs mt-1">{{ createForm.errors.name }}</p>
      </CardContainer>
      </transition>

      <!-- Categories List -->
      <CardContainer padding="none" class="overflow-hidden">
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-neutral-200">
            <thead class="bg-neutral-50">
              <tr>
                <th class="px-5 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Nama</th>
                <th class="px-5 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Deskripsi</th>
                <th class="px-5 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Jumlah Aspirasi</th>
                <th class="px-5 py-3 text-right text-xs font-medium text-neutral-500 uppercase tracking-wider">Aksi</th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-neutral-200">
              <tr v-for="cat in categories.data" :key="cat.id" class="hover:bg-neutral-50">
                <td class="px-5 py-3">
                  <input
                    v-if="editing === cat.id"
                    v-model="editForm.name"
                    type="text"
                    class="border border-neutral-300 rounded px-2 py-1 text-sm w-full"
                  />
                  <span v-else class="text-sm font-medium text-neutral-900">{{ cat.name }}</span>
                </td>
                <td class="px-5 py-3">
                  <input
                    v-if="editing === cat.id"
                    v-model="editForm.description"
                    type="text"
                    class="border border-neutral-300 rounded px-2 py-1 text-sm w-full"
                  />
                  <span v-else class="text-sm text-neutral-600">{{ cat.description || '-' }}</span>
                </td>
                <td class="px-5 py-3 text-sm text-neutral-600">{{ cat.aspirations_count }}</td>
                <td class="px-5 py-3 text-right">
                  <template v-if="editing === cat.id">
                    <div class="flex justify-end gap-2">
                      <PrimaryButton size="sm" @click="saveEdit(cat)">Simpan</PrimaryButton>
                      <SecondaryButton size="sm" @click="cancelEdit">Batal</SecondaryButton>
                    </div>
                  </template>
                  <template v-else>
                    <div class="flex justify-end gap-2">
                      <IconButton :aria-label="`Edit ${cat.name}`" size="sm" @click="startEdit(cat)">
                        <svg class="w-5 h-5 text-brand-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                        </svg>
                      </IconButton>
                      <IconButton :aria-label="`Hapus ${cat.name}`" size="sm" :disabled="cat.aspirations_count > 0" @click="deleteCategory(cat)">
                        <svg :class="['w-5 h-5', cat.aspirations_count > 0 ? 'text-neutral-300' : 'text-status-error']" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                      </IconButton>
                    </div>
                  </template>
                </td>
              </tr>
              <tr v-if="categories.data.length === 0">
                <td colspan="4" class="px-5 py-10 text-center text-neutral-500">Belum ada kategori</td>
              </tr>
            </tbody>
          </table>
        </div>
        <Pagination :paginator="categories" />
      </CardContainer>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

import CardContainer from '@/Components/UI/CardContainer.vue';
import CtaButton from '@/Components/UI/CtaButton.vue';
import Pagination from '@/Components/UI/Pagination.vue';
import IconButton from '@/Components/UI/IconButton.vue';
import PrimaryButton from '@/Components/UI/PrimaryButton.vue';
import SecondaryButton from '@/Components/UI/SecondaryButton.vue';

const props = defineProps({
  categories: Object,
});

const showCreate = ref(false);
function toggleCreate(){ showCreate.value = !showCreate.value; }
function createCategory(){ 
    createForm.post('/admin/aspiration-categories', {
      onSuccess: () => { createForm.reset(); showCreate.value=false; },
    });
}

const showModal = ref(false);
const editingCategory = ref(null);

const createForm = useForm({
  name: '',
  description: '',
});

const editForm = useForm({
  name: '',
  description: '',
});

function openCreateModal() {
  editingCategory.value = null;
  createForm.reset();
  showModal.value = true;
}

function openEditModal(cat) {
  editingCategory.value = cat;
  editForm.name = cat.name;
  editForm.description = cat.description;
  showModal.value = true;
}

function submit() {
  if (editingCategory.value) {
    editForm.patch(`/admin/aspiration-categories/${editingCategory.value.id}`, {
      onSuccess: () => showModal.value = false,
    });
  } else {
    createForm.post('/admin/aspiration-categories', {
      onSuccess: () => showModal.value = false,
    });
  }
}

function deleteCategory(cat) {
  if (!confirm('Hapus kategori ini?')) return;
  router.delete(`/admin/aspiration-categories/${cat.id}`, {
    preserveScroll: true,
  });
}
</script>
