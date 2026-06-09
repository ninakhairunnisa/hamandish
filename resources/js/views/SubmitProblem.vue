<script setup>
import { ref, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import api from '../api';

const router = useRouter();
const title = ref('');
const description = ref('');
const categoryId = ref('');
const categories = ref([]);
const submitting = ref(false);
const done = ref(false);
const error = ref(null);

onMounted(async () => {
    const { data } = await api.get('/categories');
    categories.value = data.data;
});

async function submit() {
    error.value = null;
    submitting.value = true;
    try {
        await api.post('/problems', {
            title: title.value,
            description: description.value,
            category_id: categoryId.value || null,
        });
        done.value = true;
        setTimeout(() => router.push({ name: 'feed' }), 1500);
    } catch (e) {
        error.value = e.response?.data?.message || 'خطا در ثبت مشکل.';
    } finally {
        submitting.value = false;
    }
}
</script>

<template>
    <div>
        <header class="sticky top-0 z-10 bg-white/95 px-5 py-4 backdrop-blur">
            <h1 class="text-center text-lg font-bold text-slate-800">ثبت مشکل جدید</h1>
        </header>

        <div v-if="done" class="px-6 py-16 text-center">
            <div class="mb-3 text-4xl">✅</div>
            <p class="text-slate-600">مشکل شما ثبت شد و پس از بررسی نمایش داده می‌شود.</p>
        </div>

        <form v-else class="space-y-4 px-4 py-4" @submit.prevent="submit">
            <input
                v-model="title"
                placeholder="عنوان مشکل"
                class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm outline-none focus:border-blue-400"
            />
            <select
                v-model="categoryId"
                class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm outline-none focus:border-blue-400"
            >
                <option value="">دسته‌بندی (اختیاری)</option>
                <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.title }}</option>
            </select>
            <textarea
                v-model="description"
                rows="6"
                placeholder="توضیح کامل مشکل…"
                class="w-full resize-none rounded-2xl border border-slate-200 px-4 py-3 text-sm outline-none focus:border-blue-400"
            ></textarea>

            <p v-if="error" class="text-sm text-rose-600">{{ error }}</p>

            <button
                type="submit"
                :disabled="submitting"
                class="w-full rounded-2xl bg-blue-600 py-3 font-semibold text-white shadow-lg shadow-blue-200 active:scale-95 disabled:opacity-50"
            >ثبت مشکل</button>
        </form>
    </div>
</template>
