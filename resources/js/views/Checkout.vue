<script setup>
import { ref, onMounted, inject } from 'vue';
import { useRouter } from 'vue-router';
import api from '../api';
import { useCartStore } from '../stores/cart';
import { useAuthStore } from '../stores/auth';
import { toman } from '../money';

defineOptions({ name: 'Checkout' });

const router = useRouter();
const cart   = useCartStore();
const auth   = useAuthStore();
const gs     = inject('globalSettings', {});

const form = ref({
    customer_name:  '',
    customer_phone: '',
    address:        '',
    payment_method: 'cod',
    note:           '',
});
const receiptFile = ref(null);
const receiptPreview = ref(null);
const submitting = ref(false);
const error = ref('');
const done  = ref(null); // created order

onMounted(() => {
    if (cart.isEmpty) { router.replace({ name: 'shop' }); return; }
    // Prefill from the logged-in profile.
    const u = auth.user;
    if (u) {
        form.value.customer_name  = [u.first_name, u.last_name].filter(Boolean).join(' ');
        form.value.customer_phone = u.phone || '';
    }
});

function onReceipt(e) {
    const file = e.target.files?.[0];
    if (!file) return;
    receiptFile.value = file;
    receiptPreview.value = URL.createObjectURL(file);
}

async function submit() {
    error.value = '';
    if (!form.value.customer_name.trim()) { error.value = 'نام را وارد کنید.'; return; }
    if (!/^09\d{9}$/.test(form.value.customer_phone)) { error.value = 'شماره موبایل معتبر نیست.'; return; }
    if (!form.value.address.trim()) { error.value = 'آدرس را وارد کنید.'; return; }

    submitting.value = true;
    try {
        const fd = new FormData();
        fd.append('customer_name', form.value.customer_name);
        fd.append('customer_phone', form.value.customer_phone);
        fd.append('address', form.value.address);
        fd.append('payment_method', form.value.payment_method);
        if (form.value.note) fd.append('note', form.value.note);
        cart.orderItems().forEach((it, i) => {
            fd.append(`items[${i}][product_id]`, it.product_id);
            fd.append(`items[${i}][quantity]`, it.quantity);
        });
        if (form.value.payment_method === 'transfer' && receiptFile.value) {
            fd.append('receipt', receiptFile.value);
        }
        const { data } = await api.post('/orders', fd, { headers: { 'Content-Type': 'multipart/form-data' } });
        done.value = data;
        cart.clear();
    } catch (err) {
        error.value = err.response?.data?.message
            || Object.values(err.response?.data?.errors || {})[0]?.[0]
            || 'خطا در ثبت سفارش.';
    } finally {
        submitting.value = false;
    }
}
</script>

<template>
    <div class="min-h-dvh bg-slate-50 pb-24">
        <header class="sticky top-0 z-10 flex items-center gap-3 bg-white/95 px-5 py-4 backdrop-blur shadow-sm">
            <button @click="router.back()" class="text-xl text-slate-500">←</button>
            <h1 class="font-bold text-slate-800">ثبت سفارش</h1>
        </header>

        <!-- Success -->
        <div v-if="done" class="mx-auto max-w-lg px-4 py-8 text-center space-y-4">
            <div class="text-5xl">✅</div>
            <p class="text-lg font-bold text-green-700">سفارش شما ثبت شد</p>
            <p class="text-sm text-slate-500">شماره سفارش: <strong>{{ done.id }}</strong></p>
            <p class="text-sm text-slate-500">مبلغ کل: <strong>{{ toman(done.total_amount) }}</strong></p>
            <p v-if="done.payment_method === 'transfer'" class="text-xs text-amber-600">پس از واریز، در صورت نیاز عکس رسید را از بخش «سفارش‌های من» ارسال کنید.</p>
            <div class="flex gap-2">
                <button class="flex-1 rounded-xl bg-slate-100 py-2.5 text-sm font-semibold text-slate-600" @click="router.push({ name: 'orders' })">سفارش‌های من</button>
                <button class="flex-1 rounded-xl bg-blue-600 py-2.5 text-sm font-semibold text-white" @click="router.push({ name: 'shop' })">ادامه خرید</button>
            </div>
        </div>

        <div v-else class="mx-auto max-w-lg space-y-4 px-4 py-4">
            <!-- Order summary -->
            <div class="rounded-3xl bg-white p-4 shadow-sm">
                <p class="mb-2 text-sm font-bold text-slate-700">سبد خرید</p>
                <div v-for="item in cart.items" :key="item.id" class="flex justify-between py-1 text-sm text-slate-600">
                    <span>{{ item.name }} × {{ item.quantity }}</span>
                    <span>{{ toman(item.price * item.quantity) }}</span>
                </div>
                <div class="mt-2 flex justify-between border-t border-slate-100 pt-2 text-sm font-extrabold text-slate-800">
                    <span>جمع کل</span><span>{{ toman(cart.total) }}</span>
                </div>
            </div>

            <!-- Customer details -->
            <div class="space-y-3 rounded-3xl bg-white p-4 shadow-sm">
                <input v-model="form.customer_name" placeholder="نام و نام خانوادگی *"
                    class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm outline-none focus:border-blue-500" />
                <input v-model="form.customer_phone" placeholder="شماره موبایل *" dir="ltr" maxlength="11"
                    class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm outline-none focus:border-blue-500" />
                <!-- Single-line address -->
                <textarea v-model="form.address" rows="2" placeholder="آدرس کامل (یک خط) *"
                    class="w-full resize-none rounded-xl border border-slate-200 px-3 py-2 text-sm outline-none focus:border-blue-500"></textarea>
                <textarea v-model="form.note" rows="2" placeholder="توضیحات سفارش (اختیاری)"
                    class="w-full resize-none rounded-xl border border-slate-200 px-3 py-2 text-sm outline-none focus:border-blue-500"></textarea>
            </div>

            <!-- Payment method -->
            <div class="space-y-3 rounded-3xl bg-white p-4 shadow-sm">
                <p class="text-sm font-bold text-slate-700">روش پرداخت</p>
                <label class="flex items-center gap-3 rounded-xl border px-3 py-2 cursor-pointer"
                    :class="form.payment_method === 'cod' ? 'border-blue-500 bg-blue-50' : 'border-slate-200'">
                    <input type="radio" value="cod" v-model="form.payment_method" class="accent-blue-600" />
                    <span class="text-sm text-slate-700">💵 پرداخت در محل</span>
                </label>
                <label class="flex items-center gap-3 rounded-xl border px-3 py-2 cursor-pointer"
                    :class="form.payment_method === 'transfer' ? 'border-blue-500 bg-blue-50' : 'border-slate-200'">
                    <input type="radio" value="transfer" v-model="form.payment_method" class="accent-blue-600" />
                    <span class="text-sm text-slate-700">🏦 واریز به حساب</span>
                </label>

                <!-- Bank details + receipt upload -->
                <div v-if="form.payment_method === 'transfer'" class="space-y-2 rounded-xl bg-amber-50 p-3">
                    <p v-if="gs.shop_bank_card" class="text-sm text-amber-900">
                        شماره کارت: <strong dir="ltr">{{ gs.shop_bank_card }}</strong>
                    </p>
                    <p v-if="gs.shop_bank_holder" class="text-xs text-amber-700">به نام: {{ gs.shop_bank_holder }}</p>
                    <p class="text-xs text-amber-700">پس از واریز، عکس رسید را بارگذاری کنید:</p>
                    <label class="block w-full cursor-pointer rounded-xl border border-dashed border-amber-300 bg-white px-3 py-3 text-center text-xs text-amber-700">
                        {{ receiptFile ? '✅ رسید انتخاب شد — تغییر' : '📎 انتخاب عکس رسید' }}
                        <input type="file" accept="image/*" class="hidden" @change="onReceipt" />
                    </label>
                    <img v-if="receiptPreview" :src="receiptPreview" class="mt-2 max-h-40 rounded-xl object-contain" />
                </div>
            </div>

            <p v-if="error" class="text-sm text-rose-600">{{ error }}</p>

            <button :disabled="submitting" class="w-full rounded-2xl bg-blue-600 py-3 font-semibold text-white shadow-lg shadow-blue-200 active:scale-95 disabled:opacity-60" @click="submit">
                {{ submitting ? 'در حال ثبت…' : `ثبت نهایی سفارش (${toman(cart.total)})` }}
            </button>
        </div>
    </div>
</template>
