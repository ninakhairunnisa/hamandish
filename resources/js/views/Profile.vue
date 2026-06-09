<script setup>
import { ref, onMounted } from 'vue';
import api from '../api';
import { useAuthStore } from '../stores/auth';
import ProblemCard from '../components/ProblemCard.vue';
import { timeAgo } from '../time';

const auth = useAuthStore();
const myProblems = ref([]);
const myComments = ref([]);
const loading = ref(true);
const firstName = ref('');
const lastName = ref('');
const showName = ref(false);
const saving = ref(false);
const savedMsg = ref(false);

async function saveProfile() {
    saving.value = true;
    try {
        const { data } = await api.post('/profile', {
            first_name: firstName.value || null,
            last_name: lastName.value || null,
            show_name: showName.value,
        });
        auth.user = data.data ?? data;
        savedMsg.value = true;
        setTimeout(() => (savedMsg.value = false), 2000);
    } finally {
        saving.value = false;
    }
}

onMounted(async () => {
    try {
        if (!auth.user) await auth.fetchMe();
        firstName.value = auth.user?.first_name || '';
        lastName.value = auth.user?.last_name || '';
        showName.value = !!auth.user?.show_name;
        const [p, c] = await Promise.allSettled([
            api.get('/profile/problems'),
            api.get('/profile/comments'),
        ]);
        if (p.status === 'fulfilled') myProblems.value = p.value.data.data;
        if (c.status === 'fulfilled') myComments.value = c.value.data.data;
    } finally {
        loading.value = false;
    }
});
</script>

<template>
    <div>
        <header class="sticky top-0 z-10 bg-white/95 px-5 py-4 backdrop-blur">
            <h1 class="text-center text-lg font-bold text-slate-800">پروفایل</h1>
        </header>

        <div class="px-4 py-4 pb-28">
            <div class="mb-6 rounded-3xl bg-white p-5 shadow-sm">
                <div class="flex items-center gap-4">
                    <div class="flex h-16 w-16 items-center justify-center rounded-full bg-blue-100 text-2xl">👤</div>
                    <div>
                        <p class="font-bold text-slate-800" dir="auto">{{ auth.user?.display_name || 'کاربر' }}</p>
                        <p class="text-sm text-slate-400" dir="ltr">{{ auth.user?.phone }}</p>
                        <span
                            v-if="auth.user?.label"
                            class="mt-1 inline-block rounded-full bg-indigo-600 px-2 py-0.5 text-[10px] font-semibold text-white"
                        >{{ auth.user.label }}</span>
                    </div>
                </div>

                <div class="mt-4 space-y-3 border-t border-slate-100 pt-4">
                    <div class="grid grid-cols-2 gap-2">
                        <input
                            v-model.trim="firstName"
                            placeholder="نام"
                            class="rounded-xl border border-slate-200 px-3 py-2 text-sm outline-none focus:border-blue-400"
                        />
                        <input
                            v-model.trim="lastName"
                            placeholder="نام خانوادگی"
                            class="rounded-xl border border-slate-200 px-3 py-2 text-sm outline-none focus:border-blue-400"
                        />
                    </div>
                    <label class="flex items-center justify-between text-sm text-slate-600">
                        <span>نمایش نام من به‌جای شماره ماسک‌شده</span>
                        <input v-model="showName" type="checkbox" class="h-5 w-5 accent-blue-600" />
                    </label>
                    <p class="text-xs text-slate-400">
                        در صورت غیرفعال بودن، نام شما به‌صورت
                        <span dir="ltr">{{ (auth.user?.phone || '0912xxxxx89').slice(0, 4) }}*****{{ (auth.user?.phone || '').slice(-2) }}</span>
                        نمایش داده می‌شود.
                    </p>
                    <button
                        class="w-full rounded-xl bg-blue-600 py-2 text-sm font-semibold text-white active:scale-95 disabled:opacity-60"
                        :disabled="saving"
                        @click="saveProfile"
                    >{{ savedMsg ? 'ذخیره شد ✅' : 'ذخیره' }}</button>
                </div>
            </div>

            <div v-if="loading" class="flex justify-center py-10">
                <div class="h-8 w-8 animate-spin rounded-full border-4 border-blue-200 border-t-blue-600"></div>
            </div>

            <template v-else>
                <h2 class="mb-3 font-bold text-slate-800">مشکلات من</h2>
                <div class="space-y-3">
                    <ProblemCard v-for="p in myProblems" :key="p.id" :problem="p" />
                    <p v-if="!myProblems.length" class="py-4 text-center text-sm text-slate-400">
                        هنوز مشکلی ثبت نکرده‌اید.
                    </p>
                </div>

                <h2 class="mb-3 mt-8 font-bold text-slate-800">پاسخ‌های من</h2>
                <div class="space-y-3">
                    <router-link
                        v-for="c in myComments"
                        :key="c.id"
                        :to="{ name: 'problem', params: { id: c.problem_id }, query: { comment: c.id } }"
                        class="block rounded-2xl bg-white p-4 shadow-sm active:scale-[0.99]"
                    >
                        <p class="line-clamp-2 text-sm text-slate-700">{{ c.content }}</p>
                        <p class="mt-2 text-xs text-slate-400">
                            در «{{ c.problem_title }}» · {{ timeAgo(c.created_at) }}
                            <span v-if="c.edited_at" class="text-[10px]">(ویرایش‌شده {{ timeAgo(c.edited_at) }})</span>
                        </p>
                    </router-link>
                    <p v-if="!myComments.length" class="py-4 text-center text-sm text-slate-400">
                        هنوز پاسخی ثبت نکرده‌اید.
                    </p>
                </div>
            </template>
        </div>
    </div>
</template>
