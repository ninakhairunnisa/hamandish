<script setup>
import { ref, onMounted } from 'vue';
import api from '../api';
import ProblemCard from '../components/ProblemCard.vue';

defineOptions({ name: 'Feed' });

const featured = ref([]);
const popular = ref([]);
const loading = ref(true);

onMounted(async () => {
    try {
        const [f, p] = await Promise.all([
            api.get('/problems/featured'),
            api.get('/problems/popular'),
        ]);
        featured.value = f.data.data;
        popular.value = p.data.data;
    } finally {
        loading.value = false;
    }
});
</script>

<template>
    <div>
        <header class="sticky top-0 z-10 flex items-center justify-between bg-white/95 px-5 py-4 backdrop-blur">
            <span class="text-xl">🔔</span>
            <h1 class="flex items-center gap-2 text-lg font-bold text-slate-800">🗣️ هم‌اندیش</h1>
            <span class="text-xl">👤</span>
        </header>

        <div v-if="loading" class="flex justify-center py-20">
            <div class="h-8 w-8 animate-spin rounded-full border-4 border-blue-200 border-t-blue-600"></div>
        </div>

        <div v-else class="space-y-6 px-4 py-4">
            <section>
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="font-bold text-slate-800">🔖 مشکلات برگزیده</h2>
                </div>
                <p v-if="!featured.length" class="text-sm text-slate-400">فعلاً مشکل برگزیده‌ای نیست.</p>
                <div class="space-y-3">
                    <ProblemCard v-for="p in featured" :key="p.id" :problem="p" />
                </div>
            </section>

            <section>
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="font-bold text-slate-800">مشکلات پیشنهادی کاربران</h2>
                </div>
                <div class="space-y-3">
                    <ProblemCard v-for="p in popular" :key="p.id" :problem="p" />
                </div>
            </section>
        </div>
    </div>
</template>
