<script setup>
import { ref, onMounted } from 'vue';
import api from '../api';
import { toman, groupDigits } from '../money';
import { fullDate } from '../time';
import { compressImage } from '../image';

defineOptions({ name: 'ShopAdmin' });

const tab = ref('products'); // products | categories | orders | settings
const loading = ref(false);
const message = ref(null);
function flash(t) { message.value = t; setTimeout(() => (message.value = null), 2500); }

// ── Products ──────────────────────────────────────────────────────────────────
const products = ref([]);
const categories = ref([]);
const productForm = ref({ id: null, name: '', description: '', price: '', stock: '', category_id: '', is_active: true });
const imageFile = ref(null);
const imagePreview = ref(null);
const productSaving = ref(false);

async function loadProducts() {
    const [p, c] = await Promise.all([api.get('/shop-admin/products'), api.get('/shop-admin/categories')]);
    products.value = p.data.data;
    categories.value = c.data;
}
async function onImage(e) {
    const file = e.target.files?.[0];
    if (!file) return;
    // Downscale before upload so large camera photos don't exceed server limits.
    const compressed = await compressImage(file);
    imageFile.value = compressed;
    imagePreview.value = URL.createObjectURL(compressed);
}
function editProduct(p) {
    productForm.value = {
        id: p.id, name: p.name, description: p.description || '',
        price: p.price, stock: p.stock,
        category_id: p.category?.id || '', is_active: p.is_active,
    };
    imageFile.value = null;
    imagePreview.value = p.image_url || null;
}
function resetProductForm() {
    productForm.value = { id: null, name: '', description: '', price: '', stock: '', category_id: '', is_active: true };
    imageFile.value = null; imagePreview.value = null;
}
async function saveProduct() {
    productSaving.value = true;
    try {
        const fd = new FormData();
        fd.append('name', productForm.value.name);
        fd.append('description', productForm.value.description || '');
        fd.append('price', productForm.value.price || 0);
        fd.append('stock', productForm.value.stock || 0);
        if (productForm.value.category_id) fd.append('category_id', productForm.value.category_id);
        fd.append('is_active', productForm.value.is_active ? 1 : 0);
        if (imageFile.value) fd.append('image', imageFile.value);

        const url = productForm.value.id ? `/shop-admin/products/${productForm.value.id}` : '/shop-admin/products';
        const { data } = await api.post(url, fd, { headers: { 'Content-Type': 'multipart/form-data' } });

        if (productForm.value.id) {
            const idx = products.value.findIndex((x) => x.id === data.id);
            if (idx !== -1) products.value[idx] = data;
        } else {
            products.value.unshift(data);
        }
        resetProductForm();
        flash('ذخیره شد ✅');
    } catch (err) {
        // Prefer the specific field error over the generic validation message.
        flash(Object.values(err.response?.data?.errors || {})[0]?.[0] || err.response?.data?.message || 'خطا');
    } finally {
        productSaving.value = false;
    }
}
async function deleteProduct(p) {
    if (!confirm(`«${p.name}» حذف شود؟`)) return;
    await api.delete(`/shop-admin/products/${p.id}`);
    products.value = products.value.filter((x) => x.id !== p.id);
    flash('حذف شد 🗑️');
}

// ── Categories ────────────────────────────────────────────────────────────────
const newCat = ref('');
async function addCategory() {
    if (!newCat.value.trim()) return;
    const { data } = await api.post('/shop-admin/categories', { title: newCat.value.trim(), sort_order: categories.value.length });
    categories.value.push(data);
    newCat.value = '';
    flash('دسته اضافه شد ✅');
}
async function deleteCategory(c) {
    if (!confirm(`دسته «${c.title}» حذف شود؟`)) return;
    await api.delete(`/shop-admin/categories/${c.id}`);
    categories.value = categories.value.filter((x) => x.id !== c.id);
    flash('حذف شد 🗑️');
}

// ── Orders ────────────────────────────────────────────────────────────────────
const orders = ref([]);
const orderStats = ref(null);
const orderFilter = ref('');
const statusLabel = { pending: 'در انتظار', confirmed: 'تأیید شده', shipping: 'در حال ارسال', delivered: 'تحویل شده', canceled: 'لغو شده' };
const statusColor = {
    pending: 'bg-amber-100 text-amber-700', confirmed: 'bg-blue-100 text-blue-700',
    shipping: 'bg-indigo-100 text-indigo-700', delivered: 'bg-emerald-100 text-emerald-700', canceled: 'bg-rose-100 text-rose-700',
};
async function loadOrders() {
    const params = orderFilter.value ? { status: orderFilter.value } : {};
    const [o, s] = await Promise.all([api.get('/shop-admin/orders', { params }), api.get('/shop-admin/orders/stats')]);
    orders.value = o.data.data;
    orderStats.value = s.data;
}
async function setOrderStatus(order, status) {
    try {
        const { data } = await api.patch(`/shop-admin/orders/${order.id}/status`, { status });
        Object.assign(order, data);
        orderStats.value = null;
        flash('وضعیت بروز شد ✅');
    } catch (err) { flash(err.response?.data?.message || 'خطا'); }
}

// ── Shop settings ─────────────────────────────────────────────────────────────
const shopSettings = ref({ shop_enabled: true, shop_nav_label: 'فروشگاه', shop_bank_card: '', shop_bank_holder: '' });
const settingsSaving = ref(false);
async function loadShopSettings() {
    const { data } = await api.get('/shop-admin/settings');
    shopSettings.value = data;
}
async function saveShopSettings() {
    settingsSaving.value = true;
    try {
        await api.patch('/shop-admin/settings', shopSettings.value);
        flash('تنظیمات ذخیره شد ✅');
    } catch (err) { flash(err.response?.data?.message || 'خطا'); }
    finally { settingsSaving.value = false; }
}

// ── Tab routing ───────────────────────────────────────────────────────────────
async function switchTab(name) {
    tab.value = name;
    loading.value = true;
    try {
        if (name === 'products' || name === 'categories') await loadProducts();
        if (name === 'orders')   await loadOrders();
        if (name === 'settings') await loadShopSettings();
    } finally { loading.value = false; }
}

onMounted(() => switchTab('products'));
</script>

<template>
    <div class="pb-24">
        <header class="sticky top-0 z-10 bg-white/95 px-4 py-3 backdrop-blur shadow-sm">
            <h1 class="text-center text-lg font-bold text-slate-800">🛍️ مدیریت فروشگاه</h1>
            <div class="mt-3 flex gap-1 overflow-x-auto rounded-2xl bg-slate-100 p-1 text-xs">
                <button v-for="t in [
                    { key:'products', label:'محصولات' },
                    { key:'categories', label:'دسته‌بندی' },
                    { key:'orders', label:'سفارش‌ها' },
                    { key:'settings', label:'تنظیمات' },
                ]" :key="t.key"
                    class="shrink-0 rounded-xl px-3 py-2 font-semibold transition whitespace-nowrap"
                    :class="tab === t.key ? 'bg-white text-blue-600 shadow-sm' : 'text-slate-500'"
                    @click="switchTab(t.key)">{{ t.label }}</button>
            </div>
        </header>

        <p v-if="message" class="fixed left-1/2 top-4 z-50 -translate-x-1/2 rounded-full bg-slate-800 px-4 py-2 text-sm text-white shadow-lg">{{ message }}</p>

        <div v-if="loading" class="flex justify-center py-16">
            <div class="h-8 w-8 animate-spin rounded-full border-4 border-blue-200 border-t-blue-600"></div>
        </div>

        <div v-else class="px-4 py-4">

            <!-- ─── محصولات ─────────────────────────────────────────── -->
            <div v-if="tab === 'products'" class="space-y-4">
                <!-- Product form -->
                <div class="space-y-3 rounded-3xl bg-white p-5 shadow-sm">
                    <h2 class="font-bold text-slate-800">{{ productForm.id ? 'ویرایش محصول' : 'افزودن محصول جدید' }}</h2>

                    <input v-model="productForm.name" placeholder="نام محصول *" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm outline-none focus:border-blue-500" />
                    <textarea v-model="productForm.description" rows="2" placeholder="توضیحات" class="w-full resize-none rounded-xl border border-slate-200 px-3 py-2 text-sm outline-none focus:border-blue-500"></textarea>
                    <div class="flex gap-2">
                        <input v-model.number="productForm.price" type="number" min="0" placeholder="قیمت (تومان) *" dir="ltr" class="flex-1 rounded-xl border border-slate-200 px-3 py-2 text-sm outline-none focus:border-blue-500" />
                        <input v-model.number="productForm.stock" type="number" min="0" placeholder="موجودی *" dir="ltr" class="w-28 rounded-xl border border-slate-200 px-3 py-2 text-sm outline-none focus:border-blue-500" />
                    </div>
                    <select v-model="productForm.category_id" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm outline-none focus:border-blue-500">
                        <option value="">بدون دسته</option>
                        <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.title }}</option>
                    </select>

                    <!-- Image: gallery OR camera -->
                    <div class="space-y-2">
                        <p class="text-xs font-semibold text-slate-600">تصویر محصول</p>
                        <div class="flex gap-2">
                            <label class="flex-1 cursor-pointer rounded-xl border border-dashed border-slate-300 bg-slate-50 px-3 py-3 text-center text-xs text-slate-600">
                                🖼️ انتخاب از گالری
                                <input type="file" accept="image/*" class="hidden" @change="onImage" />
                            </label>
                            <label class="flex-1 cursor-pointer rounded-xl border border-dashed border-slate-300 bg-slate-50 px-3 py-3 text-center text-xs text-slate-600">
                                📷 گرفتن عکس
                                <input type="file" accept="image/*" capture="environment" class="hidden" @change="onImage" />
                            </label>
                        </div>
                        <img v-if="imagePreview" :src="imagePreview" class="max-h-40 rounded-xl object-contain" />
                    </div>

                    <label class="flex items-center gap-2 text-sm text-slate-700">
                        <input type="checkbox" v-model="productForm.is_active" class="h-4 w-4 rounded accent-blue-600" />
                        فعال (نمایش در فروشگاه)
                    </label>

                    <div class="flex gap-2">
                        <button v-if="productForm.id" class="rounded-xl bg-slate-100 px-4 py-2 text-sm text-slate-600" @click="resetProductForm">انصراف</button>
                        <button :disabled="productSaving" class="flex-1 rounded-xl bg-blue-600 py-2 text-sm font-semibold text-white active:scale-95 disabled:opacity-60" @click="saveProduct">
                            {{ productSaving ? 'در حال ذخیره…' : (productForm.id ? 'بروزرسانی' : 'افزودن محصول') }}
                        </button>
                    </div>
                </div>

                <!-- Product list -->
                <p v-if="!products.length" class="py-8 text-center text-sm text-slate-400">هنوز محصولی ثبت نشده است.</p>
                <div v-for="p in products" :key="p.id" class="flex gap-3 rounded-3xl bg-white p-3 shadow-sm">
                    <div class="h-16 w-16 shrink-0 overflow-hidden rounded-2xl bg-slate-100">
                        <img v-if="p.image_url" :src="p.image_url" class="h-full w-full object-cover" />
                        <div v-else class="flex h-full items-center justify-center text-2xl text-slate-300">📦</div>
                    </div>
                    <div class="flex-1">
                        <div class="flex items-start justify-between">
                            <p class="text-sm font-bold text-slate-800">{{ p.name }}</p>
                            <span v-if="!p.is_active" class="rounded-full bg-slate-200 px-2 py-0.5 text-[10px] text-slate-500">غیرفعال</span>
                        </div>
                        <p class="text-sm font-extrabold text-blue-600">{{ toman(p.price) }}</p>
                        <p class="text-xs" :class="p.stock > 0 ? 'text-slate-400' : 'text-rose-500'">موجودی: {{ groupDigits(p.stock) }}</p>
                        <div class="mt-1 flex gap-2">
                            <button class="rounded-lg bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-700" @click="editProduct(p)">✏️ ویرایش</button>
                            <button class="rounded-lg bg-rose-100 px-3 py-1 text-xs font-semibold text-rose-700" @click="deleteProduct(p)">🗑️ حذف</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ─── دسته‌بندی ───────────────────────────────────────── -->
            <div v-else-if="tab === 'categories'" class="space-y-3">
                <div class="flex gap-2">
                    <input v-model="newCat" placeholder="نام دسته جدید…" class="flex-1 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm outline-none focus:border-blue-500" @keyup.enter="addCategory" />
                    <button class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-semibold text-white active:scale-95" @click="addCategory">افزودن</button>
                </div>
                <p v-if="!categories.length" class="py-6 text-center text-sm text-slate-400">هنوز دسته‌ای تعریف نشده.</p>
                <div v-for="c in categories" :key="c.id" class="flex items-center justify-between rounded-2xl bg-white px-4 py-3 shadow-sm">
                    <span class="text-sm text-slate-800">{{ c.title }} <span class="text-xs text-slate-400">({{ c.products_count ?? 0 }} محصول)</span></span>
                    <button class="rounded-xl bg-rose-100 px-3 py-1.5 text-xs font-semibold text-rose-700 active:scale-95" @click="deleteCategory(c)">حذف</button>
                </div>
            </div>

            <!-- ─── سفارش‌ها ─────────────────────────────────────────── -->
            <div v-else-if="tab === 'orders'" class="space-y-4">
                <!-- Stats -->
                <div v-if="orderStats" class="grid grid-cols-3 gap-2 text-center text-xs">
                    <div v-for="s in [
                        { label:'کل سفارش', value: orderStats.total, color:'text-slate-700' },
                        { label:'در انتظار', value: orderStats.pending, color:'text-amber-600' },
                        { label:'تحویل شده', value: orderStats.delivered, color:'text-emerald-600' },
                    ]" :key="s.label" class="rounded-2xl bg-white p-3 shadow-sm">
                        <div class="text-xl font-extrabold" :class="s.color">{{ s.value }}</div>
                        <div class="text-slate-400">{{ s.label }}</div>
                    </div>
                </div>
                <div v-if="orderStats" class="rounded-2xl bg-emerald-50 px-4 py-2 text-center text-sm font-bold text-emerald-700">
                    درآمد (تحویل‌شده): {{ toman(orderStats.revenue) }}
                </div>

                <select v-model="orderFilter" class="w-full rounded-xl border border-slate-200 bg-white px-2 py-2 text-sm" @change="loadOrders">
                    <option value="">همه وضعیت‌ها</option>
                    <option v-for="(label, key) in statusLabel" :key="key" :value="key">{{ label }}</option>
                </select>

                <p v-if="!orders.length" class="py-8 text-center text-sm text-slate-400">سفارشی یافت نشد.</p>
                <div v-for="o in orders" :key="o.id" class="rounded-3xl bg-white p-4 shadow-sm space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-sm font-bold text-slate-800">سفارش #{{ o.id }}</span>
                        <span class="rounded-full px-2 py-0.5 text-xs" :class="statusColor[o.status]">{{ statusLabel[o.status] }}</span>
                    </div>
                    <p class="text-xs text-slate-500">{{ o.customer_name }} · <span dir="ltr">{{ o.customer_phone }}</span></p>
                    <p class="text-xs text-slate-500">📍 {{ o.address }}</p>
                    <div v-for="it in o.items" :key="it.id" class="flex justify-between text-xs text-slate-600">
                        <span>{{ it.product_name }} × {{ it.quantity }}</span>
                        <span>{{ toman(it.unit_price * it.quantity) }}</span>
                    </div>
                    <div class="flex justify-between border-t border-slate-100 pt-2 text-sm font-bold text-slate-800">
                        <span>{{ o.payment_method === 'cod' ? '💵 در محل' : '🏦 واریز' }}</span>
                        <span>{{ toman(o.total_amount) }}</span>
                    </div>
                    <p v-if="o.note" class="text-xs text-slate-400">یادداشت مشتری: {{ o.note }}</p>
                    <a v-if="o.receipt_url" :href="o.receipt_url" target="_blank" class="inline-block text-xs text-blue-600 underline">🧾 مشاهده رسید پرداخت</a>
                    <p class="text-xs text-slate-400">{{ fullDate(o.created_at) }}</p>

                    <!-- Status actions -->
                    <div class="flex flex-wrap gap-1.5 pt-1">
                        <button v-if="o.status==='pending'" class="rounded-lg bg-blue-100 px-3 py-1 text-xs font-semibold text-blue-700" @click="setOrderStatus(o,'confirmed')">تأیید</button>
                        <button v-if="['pending','confirmed'].includes(o.status)" class="rounded-lg bg-indigo-100 px-3 py-1 text-xs font-semibold text-indigo-700" @click="setOrderStatus(o,'shipping')">ارسال</button>
                        <button v-if="o.status==='shipping'" class="rounded-lg bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-700" @click="setOrderStatus(o,'delivered')">تحویل شد</button>
                        <button v-if="!['delivered','canceled'].includes(o.status)" class="rounded-lg bg-rose-100 px-3 py-1 text-xs font-semibold text-rose-700" @click="setOrderStatus(o,'canceled')">لغو</button>
                    </div>
                </div>
            </div>

            <!-- ─── تنظیمات فروشگاه ─────────────────────────────────── -->
            <div v-else-if="tab === 'settings'" class="space-y-4">
                <div class="space-y-3 rounded-3xl bg-white p-5 shadow-sm">
                    <h2 class="font-bold text-slate-800">⚙️ تنظیمات فروشگاه</h2>
                    <label class="flex items-center gap-3 cursor-pointer">
                        <input type="checkbox" v-model="shopSettings.shop_enabled" class="h-4 w-4 rounded accent-blue-600" />
                        <span class="text-sm text-slate-700">فروشگاه فعال باشد</span>
                    </label>
                    <input v-model="shopSettings.shop_nav_label" placeholder="برچسب منو (مثال: فروشگاه)" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm outline-none focus:border-blue-500" />
                </div>
                <div class="space-y-3 rounded-3xl bg-white p-5 shadow-sm">
                    <h2 class="font-bold text-slate-800">🏦 اطلاعات واریز به حساب</h2>
                    <input v-model="shopSettings.shop_bank_card" placeholder="شماره کارت" dir="ltr" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm outline-none focus:border-blue-500" />
                    <input v-model="shopSettings.shop_bank_holder" placeholder="نام صاحب حساب" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm outline-none focus:border-blue-500" />
                </div>
                <button :disabled="settingsSaving" class="w-full rounded-xl bg-blue-600 py-3 font-semibold text-white active:scale-95 disabled:opacity-60" @click="saveShopSettings">
                    {{ settingsSaving ? 'در حال ذخیره…' : '💾 ذخیره تنظیمات' }}
                </button>
            </div>

        </div>
    </div>
</template>
