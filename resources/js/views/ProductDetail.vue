<script setup>
import { ref, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import api from '../api';
import { useCartStore } from '../stores/cart';
import { toman } from '../money';

defineOptions({ name: 'ProductDetail' });

const route  = useRoute();
const router = useRouter();
const cart   = useCartStore();

const product = ref(null);
const loading = ref(true);
const qty     = ref(1);
const added   = ref(false);

onMounted(async () => {
    try {
        const { data } = await api.get(`/shop/products/${route.params.id}`);
        product.value = data;
    } catch (_) {
        router.replace({ name: 'shop' });
    } finally {
        loading.value = false;
    }
});

function addToCart() {
    cart.add(product.value, qty.value);
    added.value = true;
    setTimeout(() => (added.value = false), 1800);
}
</script>

<template>
    <div class="min-h-dvh bg-slate-50 pb-24">
        <header class="sticky top-0 z-10 flex items-center gap-3 bg-white/95 px-5 py-4 backdrop-blur shadow-sm">
            <button @click="router.back()" class="text-xl text-slate-500">←</button>
            <h1 class="font-bold text-slate-800">جزئیات محصول</h1>
        </header>

        <div v-if="loading" class="flex justify-center py-20">
            <div class="h-8 w-8 animate-spin rounded-full border-4 border-blue-200 border-t-blue-600"></div>
        </div>

        <div v-else-if="product" class="space-y-4">
            <div class="aspect-square bg-slate-100">
                <img v-if="product.image_url" :src="product.image_url" :alt="product.name" class="h-full w-full object-cover" />
                <div v-else class="flex h-full items-center justify-center text-6xl text-slate-300">📦</div>
            </div>

            <div class="mx-4 space-y-3 rounded-3xl bg-white p-5 shadow-sm">
                <h2 class="text-lg font-bold text-slate-800">{{ product.name }}</h2>
                <p class="text-xl font-extrabold text-blue-600">{{ toman(product.price) }}</p>
                <p v-if="product.category" class="inline-block rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-500">{{ product.category.title }}</p>
                <p v-if="product.description" class="whitespace-pre-line text-sm leading-7 text-slate-600">{{ product.description }}</p>
                <p class="text-xs" :class="product.in_stock ? 'text-emerald-600' : 'text-rose-600'">
                    {{ product.in_stock ? `موجود (${product.stock} عدد)` : 'ناموجود' }}
                </p>
            </div>

            <div v-if="product.in_stock" class="mx-4 flex items-center gap-3">
                <div class="flex items-center rounded-xl border border-slate-200 bg-white">
                    <button class="px-3 py-2 text-lg text-slate-500" @click="qty = Math.max(1, qty - 1)">−</button>
                    <span class="w-10 text-center text-sm font-bold">{{ qty }}</span>
                    <button class="px-3 py-2 text-lg text-slate-500" @click="qty = Math.min(product.stock, qty + 1)">+</button>
                </div>
                <button class="flex-1 rounded-xl bg-blue-600 py-3 text-sm font-semibold text-white active:scale-95" @click="addToCart">
                    {{ added ? '✅ به سبد اضافه شد' : '🛒 افزودن به سبد خرید' }}
                </button>
            </div>
        </div>
    </div>
</template>
