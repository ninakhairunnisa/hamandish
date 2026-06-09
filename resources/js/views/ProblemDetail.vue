<script setup>
import { ref, onMounted } from 'vue';
import api from '../api';
import { messenger } from '../messenger';

const props = defineProps({ id: String });

const problem = ref(null);
const solutions = ref([]);
const newSolution = ref('');
const loading = ref(true);
const submitting = ref(false);

async function load() {
    const [p, s] = await Promise.all([
        api.get(`/problems/${props.id}`),
        api.get(`/problems/${props.id}/solutions`),
    ]);
    problem.value = p.data.data ?? p.data;
    solutions.value = s.data.data;
    loading.value = false;
}

async function vote(solution, type) {
    messenger.haptic('light');
    try {
        const { data } = await api.post(`/solutions/${solution.id}/vote`, { type });
        solution.votes_count = data.votes_count;
    } catch (e) {
        // e.g. self-vote (422)
        alert(e.response?.data?.message || 'خطا در ثبت رأی.');
    }
}

async function toggleSupport() {
    const { data } = await api.post(`/problems/${problem.value.id}/support`);
    problem.value.supports_count = data.supports_count;
    problem.value.is_supported = data.supported;
}

async function addSolution() {
    if (!newSolution.value.trim()) return;
    submitting.value = true;
    try {
        const { data } = await api.post(`/problems/${problem.value.id}/solutions`, {
            content: newSolution.value,
        });
        solutions.value.unshift(data.data ?? data);
        newSolution.value = '';
    } finally {
        submitting.value = false;
    }
}

onMounted(load);
</script>

<template>
    <div v-if="loading" class="flex justify-center py-20">
        <div class="h-8 w-8 animate-spin rounded-full border-4 border-blue-200 border-t-blue-600"></div>
    </div>

    <div v-else class="px-4 py-4">
        <button class="mb-3 text-sm text-slate-500" @click="$router.back()">← بازگشت</button>

        <div class="rounded-3xl bg-white p-5 shadow-sm">
            <h1 class="mb-2 text-lg font-bold text-slate-800">{{ problem.title }}</h1>
            <p class="text-sm leading-7 text-slate-600">{{ problem.description }}</p>
            <div class="mt-4 flex items-center gap-3">
                <button
                    class="flex items-center gap-1 rounded-full px-4 py-2 text-sm font-semibold active:scale-95"
                    :class="problem.is_supported ? 'bg-blue-600 text-white' : 'bg-slate-100 text-slate-600'"
                    @click="toggleSupport"
                >
                    👥 حمایت ({{ problem.supports_count }})
                </button>
            </div>
        </div>

        <h2 class="mb-3 mt-6 font-bold text-slate-800">راه‌حل‌ها</h2>

        <div class="space-y-3">
            <div v-for="s in solutions" :key="s.id" class="rounded-2xl bg-white p-4 shadow-sm">
                <p class="text-sm leading-7 text-slate-700">{{ s.content }}</p>
                <div class="mt-3 flex items-center gap-2">
                    <button class="rounded-lg bg-emerald-50 px-3 py-1 text-emerald-600 active:scale-95" @click="vote(s, 1)">▲</button>
                    <span class="min-w-8 text-center font-bold text-slate-700">{{ s.votes_count }}</span>
                    <button class="rounded-lg bg-rose-50 px-3 py-1 text-rose-600 active:scale-95" @click="vote(s, -1)">▼</button>
                </div>
            </div>
            <p v-if="!solutions.length" class="text-sm text-slate-400">هنوز راه‌حلی ثبت نشده. اولین نفر باشید!</p>
        </div>

        <div class="mt-6 rounded-2xl bg-white p-4 shadow-sm">
            <textarea
                v-model="newSolution"
                rows="3"
                placeholder="راه‌حل خود را بنویسید…"
                class="w-full resize-none rounded-xl border border-slate-200 p-3 text-sm outline-none focus:border-blue-400"
            ></textarea>
            <button
                class="mt-2 w-full rounded-xl bg-blue-600 py-2.5 font-semibold text-white active:scale-95 disabled:opacity-50"
                :disabled="submitting"
                @click="addSolution"
            >ثبت راه‌حل</button>
        </div>
    </div>
</template>
