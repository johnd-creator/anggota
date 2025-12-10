<template>
  <AppLayout page-title="Kategori Aspirasi">
    <div class="max-w-3xl mx-auto space-y-6">
      
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
        <table class="min-w-full divide-y divide-neutral-200">
          <thead class="bg-neutral-50">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Nama</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Deskripsi</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Jumlah Aspirasi</th>
              <th class="px-6 py-3 text-right text-xs font-medium text-neutral-500 uppercase tracking-wider">Aksi</th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-neutral-200">
            <tr v-for="cat in categories" :key="cat.id" class="hover:bg-neutral-50">
              <td class="px-6 py-4">
                <input
                  v-if="editing === cat.id"
                  v-model="editForm.name"
                  type="text"
                  class="border border-neutral-300 rounded px-2 py-1 text-sm w-full"
                />
                <span v-else class="text-sm font-medium text-neutral-900">{{ cat.name }}</span>
              </td>
              <td class="px-6 py-4">
                <input
                  v-if="editing === cat.id"
                  v-model="editForm.description"
                  type="text"
                  class="border border-neutral-300 rounded px-2 py-1 text-sm w-full"
                />
                <span v-else class="text-sm text-neutral-600">{{ cat.description || '-' }}</span>
              </td>
              <td class="px-6 py-4 text-sm text-neutral-600">{{ cat.aspirations_count }}</td>
              <td class="px-6 py-4 text-right">
                <template v-if="editing === cat.id">
                  <button @click="saveEdit(cat)" class="text-green-600 hover:text-green-700 text-sm font-medium mr-2">Simpan</button>
                  <button @click="cancelEdit" class="text-neutral-500 hover:text-neutral-700 text-sm">Batal</button>
                </template>
                <template v-else>
                  <button @click="startEdit(cat)" class="text-blue-600 hover:text-blue-700 text-sm font-medium mr-2">Edit</button>
                  <button @click="deleteCategory(cat)" :disabled="cat.aspirations_count > 0" :class="['text-sm font-medium', cat.aspirations_count > 0 ? 'text-neutral-300 cursor-not-allowed' : 'text-red-600 hover:text-red-700']">
                    Hapus
                  </button>
                </template>
              </td>
            </tr>
            <tr v-if="categories.length === 0">
              <td colspan="4" class="px-6 py-10 text-center text-neutral-500">Belum ada kategori</td>
            </tr>
          </tbody>
        </table>
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

const props = defineProps({
  categories: Array,
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
