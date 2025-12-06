<template>
  <AppLayout page-title="Edit Role">
    <CardContainer padding="lg" shadow="sm">
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <InputField v-model="form.label" label="Label" />
        <InputField v-model="form.description" label="Deskripsi" />
        <InputField v-model="domains" label="Domain Whitelist (pisahkan koma)" />
      </div>
      <div class="mt-4 flex justify-between items-center">
        <SecondaryButton @click="back">Kembali</SecondaryButton>
        <div class="flex items-center gap-3">
          <SecondaryButton @click="submit">Simpan</SecondaryButton>
          <SecondaryButton :disabled="role.users_count>0" @click="hapus">Hapus</SecondaryButton>
        </div>
      </div>
    </CardContainer>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import CardContainer from '@/Components/UI/CardContainer.vue';
import InputField from '@/Components/UI/InputField.vue';
import SecondaryButton from '@/Components/UI/SecondaryButton.vue';
import { reactive, ref } from 'vue';
import { usePage, router } from '@inertiajs/vue3';
const page = usePage();
const role = page.props.role;
const form = reactive({ label: role.label || '', description: role.description || '' });
const domains = ref((role.domain_whitelist||[]).join(','));
function back(){ router.get('/admin/roles'); }
function submit(){ const payload = { ...form, domain_whitelist: domains.value ? domains.value.split(',').map(s=>s.trim()).filter(Boolean) : [] }; router.put(`/admin/roles/${role.id}`, payload); }
function hapus(){ if (confirm('Yakin hapus role?')) router.delete(`/admin/roles/${role.id}`); }
</script>

