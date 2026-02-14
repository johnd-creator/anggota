<template>
  <AppLayout :page-title="member ? 'Edit Member Profile' : 'Create Member'">
    <div class="min-h-screen py-6 px-4 sm:px-6 lg:px-8 relative">
      <!-- Animated Background Blobs -->
      <div class="fixed top-20 left-10 w-72 h-72 bg-blue-300 rounded-full mix-blend-multiply filter blur-3xl opacity-20 animate-blob pointer-events-none -z-10"></div>
      <div class="fixed top-20 right-10 w-72 h-72 bg-indigo-300 rounded-full mix-blend-multiply filter blur-3xl opacity-20 animate-blob animation-delay-2000 pointer-events-none -z-10"></div>
      <div class="fixed -bottom-32 left-1/3 w-72 h-72 bg-purple-300 rounded-full mix-blend-multiply filter blur-3xl opacity-20 animate-blob animation-delay-4000 pointer-events-none -z-10"></div>

      <!-- Back to List Button -->
      <div class="w-full max-w-5xl mx-auto mb-6 flex justify-start">
        <button type="button" @click="router.get('/admin/members')" class="group flex items-center text-sm font-medium text-slate-500 hover:text-[#1E3A8A] transition-colors">
          <span class="material-icons-round mr-1 text-lg group-hover:-translate-x-1 transition-transform">arrow_back</span>
          Back to List
        </button>
      </div>

      <!-- Main Form Container -->
      <main class="w-full max-w-5xl mx-auto bg-white rounded-2xl shadow-soft border border-slate-200/50 backdrop-blur-sm overflow-hidden">
        <!-- Header with Tabs -->
        <div class="px-8 pt-8 pb-6 border-b border-slate-100">
          <div class="flex flex-col md:flex-row md:items-center justify-between gap-6">
            <div>
              <h1 class="text-2xl font-bold text-slate-900">{{ member ? 'Edit Member Profile' : 'Create Member Profile' }}</h1>
              <p class="text-sm text-slate-500 mt-1">{{ member ? 'Update personal details and contact information.' : 'Fill in the member information below.' }}</p>
            </div>
            <div class="flex items-center space-x-2 text-sm font-medium">
              <button type="button" @click="setStep(1)" class="flex flex-col items-center relative group cursor-pointer">
                <span class="mb-1 transition-colors" :class="step === 1 ? 'text-[#1E3A8A] font-bold' : 'text-slate-400 hover:text-slate-600'">Data Personal</span>
                <div class="h-1 w-24 rounded-full transition-all" :class="step === 1 ? 'bg-[#1E3A8A] shadow-glow' : 'bg-slate-200 group-hover:bg-slate-300'"></div>
              </button>
              <button type="button" @click="setStep(2)" class="flex flex-col items-center relative group cursor-pointer">
                <span class="mb-1 transition-colors" :class="step === 2 ? 'text-[#1E3A8A] font-bold' : 'text-slate-400 hover:text-slate-600'">Data Organisasi</span>
                <div class="h-1 w-24 rounded-full transition-all" :class="step === 2 ? 'bg-[#1E3A8A] shadow-glow' : 'bg-slate-200 group-hover:bg-slate-300'"></div>
              </button>
              <button type="button" @click="setStep(3)" class="flex flex-col items-center relative group cursor-pointer">
                <span class="mb-1 transition-colors" :class="step === 3 ? 'text-[#1E3A8A] font-bold' : 'text-slate-400 hover:text-slate-600'">Dokumen</span>
                <div class="h-1 w-24 rounded-full transition-all" :class="step === 3 ? 'bg-[#1E3A8A] shadow-glow' : 'bg-slate-200 group-hover:bg-slate-300'"></div>
              </button>
            </div>
          </div>
        </div>

        <form @submit.prevent="submit" class="p-8 space-y-10">
          <!-- Step 1: Personal Identity -->
          <section v-show="step === 1">
            <div class="flex items-center gap-2 mb-6">
              <div class="p-2 bg-blue-50 rounded-lg text-[#1E3A8A]">
                <span class="material-icons-round text-xl">person</span>
              </div>
              <h2 class="text-lg font-semibold text-slate-800">Personal Identity</h2>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div class="col-span-1 md:col-span-2">
                <label class="block text-sm font-medium text-slate-700 mb-1.5" for="full_name">Full Name <span class="text-red-500">*</span></label>
                <input v-model="form.full_name" id="full_name" type="text" :disabled="submitting" required
                  class="w-full px-4 py-2.5 rounded-xl border border-slate-200 bg-slate-50 text-slate-900 placeholder-slate-400 focus:ring-2 focus:ring-[#1E3A8A]/20 focus:border-[#1E3A8A] transition-all duration-200 shadow-sm" />
                <p v-if="err('full_name')" class="mt-1.5 text-sm text-red-500">{{ err('full_name') }}</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-slate-700 mb-1.5" for="nip">NIP <span class="text-red-500">*</span></label>
                <input v-model="form.nip" id="nip" type="text" :disabled="submitting" required
                  class="w-full px-4 py-2.5 rounded-xl border border-slate-200 bg-slate-50 text-slate-900 placeholder-slate-400 focus:ring-2 focus:ring-[#1E3A8A]/20 focus:border-[#1E3A8A] transition-all duration-200 shadow-sm" />
                <p v-if="err('nip')" class="mt-1.5 text-sm text-red-500">{{ err('nip') }}</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-slate-700 mb-1.5" for="gender">Gender</label>
                <div class="relative">
                  <select v-model="form.gender" id="gender" :disabled="submitting"
                    class="w-full px-4 py-2.5 rounded-xl border border-slate-200 bg-slate-50 text-slate-900 focus:ring-2 focus:ring-[#1E3A8A]/20 focus:border-[#1E3A8A] appearance-none transition-all duration-200 shadow-sm cursor-pointer">
                    <option value="L">Laki-laki</option>
                    <option value="P">Perempuan</option>
                  </select>
                  <span class="material-icons-round absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none">expand_more</span>
                </div>
                <p v-if="err('gender')" class="mt-1.5 text-sm text-red-500">{{ err('gender') }}</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-slate-700 mb-1.5" for="birth_place">Birth Place</label>
                <input v-model="form.birth_place" id="birth_place" type="text" :disabled="submitting"
                  class="w-full px-4 py-2.5 rounded-xl border border-slate-200 bg-slate-50 text-slate-900 placeholder-slate-400 focus:ring-2 focus:ring-[#1E3A8A]/20 focus:border-[#1E3A8A] transition-all duration-200 shadow-sm" />
                <p v-if="err('birth_place')" class="mt-1.5 text-sm text-red-500">{{ err('birth_place') }}</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-slate-700 mb-1.5" for="birth_date">Birth Date</label>
                <div class="relative">
                  <input v-model="form.birth_date" id="birth_date" type="date" :disabled="submitting"
                    class="w-full px-4 py-2.5 rounded-xl border border-slate-200 bg-slate-50 text-slate-900 placeholder-slate-400 focus:ring-2 focus:ring-[#1E3A8A]/20 focus:border-[#1E3A8A] transition-all duration-200 shadow-sm" />
                </div>
                <p v-if="err('birth_date')" class="mt-1.5 text-sm text-red-500">{{ err('birth_date') }}</p>
              </div>
            </div>
          </section>

          <hr v-show="step === 1" class="border-slate-100">

          <!-- Contact Information -->
          <section v-show="step === 1">
            <div class="flex items-center gap-2 mb-6">
              <div class="p-2 bg-indigo-50 rounded-lg text-indigo-600">
                <span class="material-icons-round text-xl">contact_mail</span>
              </div>
              <h2 class="text-lg font-semibold text-slate-800">Contact Information</h2>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div>
                <label class="block text-sm font-medium text-slate-700 mb-1.5" for="email">Email Pribadi <span class="text-red-500">*</span></label>
                <input v-model="form.email" id="email" type="email" :disabled="submitting" required
                  class="w-full px-4 py-2.5 rounded-xl border border-slate-200 bg-slate-50 text-slate-900 placeholder-slate-400 focus:ring-2 focus:ring-[#1E3A8A]/20 focus:border-[#1E3A8A] transition-all duration-200 shadow-sm" />
                <p v-if="err('email')" class="mt-1.5 text-sm text-red-500">{{ err('email') }}</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-slate-700 mb-1.5" for="company_email">Email Perusahaan (Microsoft)</label>
                <input v-model="form.company_email" id="company_email" type="email" :disabled="submitting"
                  class="w-full px-4 py-2.5 rounded-xl border border-slate-200 bg-slate-50 text-slate-900 placeholder-slate-400 focus:ring-2 focus:ring-[#1E3A8A]/20 focus:border-[#1E3A8A] transition-all duration-200 shadow-sm" />
                <p v-if="err('company_email')" class="mt-1.5 text-sm text-red-500">{{ err('company_email') }}</p>
              </div>
              <div class="col-span-1 md:col-span-2">
                <label class="block text-sm font-medium text-slate-700 mb-1.5" for="phone">Phone</label>
                <input v-model="form.phone" id="phone" type="tel" :disabled="submitting"
                  class="w-full px-4 py-2.5 rounded-xl border border-slate-200 bg-slate-50 text-slate-900 placeholder-slate-400 focus:ring-2 focus:ring-[#1E3A8A]/20 focus:border-[#1E3A8A] transition-all duration-200 shadow-sm" />
                <p class="mt-1.5 text-xs text-slate-500">Format: 08123456789 atau +628123456789</p>
                <p v-if="err('phone')" class="mt-1.5 text-sm text-red-500">{{ err('phone') }}</p>
              </div>
              <div class="col-span-1 md:col-span-2">
                <label class="block text-sm font-medium text-slate-700 mb-1.5" for="address">Alamat / Domisili</label>
                <textarea v-model="form.address" id="address" rows="3" :disabled="submitting"
                  class="w-full px-4 py-2.5 rounded-xl border border-slate-200 bg-slate-50 text-slate-900 placeholder-slate-400 focus:ring-2 focus:ring-[#1E3A8A]/20 focus:border-[#1E3A8A] transition-all duration-200 shadow-sm resize-none"></textarea>
                <p v-if="err('address')" class="mt-1.5 text-sm text-red-500">{{ err('address') }}</p>
              </div>
              <div class="col-span-1 md:col-span-2">
                <label class="block text-sm font-medium text-slate-700 mb-1.5" for="emergency_contact">Emergency Contact</label>
                <input v-model="form.emergency_contact" id="emergency_contact" type="text" :disabled="submitting"
                  class="w-full px-4 py-2.5 rounded-xl border border-slate-200 bg-slate-50 text-slate-900 placeholder-slate-400 focus:ring-2 focus:ring-[#1E3A8A]/20 focus:border-[#1E3A8A] transition-all duration-200 shadow-sm" />
                <p v-if="err('emergency_contact')" class="mt-1.5 text-sm text-red-500">{{ err('emergency_contact') }}</p>
              </div>
            </div>
          </section>

          <!-- Step 2: Organization Data -->
          <section v-show="step === 2">
            <div class="flex items-center gap-2 mb-6">
              <div class="p-2 bg-emerald-50 rounded-lg text-emerald-600">
                <span class="material-icons-round text-xl">business</span>
              </div>
              <h2 class="text-lg font-semibold text-slate-800">Organization Data</h2>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div>
                <label class="block text-sm font-medium text-slate-700 mb-1.5" for="job_title">Job Title</label>
                <input v-model="form.job_title" id="job_title" type="text" :disabled="submitting"
                  class="w-full px-4 py-2.5 rounded-xl border border-slate-200 bg-slate-50 text-slate-900 placeholder-slate-400 focus:ring-2 focus:ring-[#1E3A8A]/20 focus:border-[#1E3A8A] transition-all duration-200 shadow-sm" />
                <p v-if="err('job_title')" class="mt-1.5 text-sm text-red-500">{{ err('job_title') }}</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-slate-700 mb-1.5" for="kta_number">Nomor KTA (Smart ID)</label>
                <input v-model="form.kta_number" id="kta_number" type="text" :disabled="submitting"
                  class="w-full px-4 py-2.5 rounded-xl border border-slate-200 bg-slate-50 text-slate-900 placeholder-slate-400 focus:ring-2 focus:ring-[#1E3A8A]/20 focus:border-[#1E3A8A] transition-all duration-200 shadow-sm" />
                <p v-if="err('kta_number')" class="mt-1.5 text-sm text-red-500">{{ err('kta_number') }}</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-slate-700 mb-1.5" for="union_position_id">Jabatan Serikat</label>
                <div class="relative">
                  <select v-model="form.union_position_id" id="union_position_id" :disabled="submitting"
                    class="w-full px-4 py-2.5 rounded-xl border border-slate-200 bg-slate-50 text-slate-900 focus:ring-2 focus:ring-[#1E3A8A]/20 focus:border-[#1E3A8A] appearance-none transition-all duration-200 shadow-sm cursor-pointer">
                    <option value="">Pilih Jabatan</option>
                    <option v-for="pos in positionOptions" :key="pos.value" :value="pos.value">{{ pos.label }}</option>
                  </select>
                  <span class="material-icons-round absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none">expand_more</span>
                </div>
                <p v-if="err('union_position_id')" class="mt-1.5 text-sm text-red-500">{{ err('union_position_id') }}</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-slate-700 mb-1.5" for="employment_type">Employment Type</label>
                <div class="relative">
                  <select v-model="form.employment_type" id="employment_type" :disabled="submitting"
                    class="w-full px-4 py-2.5 rounded-xl border border-slate-200 bg-slate-50 text-slate-900 focus:ring-2 focus:ring-[#1E3A8A]/20 focus:border-[#1E3A8A] appearance-none transition-all duration-200 shadow-sm cursor-pointer">
                    <option value="organik">Organik</option>
                    <option value="tkwt">TKWT</option>
                  </select>
                  <span class="material-icons-round absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none">expand_more</span>
                </div>
                <p v-if="err('employment_type')" class="mt-1.5 text-sm text-red-500">{{ err('employment_type') }}</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-slate-700 mb-1.5" for="status">Status</label>
                <div class="relative">
                  <select v-model="form.status" id="status" :disabled="submitting"
                    class="w-full px-4 py-2.5 rounded-xl border border-slate-200 bg-slate-50 text-slate-900 focus:ring-2 focus:ring-[#1E3A8A]/20 focus:border-[#1E3A8A] appearance-none transition-all duration-200 shadow-sm cursor-pointer">
                    <option v-for="st in statusOptions" :key="st.value" :value="st.value">{{ st.label }}</option>
                  </select>
                  <span class="material-icons-round absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none">expand_more</span>
                </div>
                <p v-if="err('status')" class="mt-1.5 text-sm text-red-500">{{ err('status') }}</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-slate-700 mb-1.5" for="join_date">Join Date <span class="text-red-500">*</span></label>
                <input v-model="form.join_date" id="join_date" type="date" :disabled="submitting" required
                  class="w-full px-4 py-2.5 rounded-xl border border-slate-200 bg-slate-50 text-slate-900 placeholder-slate-400 focus:ring-2 focus:ring-[#1E3A8A]/20 focus:border-[#1E3A8A] transition-all duration-200 shadow-sm" />
                <p v-if="err('join_date')" class="mt-1.5 text-sm text-red-500">{{ err('join_date') }}</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-slate-700 mb-1.5" for="company_join_date">Tanggal Gabung Perusahaan</label>
                <input v-model="form.company_join_date" id="company_join_date" type="date" :disabled="submitting"
                  class="w-full px-4 py-2.5 rounded-xl border border-slate-200 bg-slate-50 text-slate-900 placeholder-slate-400 focus:ring-2 focus:ring-[#1E3A8A]/20 focus:border-[#1E3A8A] transition-all duration-200 shadow-sm" />
                <p v-if="err('company_join_date')" class="mt-1.5 text-sm text-red-500">{{ err('company_join_date') }}</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-slate-700 mb-1.5" for="organization_unit_id">Organization Unit <span class="text-red-500">*</span></label>
                <div class="relative">
                  <select v-model="form.organization_unit_id" id="organization_unit_id" :disabled="submitting" required
                    class="w-full px-4 py-2.5 rounded-xl border border-slate-200 bg-slate-50 text-slate-900 focus:ring-2 focus:ring-[#1E3A8A]/20 focus:border-[#1E3A8A] appearance-none transition-all duration-200 shadow-sm cursor-pointer">
                    <option value="">Pilih Unit</option>
                    <option v-for="u in unitsOptions" :key="u.value" :value="u.value">{{ u.label }}</option>
                  </select>
                  <span class="material-icons-round absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none">expand_more</span>
                </div>
                <p v-if="err('organization_unit_id')" class="mt-1.5 text-sm text-red-500">{{ err('organization_unit_id') }}</p>
              </div>
              <div class="col-span-1 md:col-span-2">
                <label class="block text-sm font-medium text-slate-700 mb-1.5" for="notes">Notes</label>
                <textarea v-model="form.notes" id="notes" rows="3" :disabled="submitting"
                  class="w-full px-4 py-2.5 rounded-xl border border-slate-200 bg-slate-50 text-slate-900 placeholder-slate-400 focus:ring-2 focus:ring-[#1E3A8A]/20 focus:border-[#1E3A8A] transition-all duration-200 shadow-sm resize-none"></textarea>
                <p v-if="err('notes')" class="mt-1.5 text-sm text-red-500">{{ err('notes') }}</p>
              </div>
            </div>
          </section>

          <!-- Step 3: Documents -->
          <section v-show="step === 3">
            <div class="flex items-center gap-2 mb-6">
              <div class="p-2 bg-amber-50 rounded-lg text-amber-600">
                <span class="material-icons-round text-xl">description</span>
              </div>
              <h2 class="text-lg font-semibold text-slate-800">Documents</h2>
            </div>
            <div class="space-y-6">
              <div>
                <label class="block text-sm font-medium text-slate-700 mb-1.5">Photo</label>
                <input type="file" @change="onPhoto" accept="image/*" :disabled="submitting"
                  class="w-full px-4 py-2.5 rounded-xl border border-slate-200 bg-slate-50 text-slate-900 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-[#1E3A8A] hover:file:bg-blue-100" />
                <div v-if="photo" class="mt-2 text-sm text-slate-600">{{ photo.name }}</div>
                <div v-if="member?.photo_path" class="mt-2 flex items-center gap-2">
                  <img :src="`/storage/${member.photo_path}`" class="h-16 w-16 rounded-lg object-cover" />
                  <span class="text-xs text-slate-500">Current photo</span>
                </div>
              </div>
              <div>
                <label class="block text-sm font-medium text-slate-700 mb-1.5">Documents (pdf/jpg, max 2MB)</label>
                <input type="file" multiple @change="onDocs" accept=".pdf,image/*" :disabled="submitting"
                  class="w-full px-4 py-2.5 rounded-xl border border-slate-200 bg-slate-50 text-slate-900 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-[#1E3A8A] hover:file:bg-blue-100" />
                <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4">
                  <div v-for="(d, i) in documents" :key="i" class="border border-slate-200 rounded-xl p-4 bg-white">
                    <div class="flex items-center gap-3">
                      <img v-if="isImage(d)" :src="previewUrl(d)" class="h-12 w-12 rounded-lg object-cover" />
                      <span v-else class="material-icons-round text-slate-400 text-3xl">picture_as_pdf</span>
                      <div class="text-sm text-slate-900 truncate flex-1">{{ d.name }}</div>
                    </div>
                    <div class="mt-2 text-xs text-slate-500">{{ (d.size / 1024).toFixed(1) }} KB</div>
                    <div class="mt-3 flex justify-end">
                      <button type="button" @click="removeDoc(i)" :disabled="submitting"
                        class="text-sm text-red-500 hover:text-red-700 flex items-center gap-1">
                        <span class="material-icons-round text-base">delete</span>
                        Hapus
                      </button>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </section>
        </form>

        <!-- Footer Actions -->
        <div class="px-8 py-6 bg-slate-50 border-t border-slate-100 flex justify-between items-center">
          <button type="button" @click="prevStep" :disabled="step === 1 || submitting"
            class="px-6 py-2.5 rounded-xl text-sm font-medium text-slate-600 hover:bg-slate-200 transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-slate-400 disabled:opacity-50 disabled:cursor-not-allowed">
            Back
          </button>
          <div class="flex gap-3">
            <button v-if="step < 3" type="button" @click="nextStep" :disabled="submitting"
              class="px-6 py-2.5 rounded-xl text-sm font-medium text-[#1E3A8A] bg-white border border-[#1E3A8A]/20 hover:bg-blue-50 transition-all duration-200 shadow-sm focus:outline-none focus:ring-2 focus:ring-[#1E3A8A]/50 disabled:opacity-50 disabled:cursor-not-allowed">
              Next
            </button>
            <button type="submit" :disabled="submitting"
              class="px-6 py-2.5 rounded-xl text-sm font-medium text-white bg-[#1E3A8A] hover:bg-[#172554] shadow-lg shadow-[#1E3A8A]/30 transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#1E3A8A] disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2">
              <svg v-if="submitting" class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
              </svg>
              {{ member ? 'Update' : 'Save' }}
            </button>
          </div>
        </div>
      </main>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { router, usePage } from '@inertiajs/vue3';
import { reactive, ref, computed } from 'vue';

const page = usePage();
const member = page.props.member || null;
const units = page.props.units || [];
const unitsOptions = units.map(u => ({ label: u.name, value: u.id }));
const positions = page.props.positions || [];
const positionOptions = positions.map(p => ({ label: p.name, value: p.id }));
const statusOptions = [
  { label: 'Aktif', value: 'aktif' },
  { label: 'Cuti', value: 'cuti' },
  { label: 'Suspended', value: 'suspended' },
  { label: 'Resign', value: 'resign' },
  { label: 'Pensiun', value: 'pensiun' }
];

function toDateInput(value) {
  if (!value) return '';
  return typeof value === 'string' ? value.substring(0, 10) : value;
}

const form = reactive({
  full_name: member?.full_name || '',
  email: member?.email || '',
  company_email: member?.user?.company_email || '',
  phone: member?.phone || '',
  birth_place: member?.birth_place || '',
  birth_date: toDateInput(member?.birth_date),
  gender: member?.gender || 'L',
  address: member?.address || '',
  emergency_contact: member?.emergency_contact || '',
  job_title: member?.job_title || '',
  kta_number: member?.kta_number || '',
  nip: member?.nip || '',
  union_position_id: member?.union_position_id || '',
  employment_type: member?.employment_type || 'organik',
  status: member?.status || 'aktif',
  join_date: toDateInput(member?.join_date),
  company_join_date: toDateInput(member?.company_join_date),
  organization_unit_id: member?.organization_unit_id || '',
  notes: member?.notes || ''
});

let photo = null;
let documents = [];
const step = ref(1);
const errors = reactive({ nip: '' });
const progress = ref(0);
const submitting = ref(false);
const serverErrors = computed(() => page.props.errors || {});

function err(field) { return errors[field] || serverErrors.value[field] || ''; }

function onPhoto(e) { photo = e.target.files[0]; }
function onDocs(e) { documents = Array.from(e.target.files); }
function removeDoc(i) { documents.splice(i, 1); }
function nextStep() { if (step.value < 3) step.value++; }
function prevStep() { if (step.value > 1) step.value--; }
function setStep(n) { step.value = n; }
function isImage(file) { return /^image\/.+/.test(file.type); }
function previewUrl(file) { return URL.createObjectURL(file); }

function submit() {
  const data = new FormData();
  Object.entries(form).forEach(([k, v]) => data.append(k, v ?? ''));
  if (photo) data.append('photo', photo);
  documents.forEach(d => data.append('documents[]', d));

  if (!/^[a-zA-Z0-9]+$/.test(form.nip)) {
    errors.nip = 'NIP harus alfanumerik';
    step.value = 1;
    return;
  } else {
    errors.nip = '';
  }

  const options = {
    forceFormData: true,
    onStart() { submitting.value = true; },
    onProgress: (e) => { if (e && e.percentage) progress.value = e.percentage; },
    onFinish() { submitting.value = false; },
    onError(errs) {
      const personalFields = ['full_name', 'email', 'phone', 'birth_place', 'birth_date', 'address', 'emergency_contact', 'nip', 'gender'];
      const orgFields = ['job_title', 'kta_number', 'union_position_id', 'employment_type', 'status', 'join_date', 'company_join_date', 'organization_unit_id', 'notes'];
      const keys = Object.keys(errs || {});
      if (keys.some(k => personalFields.includes(k))) step.value = 1;
      else if (keys.some(k => orgFields.includes(k))) step.value = 2;
    }
  };
  if (member) {
    data.append('_method', 'PUT');
    router.post(`/admin/members/${member.id}`, data, options);
  } else {
    router.post('/admin/members', data, options);
  }
}
</script>

<style scoped>
@keyframes blob {
  0% { transform: translate(0px, 0px) scale(1); }
  33% { transform: translate(30px, -50px) scale(1.1); }
  66% { transform: translate(-20px, 20px) scale(0.9); }
  100% { transform: translate(0px, 0px) scale(1); }
}

.animate-blob {
  animation: blob 7s infinite;
}

.animation-delay-2000 {
  animation-delay: 2s;
}

.animation-delay-4000 {
  animation-delay: 4s;
}

.shadow-glow {
  box-shadow: 0 0 15px rgba(30, 58, 138, 0.15);
}

.shadow-soft {
  box-shadow: 0 4px 20px -2px rgba(0, 0, 0, 0.05);
}
</style>
