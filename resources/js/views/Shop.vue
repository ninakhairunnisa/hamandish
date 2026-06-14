<script setup>
import { ref, onMounted, inject } from 'vue';
import { useRouter } from 'vue-router';
import api from '../api';
import { useCartStore } from '../stores/cart';
import { toman } from '../money';

defineOptions({ name: 'Shop' });

const router = useRouter();
const cart   = useCartStore();
const gs     = inject('globalSettings', { shop_nav_label: 'فروشگاه' });

const products   = ref([]);
const categories = ref([]);
const activeCat  = ref(null);
const search     = ref('');
const loading    = ref(true);

async function load() {
    loading.value = true;
    try {
        const params = {};
        if (activeCat.value) params.category_id = activeCat.value;
        if (search.value) params.search = search.value;
        const [p, c] = await Promise.all([
            api.get('/shop/products', { params }),
            categories.value.length ? Promise.resolve({ data: categories.value }) : api.get('/shop/categories'),
        ]);
        products.value   = p.data.data;
        categories.value = Array.isArray(c.data) ? c.data : categories.value;
    } finally {
        loading.value = false;
    }
}

function filterCat(id) {
    activeCat.value = activeCat.value === id ? null : id;
    load();
}

function addToCart(product) {
    if (!product.in_stock) return;
    cart.add(product);
}

onMounted(load);
</script>

<template>
    <div class="min-h-dvh bg-slate-50 pb-24">
        <header class="sticky top-0 z-10 bg-white/95 px-5 py-4 backdrop-blur shadow-sm">
            <div class="flex items-center justify-between">
                <h1 class="flex items-center gap-2 text-lg font-bold text-slate-800">🛍️ {{ gs.shop_nav_label ?? 'فروشگاه' }}</h1>
                <button class="relative" @click="router.push({ name: 'cart' })">
                    <span class="text-2xl">🛒</span>
                    <span v-if="cart.count" class="absolute -top-1 -left-2 rounded-full bg-rose-600 px-1.5 text-[10px] font-bold text-white">{{ cart.count }}</span>
                </button>
            </div>
            <input v-model="search" placeholder="جستجوی محصول…" dir="rtl"
                class="mt-3 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm outline-none focus:border-blue-500"
                @keyup.enter="load" />
            <!-- Category chips -->
            <div v-if="categories.length" class="mt-3 flex gap-2 overflow-x-auto pb-1">
                <button v-for="c in categories" :key="c.id"
                    class="shrink-0 rounded-full px-3 py-1 text-xs font-semibold transition"
                    :class="activeCat === c.id ? 'bg-blue-600 text-white' : 'bg-slate-100 text-slate-600'"
                    @click="filterCat(c.id)">{{ c.title }}</button>
            </div>
        </header>

        <div v-if="loading" class="flex justify-center py-20">
            <div class="h-8 w-8 animate-spin rounded-full border-4 border-blue-200 border-t-blue-600"></div>
        </div>

        <div v-else class="px-4 py-4">
            <p v-if="!products.length" class="py-16 text-center text-sm text-slate-400">محصولی یافت نشد.</p>
            <div class="grid grid-cols-2 gap-3">
                <div v-for="p in products" :key="p.id" class="overflow-hidden rounded-3xl bg-white shadow-sm">
                    <div class="aspect-square bg-slate-100" @click="router.push({ name: 'product', params: { id: p.id } })">
                        <img v-if="p.image_url" :src="p.image_url" :alt="p.name" class="h-full w-full object-cover" />
                        <div v-else class="flex h-full items-center justify-center text-4xl text-slate-300">📦</div>
                    </div>
                    <div class="p-3 space-y-2">
                        <p class="line-clamp-1 text-sm font-bold text-slate-800">{{ p.name }}</p>
                        <p class="text-sm font-extrabold text-blue-600">{{ toman(p.price) }}</p>
                        <button
                            :disabled="!p.in_stock"
                            class="w-full rounded-xl py-2 text-xs font-semibold active:scale-95"
                            :class="p.in_stock ? 'bg-blue-600 text-white' : 'bg-slate-100 text-slate-400'"
                            @click="addToCart(p)">
                            {{ p.in_stock ? '➕ افزودن به سبد' : 'ناموجود' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
