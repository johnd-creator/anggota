<template>
  <AppLayout :page-title="isEditing ? 'Edit Organization Unit' : 'Create Organization Unit'">
    <div class="max-w-3xl mx-auto">
      <CardContainer padding="lg">
        <form @submit.prevent="submit" class="space-y-6">
          <!-- Code -->
          <InputField
            v-model="form.code"
            label="Unit Code"
            placeholder="e.g. 001"
            :error="form.errors.code"
            :disabled="isEditing"
            required
            helper="Must be a unique 3-digit number."
          />

          <!-- Name -->
          <InputField
            v-model="form.name"
            label="Unit Name"
            placeholder="e.g. Unit Jakarta Pusat"
            :error="form.errors.name"
            required
          />

          <!-- Address -->
          <div class="w-full">
            <label class="block text-sm font-medium text-neutral-700 mb-1">
              Address
            </label>
            <textarea
              v-model="form.address"
              rows="3"
              class="block w-full rounded-lg border border-neutral-300 px-3 py-2 text-neutral-900 placeholder-neutral-400 focus:border-brand-primary-500 focus:ring-brand-primary-500/20 focus:outline-none focus:ring-2 transition-colors duration-200"
              :class="{ 'border-status-error focus:border-status-error focus:ring-status-error/20': form.errors.address }"
              placeholder="Enter full address"
            ></textarea>
            <p v-if="form.errors.address" class="mt-1 text-sm text-status-error">
              {{ form.errors.address }}
            </p>
            <p v-else class="mt-1 text-sm text-neutral-500">
              Minimum 10 characters.
            </p>
          </div>

          <!-- Actions -->
          <div class="flex items-center justify-end space-x-4 pt-4 border-t border-neutral-200">
            <SecondaryButton @click="router.visit('/admin/units')">
              Cancel
            </SecondaryButton>
            <PrimaryButton type="submit" :loading="form.processing">
              {{ isEditing ? 'Update Unit' : 'Create Unit' }}
            </PrimaryButton>
          </div>
        </form>
      </CardContainer>
    </div>
  </AppLayout>
</template>

<script setup>
import { computed } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import CardContainer from '@/Components/UI/CardContainer.vue';
import InputField from '@/Components/UI/InputField.vue';
import PrimaryButton from '@/Components/UI/PrimaryButton.vue';
import SecondaryButton from '@/Components/UI/SecondaryButton.vue';

const props = defineProps({
  unit: {
    type: Object,
    default: null,
  },
});

const isEditing = computed(() => !!props.unit);

const form = useForm({
  code: props.unit?.code || '',
  name: props.unit?.name || '',
  address: props.unit?.address || '',
});

const submit = () => {
  if (isEditing.value) {
    form.put(`/admin/units/${props.unit.id}`);
  } else {
    form.post('/admin/units');
  }
};
</script>
