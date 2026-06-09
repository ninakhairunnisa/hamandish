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

onMounted(async () => {
    try {
        if (!auth.user) await auth.fetchMe();
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
            <div class="mb-6 flex items-center gap-4 rounded-3xl bg-white p-5 shadow-sm">
                <div class="flex h-16 w-16 items-center justify-center rounded-full bg-blue-100 text-2xl">👤</div>
                <div>
                    <p class="font-bold text-slate-800">{{ auth.user?.display_name || 'کاربر' }}</p>
                    <p class="text-sm text-slate-400" dir="ltr">{{ auth.user?.phone }}</p>
                    <span
                        v-if="auth.user?.label"
                        class="mt-1 inline-block rounded-full bg-indigo-600 px-2 py-0.5 text-[10px] font-semibold text-white"
                    >{{ auth.user.label }}</span>
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

                <h2 class="mb-3 mt-8 font-bold text-slate-800">نظرات من</h2>
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
                        هنوز نظری ثبت نکرده‌اید.
                    </p>
                </div>
            </template>
        </div>
    </div>
</template>
