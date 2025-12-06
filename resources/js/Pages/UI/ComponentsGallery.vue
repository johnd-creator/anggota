<template>
  <AppLayout page-title="UI Components Gallery">
    <div class="space-y-12">
      <!-- Buttons Section -->
      <section>
        <h2 class="text-2xl font-bold text-neutral-900 mb-6">Buttons</h2>
        
        <CardContainer>
          <div class="space-y-6">
            <!-- Primary Buttons -->
            <div>
              <h3 class="text-lg font-semibold mb-3">Primary Buttons</h3>
              <div class="flex flex-wrap gap-4">
                <PrimaryButton size="sm">Small</PrimaryButton>
                <PrimaryButton size="md">Medium</PrimaryButton>
                <PrimaryButton size="lg">Large</PrimaryButton>
                <PrimaryButton :loading="true">Loading</PrimaryButton>
                <PrimaryButton :disabled="true">Disabled</PrimaryButton>
              </div>
            </div>

            <!-- Secondary Buttons -->
            <div>
              <h3 class="text-lg font-semibold mb-3">Secondary Buttons</h3>
              <div class="flex flex-wrap gap-4">
                <SecondaryButton size="sm">Small</SecondaryButton>
                <SecondaryButton size="md">Medium</SecondaryButton>
                <SecondaryButton size="lg">Large</SecondaryButton>
                <SecondaryButton :disabled="true">Disabled</SecondaryButton>
              </div>
            </div>

            <!-- Icon Buttons -->
            <div>
              <h3 class="text-lg font-semibold mb-3">Icon Buttons</h3>
              <div class="flex flex-wrap gap-4">
                <IconButton aria-label="Settings" variant="ghost">
                  <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                  </svg>
                </IconButton>
                <IconButton aria-label="Delete" variant="outline">
                  <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                  </svg>
                </IconButton>
              </div>
            </div>
          </div>
        </CardContainer>
      </section>

      <!-- Form Inputs Section -->
      <section>
        <h2 class="text-2xl font-bold text-neutral-900 mb-6">Form Inputs</h2>
        
        <CardContainer>
          <div class="space-y-6">
            <InputField
              v-model="formData.name"
              label="Name"
              placeholder="Enter your name"
              helper="This is a helper text"
            />
            
            <InputField
              v-model="formData.email"
              type="email"
              label="Email"
              placeholder="you@example.com"
              error="This email is already taken"
            />
            
            <SelectField
              v-model="formData.role"
              label="Role"
              :options="roleOptions"
              placeholder="Select a role"
            />
            
            <ToggleSwitch
              v-model="formData.notifications"
              label="Enable notifications"
            />
            
            <Checkbox
              v-model="formData.terms"
              label="I agree to the terms and conditions"
              description="By checking this box, you agree to our Terms of Service and Privacy Policy."
            />
          </div>
        </CardContainer>
      </section>

      <!-- Alerts Section -->
      <section>
        <h2 class="text-2xl font-bold text-neutral-900 mb-6">Alerts</h2>
        
        <div class="space-y-4">
          <AlertBanner type="info" title="Information" message="This is an informational message." />
          <AlertBanner type="success" title="Success" message="Your changes have been saved successfully." />
          <AlertBanner type="warning" title="Warning" message="Please review your input before proceeding." />
          <AlertBanner type="error" title="Error" message="An error occurred while processing your request." />
          <AlertBanner type="info" :dismissible="true" message="This alert can be dismissed." @dismiss="() => {}" />
        </div>
      </section>

      <!-- Cards Section -->
      <section>
        <h2 class="text-2xl font-bold text-neutral-900 mb-6">Cards</h2>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <CardContainer shadow="md">
            <template #header>
              <h3 class="font-semibold">Card with Header</h3>
            </template>
            <p class="text-neutral-600">This is a card with a header and body content.</p>
          </CardContainer>

          <CardContainer shadow="lg" hoverable>
            <p class="text-neutral-600">This is a hoverable card with large shadow.</p>
            <template #footer>
              <div class="flex justify-end">
                <SecondaryButton size="sm">Action</SecondaryButton>
              </div>
            </template>
          </CardContainer>
        </div>
      </section>

      <!-- Modal Section -->
      <section>
        <h2 class="text-2xl font-bold text-neutral-900 mb-6">Modal</h2>
        
        <CardContainer>
          <PrimaryButton @click="showModal = true">Open Modal</PrimaryButton>
          
          <ModalBase v-model:show="showModal" title="Example Modal" size="md">
            <p class="text-neutral-600">This is a modal dialog. You can put any content here.</p>
            
            <template #footer>
              <div class="flex justify-end space-x-3">
                <SecondaryButton @click="showModal = false">Cancel</SecondaryButton>
                <PrimaryButton @click="showModal = false">Confirm</PrimaryButton>
              </div>
            </template>
          </ModalBase>
        </CardContainer>
      </section>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/UI/PrimaryButton.vue';
import SecondaryButton from '@/Components/UI/SecondaryButton.vue';
import IconButton from '@/Components/UI/IconButton.vue';
import InputField from '@/Components/UI/InputField.vue';
import SelectField from '@/Components/UI/SelectField.vue';
import ToggleSwitch from '@/Components/UI/ToggleSwitch.vue';
import Checkbox from '@/Components/UI/Checkbox.vue';
import CardContainer from '@/Components/UI/CardContainer.vue';
import AlertBanner from '@/Components/UI/AlertBanner.vue';
import ModalBase from '@/Components/UI/ModalBase.vue';

const formData = ref({
  name: '',
  email: '',
  role: '',
  notifications: false,
  terms: false,
});

const roleOptions = [
  { value: 'super_admin', label: 'Super Admin' },
  { value: 'admin_unit', label: 'Admin Unit' },
  { value: 'anggota', label: 'Anggota' },
  { value: 'reguler', label: 'Reguler' },
];

const showModal = ref(false);
</script>
