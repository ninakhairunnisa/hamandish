<script setup>
import { ref, onMounted, inject } from 'vue';
import { useRouter } from 'vue-router';
import api from '../api';

const gs = inject('globalSettings', { assembly_section_title: 'مشارکت در مجمع مردم مبعوث شده' });

const router = useRouter();

const loading   = ref(true);
const submitting = ref(false);
const error     = ref('');
const success   = ref(false);

const introMessage = ref('');
const availableRoles = ref([]);
const selectedRoles  = ref([]);
const description    = ref('');
const existing  = ref(null);   // current user's membership

onMounted(async () => {
    try {
        const [formData, myData] = await Promise.all([
            api.get('/assembly/form'),
            api.get('/assembly/my'),
        ]);
        introMessage.value   = formData.data.intro_message;
        availableRoles.value = formData.data.roles;
        if (myData.data) {
            existing.value      = myData.data;
            selectedRoles.value = myData.data.roles ?? [];
            description.value   = myData.data.description ?? '';
            if (['approved', 'recorded'].includes(myData.data.status)) {
                success.value = true;
            }
        }
    } catch (_) {
        error.value = 'خطا در بارگذاری اطلاعات.';
    } finally {
        loading.value = false;
    }
});

function toggleRole(id) {
    const idx = selectedRoles.value.indexOf(id);
    if (idx === -1) selectedRoles.value.push(id);
    else selectedRoles.value.splice(idx, 1);
}

async function submit() {
    if (!selectedRoles.value.length) {
        error.value = 'حداقل یک مسئولیت را انتخاب کنید.';
        return;
    }
    error.value   = '';
    submitting.value = true;
    try {
        const { data } = await api.post('/assembly', {
            roles: selectedRoles.value,
            description: description.value || null,
        });
        existing.value = data;
        success.value  = true;
    } catch (err) {
        error.value = err.response?.data?.message || 'خطا در ثبت. لطفاً دوباره تلاش کنید.';
    } finally {
        submitting.value = false;
    }
}

const statusLabel = {
    pending:  'در انتظار بررسی',
    approved: 'تأیید شده ✅',
    rejected: 'رد شده ❌',
    recorded: 'ثبت شده 📋',
};
</script>

<template>
    <div class="min-h-dvh bg-slate-50 pb-24">
        <header class="sticky top-0 z-10 flex items-center gap-3 bg-white/95 px-5 py-4 backdrop-blur shadow-sm">
            <button @click="router.back()" class="text-xl text-slate-500">←</button>
            <h1 class="font-bold text-slate-800">{{ gs.assembly_section_title ?? 'مشارکت در مجمع' }}</h1>
        </header>

        <div v-if="loading" class="flex justify-center py-20">
            <div class="h-8 w-8 animate-spin rounded-full border-4 border-blue-200 border-t-blue-600"></div>
        </div>

        <div v-else class="mx-auto max-w-lg space-y-5 px-4 py-5">

            <!-- Intro message from admin -->
            <div v-if="introMessage" class="rounded-2xl bg-blue-50 border border-blue-100 px-5 py-4 text-sm leading-7 text-blue-900 whitespace-pre-line">
                {{ introMessage }}
            </div>

            <!-- Success / already submitted -->
            <template v-if="existing && ['approved', 'recorded'].includes(existing.status)">
                <div class="rounded-2xl bg-green-50 border border-green-200 px-5 py-6 text-center space-y-2">
                    <div class="text-3xl">✅</div>
                    <p class="font-bold text-green-800">عضویت شما ثبت شده است</p>
                    <p class="text-sm text-green-700">وضعیت: <strong>{{ statusLabel[existing.status] }}</strong></p>
                </div>
            </template>

            <template v-else>

                <!-- Existing pending/rejected status notice -->
                <div v-if="existing" class="rounded-2xl border px-4 py-3 text-sm"
                     :class="existing.status === 'rejected' ? 'bg-red-50 border-red-200 text-red-800' : 'bg-amber-50 border-amber-200 text-amber-800'">
                    <strong>وضعیت قبلی:</strong> {{ statusLabel[existing.status] }}
                    <span v-if="existing.status === 'rejected'"> — می‌توانید فرم را ویرایش و دوباره ارسال کنید.</span>
                    <span v-else> — در حال بررسی است.</span>
                </div>

                <!-- Role selection -->
                <div class="space-y-2">
                    <p class="text-sm font-semibold text-slate-700">مسئولیت‌های مورد نظر را انتخاب کنید: <span class="text-red-500">*</span></p>
                    <div class="grid grid-cols-1 gap-2">
                        <label
                            v-for="role in availableRoles"
                            :key="role.id"
                            class="flex cursor-pointer items-center gap-3 rounded-xl border bg-white px-4 py-3 transition"
                            :class="selectedRoles.includes(role.id)
                                ? 'border-blue-500 bg-blue-50 text-blue-800'
                                : 'border-slate-200 text-slate-700'"
                        >
                            <input
                                type="checkbox"
                                class="h-4 w-4 rounded accent-blue-600"
                                :checked="selectedRoles.includes(role.id)"
                                @change="toggleRole(role.id)"
                            />
                            <span>{{ role.title }}</span>
                        </label>
                    </div>
                    <p v-if="!availableRoles.length" class="text-sm text-slate-400">هنوز مسئولیتی تعریف نشده است.</p>
                </div>

                <!-- Description -->
                <div class="space-y-2">
                    <label class="text-sm font-semibold text-slate-700">توضیحات بیشتر (اختیاری)</label>
                    <textarea
                        v-model="description"
                        rows="4"
                        placeholder="سوابق، تخصص، دلایل تمایل به عضویت و هر اطلاعات مفید دیگری را اینجا بنویسید..."
                        class="w-full resize-none rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm outline-none focus:border-blue-500"
                    ></textarea>
                </div>

                <!-- Error -->
                <p v-if="error" class="text-sm text-red-600">{{ error }}</p>

                <!-- Submit -->
                <button
                    @click="submit"
                    :disabled="submitting"
                    class="w-full rounded-2xl bg-blue-600 py-3 font-semibold text-white shadow-lg shadow-blue-200 active:scale-95 disabled:opacity-60"
                >
                    {{ submitting ? 'در حال ثبت…' : (existing ? 'به‌روزرسانی درخواست' : 'ثبت درخواست عضویت') }}
                </button>

            </template>
        </div>
    </div>
</template>
