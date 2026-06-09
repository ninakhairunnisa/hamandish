<script setup>
import { ref, onMounted } from 'vue';
import api from '../api';
import { useAuthStore } from '../stores/auth';
import ProblemCard from '../components/ProblemCard.vue';

const auth = useAuthStore();
const myProblems = ref([]);
const loading = ref(true);

onMounted(async () => {
    try {
        if (!auth.user) await auth.fetchMe();
        const { data } = await api.get('/profile/problems');
        myProblems.value = data.data;
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

        <div class="px-4 py-4">
            <div class="mb-6 flex items-center gap-4 rounded-3xl bg-white p-5 shadow-sm">
                <div class="flex h-16 w-16 items-center justify-center rounded-full bg-blue-100 text-2xl">👤</div>
                <div>
                    <p class="font-bold text-slate-800">
                        {{ auth.user?.first_name || 'کاربر' }} {{ auth.user?.last_name || '' }}
                    </p>
                    <p class="text-sm text-slate-400">{{ auth.user?.phone }}</p>
                </div>
            </div>

            <h2 class="mb-3 font-bold text-slate-800">مشکلات من</h2>
            <div v-if="loading" class="flex justify-center py-10">
                <div class="h-8 w-8 animate-spin rounded-full border-4 border-blue-200 border-t-blue-600"></div>
            </div>
            <div v-else class="space-y-3">
                <ProblemCard v-for="p in myProblems" :key="p.id" :problem="p" />
                <p v-if="!myProblems.length" class="py-6 text-center text-sm text-slate-400">هنوز مشکلی ثبت نکرده‌اید.</p>
            </div>
        </div>
    </div>
</template>
