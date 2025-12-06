<template>
  <AppLayout page-title="Tambah Role">
    <CardContainer padding="lg" shadow="sm">
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <InputField v-model="form.name" label="Name" />
        <InputField v-model="form.label" label="Label" />
        <InputField v-model="form.description" label="Deskripsi" />
        <InputField v-model="domains" label="Domain Whitelist (pisahkan koma)" />
      </div>
      <div class="mt-4 flex justify-end gap-3">
        <SecondaryButton @click="back">Batal</SecondaryButton>
        <PrimaryButton @click="submit">Simpan</PrimaryButton>
      </div>
    </CardContainer>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import CardContainer from '@/Components/UI/CardContainer.vue';
import InputField from '@/Components/UI/InputField.vue';
import PrimaryButton from '@/Components/UI/PrimaryButton.vue';
import SecondaryButton from '@/Components/UI/SecondaryButton.vue';
import { reactive, ref } from 'vue';
import { router } from '@inertiajs/vue3';
const form = reactive({ name:'', label:'', description:'' });
const domains = ref('');
function back(){ router.get('/admin/roles'); }
function submit(){ 
  const payload = { ...form };
  if (domains.value) payload.domain_whitelist = domains.value.split(',').map(s=>s.trim()).filter(Boolean);
  router.post('/admin/roles', payload);
}
</script>

