<script setup>
import { ref, onMounted } from 'vue';
import api from '../api';
import ProblemCard from '../components/ProblemCard.vue';

const problems = ref([]);
const loading = ref(true);
const search = ref('');

async function load() {
    loading.value = true;
    const { data } = await api.get('/problems', { params: { sort: 'popular', search: search.value || undefined } });
    problems.value = data.data;
    loading.value = false;
}

onMounted(load);
</script>

<template>
    <div>
        <header class="sticky top-0 z-10 bg-white/95 px-5 py-4 backdrop-blur">
            <h1 class="text-center text-lg font-bold text-slate-800">مشکلات مردمی</h1>
        </header>

        <div class="px-4 py-3">
            <input
                v-model="search"
                placeholder="جستجو…"
                class="mb-4 w-full rounded-2xl border border-slate-200 px-4 py-2.5 text-sm outline-none focus:border-blue-400"
                @keyup.enter="load"
            />

            <div v-if="loading" class="flex justify-center py-16">
                <div class="h-8 w-8 animate-spin rounded-full border-4 border-blue-200 border-t-blue-600"></div>
            </div>
            <div v-else class="space-y-3">
                <ProblemCard v-for="p in problems" :key="p.id" :problem="p" />
                <p v-if="!problems.length" class="py-10 text-center text-sm text-slate-400">موردی یافت نشد.</p>
            </div>
        </div>
    </div>
</template>
