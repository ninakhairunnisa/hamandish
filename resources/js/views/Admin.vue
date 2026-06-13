<script setup>
import { ref, onMounted, computed } from 'vue';
import { useAuthStore } from '../stores/auth';
import api from '../api';

const auth = useAuthStore();
const isSuperAdmin = computed(() => auth.user?.role === 'super_admin');

// ── Tabs ────────────────────────────────────────────────────────────────────
const tab = ref('pending');
// pending | problems | users | officials | assembly | reported | super_settings | stats

const loading  = ref(false);
const message  = ref(null);
function flash(text) { message.value = text; setTimeout(() => (message.value = null), 2500); }

// ── Stats ────────────────────────────────────────────────────────────────────
const stats = ref(null);
const commentsEnabled = ref(true);
async function loadStats() {
    const [s, st] = await Promise.all([api.get('/admin/stats'), api.get('/admin/settings')]);
    stats.value = s.data;
    commentsEnabled.value = !!st.data.comments_enabled;
}
async function toggleComments() {
    const { data } = await api.patch('/admin/settings', { comments_enabled: !commentsEnabled.value });
    commentsEnabled.value = !!data.comments_enabled;
    flash(commentsEnabled.value ? 'نظرات فعال شد ✅' : 'نظرات غیرفعال شد 🔕');
}

// ── Pending ──────────────────────────────────────────────────────────────────
const pending = ref([]);
async function loadPending() {
    const { data } = await api.get('/admin/problems/pending');
    pending.value = data.data;
}
async function setStatus(problem, status) {
    await api.patch(`/admin/problems/${problem.id}/status`, { status });
    pending.value = pending.value.filter((p) => p.id !== problem.id);
    problems.value = problems.value.filter((p) => p.id !== problem.id);
    flash(status === 'approved' ? 'تأیید شد ✅' : 'رد شد ❌');
}

// ── Problems ─────────────────────────────────────────────────────────────────
const problems      = ref([]);
const statusFilter  = ref('');
const search        = ref('');
async function loadProblems() {
    const params = {};
    if (statusFilter.value) params.status = statusFilter.value;
    if (search.value) params.search = search.value;
    const { data } = await api.get('/admin/problems', { params });
    problems.value = data.data;
}
async function toggleFeatured(problem) {
    const { data } = await api.patch(`/admin/problems/${problem.id}/featured`, { is_featured: !problem.is_featured });
    problem.is_featured = data.is_featured;
    flash(data.is_featured ? 'برگزیده شد ⭐' : 'از برگزیده‌ها خارج شد');
}
async function removeProblem(problem) {
    if (!confirm(`«${problem.title}» حذف شود؟`)) return;
    await api.delete(`/admin/problems/${problem.id}`);
    problems.value = problems.value.filter((p) => p.id !== problem.id);
    pending.value  = pending.value.filter((p) => p.id !== problem.id);
    flash('حذف شد 🗑️');
}

// Referral modal
const referralProblem = ref(null);   // the problem being referred
const referralMsg     = ref('');
const referralOfficialId = ref(null);
const officials       = ref([]);
const referrals       = ref([]);       // referrals already sent for this problem
const referralLoading = ref(false);

async function openReferral(problem) {
    referralProblem.value    = problem;
    referralOfficialId.value = null;
    referralMsg.value        = `مسئول گرامی، مشکل «${problem.title}» از طریق سامانه هم‌اندیش به شما ارجاع داده شده است. خواهشمند است نسبت به بررسی و ارائه پاسخ اقدام فرمایید.\nلینک مشکل: ${window.location.origin}${import.meta.env.VITE_APP_BASE ?? '/'}#/problems/${problem.id}`;
    const [off, refs] = await Promise.all([
        officials.value.length ? Promise.resolve({ data: officials.value }) : api.get('/admin/officials'),
        api.get(`/admin/problems/${problem.id}/referrals`),
    ]);
    officials.value  = Array.isArray(off.data) ? off.data : off.data;
    referrals.value  = refs.data;
}
async function sendReferral() {
    if (!referralOfficialId.value) { flash('یک مسئول انتخاب کنید'); return; }
    if (!referralMsg.value.trim()) { flash('متن پیام خالی است'); return; }
    referralLoading.value = true;
    try {
        const { data } = await api.post(`/admin/problems/${referralProblem.value.id}/referrals`, {
            official_id: referralOfficialId.value,
            message:     referralMsg.value,
        });
        referrals.value.unshift(data.referral);
        flash(data.message);
    } catch (err) {
        flash(err.response?.data?.message || 'خطا در ارسال');
    } finally {
        referralLoading.value = false;
    }
}

// ── Users ─────────────────────────────────────────────────────────────────────
const users = ref([]);
async function loadUsers() {
    const { data } = await api.get('/admin/users', { params: search.value ? { search: search.value } : {} });
    users.value = data.data;
}
async function setLabel(user) {
    const label = prompt('لیبل کاربر (خالی = حذف لیبل):', user.label || '');
    if (label === null) return;
    try {
        const { data } = await api.patch(`/admin/users/${user.id}/label`, { label: label.trim() || null });
        user.label = data.label;
        flash(user.label ? 'لیبل ثبت شد 🏷️' : 'لیبل حذف شد');
    } catch (err) { flash(err.response?.data?.message || 'خطا'); }
}
async function toggleRole(user) {
    const role = user.role === 'admin' ? 'user' : 'admin';
    try {
        const { data } = await api.patch(`/admin/users/${user.id}/role`, { role });
        user.role = data.role;
        flash(role === 'admin' ? 'ادمین شد 👑' : 'ادمین حذف شد');
    } catch (err) { flash(err.response?.data?.message || 'خطا'); }
}

// ── Officials ─────────────────────────────────────────────────────────────────
const officialForm = ref({ id: null, name: '', position: '', phone: '', notes: '' });
const officialSaving = ref(false);
const linkedUser = ref(null);

async function loadOfficials() {
    const { data } = await api.get('/admin/officials');
    officials.value = data;
}
async function searchLinkedUser() {
    if (!/^09\d{9}$/.test(officialForm.value.phone)) { linkedUser.value = null; return; }
    try {
        const { data } = await api.get('/admin/officials/search-user', { params: { phone: officialForm.value.phone } });
        linkedUser.value = data;
    } catch (_) { linkedUser.value = null; }
}
function editOfficial(o) {
    officialForm.value = { id: o.id, name: o.name, position: o.position, phone: o.phone || '', notes: o.notes || '' };
    linkedUser.value = o.user || null;
}
function resetOfficialForm() {
    officialForm.value = { id: null, name: '', position: '', phone: '', notes: '' };
    linkedUser.value = null;
}
async function saveOfficial() {
    officialSaving.value = true;
    try {
        const payload = { ...officialForm.value };
        if (linkedUser.value?.id) payload.user_id = linkedUser.value.id;
        if (payload.id) {
            const { data } = await api.patch(`/admin/officials/${payload.id}`, payload);
            const idx = officials.value.findIndex((o) => o.id === data.id);
            if (idx !== -1) officials.value[idx] = data;
        } else {
            const { data } = await api.post('/admin/officials', payload);
            officials.value.push(data);
        }
        resetOfficialForm();
        flash('ذخیره شد ✅');
    } catch (err) {
        flash(err.response?.data?.message || Object.values(err.response?.data?.errors || {})[0]?.[0] || 'خطا');
    } finally {
        officialSaving.value = false;
    }
}
async function deleteOfficial(o) {
    if (!confirm(`«${o.name}» حذف شود؟`)) return;
    await api.delete(`/admin/officials/${o.id}`);
    officials.value = officials.value.filter((x) => x.id !== o.id);
    flash('حذف شد 🗑️');
}

// ── Assembly ──────────────────────────────────────────────────────────────────
const assemblyTab     = ref('memberships'); // memberships | roles | settings
const memberships     = ref([]);
const membershipFilter = ref('');
const assemblyStats   = ref(null);
const assemblyRoles   = ref([]);
const assemblyIntro   = ref('');
const newRoleTitle    = ref('');
const introSaving     = ref(false);

async function loadAssembly() {
    const [stats_, roles, settings] = await Promise.all([
        api.get('/admin/assembly/stats'),
        api.get('/admin/assembly/roles'),
        api.get('/admin/assembly/settings'),
    ]);
    assemblyStats.value = stats_.data;
    assemblyRoles.value = roles.data;
    assemblyIntro.value = settings.data.intro_message;
    await loadMemberships();
}
async function loadMemberships() {
    const params = membershipFilter.value ? { status: membershipFilter.value } : {};
    const { data } = await api.get('/admin/assembly/memberships', { params });
    memberships.value = data.data;
}
async function updateMembership(m, status) {
    const note = status === 'rejected' ? (prompt('دلیل رد (اختیاری):') ?? '') : (m.admin_note ?? '');
    try {
        const { data } = await api.patch(`/admin/assembly/memberships/${m.id}`, { status, admin_note: note });
        Object.assign(m, data);
        assemblyStats.value = null; // invalidate stats
        flash('وضعیت بروز شد ✅');
    } catch (err) { flash(err.response?.data?.message || 'خطا'); }
}
async function exportCsv() {
    const params = membershipFilter.value ? `?status=${membershipFilter.value}` : '';
    const link = document.createElement('a');
    // Use the api baseURL + path. Since api is axios, grab its baseURL.
    const base = api.defaults.baseURL;
    link.href = `${base}/admin/assembly/memberships/export${params}`;
    // Force download with auth header isn't possible from anchor — open in new tab
    window.open(link.href, '_blank');
}
async function addRole() {
    if (!newRoleTitle.value.trim()) return;
    const { data } = await api.post('/admin/assembly/roles', { title: newRoleTitle.value.trim(), sort_order: assemblyRoles.value.length });
    assemblyRoles.value.push(data);
    newRoleTitle.value = '';
    flash('مسئولیت اضافه شد ✅');
}
async function deleteRole(role) {
    if (!confirm(`«${role.title}» حذف شود؟`)) return;
    await api.delete(`/admin/assembly/roles/${role.id}`);
    assemblyRoles.value = assemblyRoles.value.filter((r) => r.id !== role.id);
    flash('حذف شد 🗑️');
}
async function saveIntro() {
    introSaving.value = true;
    try {
        await api.patch('/admin/assembly/settings', { intro_message: assemblyIntro.value });
        flash('متن ذخیره شد ✅');
    } catch (_) { flash('خطا'); }
    finally { introSaving.value = false; }
}

// Export with auth token in Authorization header isn't trivial from <a>.
// Instead, fetch the CSV via axios and trigger a download blob.
async function exportCsvBlob() {
    const params = membershipFilter.value ? { status: membershipFilter.value } : {};
    const { data } = await api.get('/admin/assembly/memberships/export', { params, responseType: 'blob' });
    const url = URL.createObjectURL(new Blob([data], { type: 'text/csv;charset=utf-8;' }));
    const a = document.createElement('a');
    a.href = url; a.download = 'assembly-memberships.csv'; a.click();
    URL.revokeObjectURL(url);
}

// ── Reported content (super_admin) ───────────────────────────────────────────
const reportedSolutions = ref([]);
const reportedComments  = ref([]);
async function loadReported() {
    const { data } = await api.get('/super-admin/reported');
    reportedSolutions.value = data.solutions;
    reportedComments.value  = data.comments;
}
async function reviewContent(type, item, action) {
    try {
        await api.post('/super-admin/review', { type, id: item.id, action });
        if (type === 'solution') reportedSolutions.value = reportedSolutions.value.filter(s => s.id !== item.id);
        else reportedComments.value = reportedComments.value.filter(c => c.id !== item.id);
        flash(action === 'restore' ? 'محتوا بازگردانی شد ✅' : 'تأیید شد (پنهان ماند) 🚫');
    } catch (err) { flash(err.response?.data?.message || 'خطا'); }
}
async function banUser(user) {
    if (!confirm(`کاربر ${user.name || user.phone} مسدود شود؟`)) return;
    try {
        await api.patch(`/super-admin/users/${user.id}/ban`, { is_banned: true });
        flash('کاربر مسدود شد 🔒');
    } catch (err) { flash(err.response?.data?.message || 'خطا'); }
}

// ── Super Admin settings ──────────────────────────────────────────────────────
const superSettings = ref({
    ippanel_api_key: '', ippanel_sender: '',
    ippanel_otp_pattern_code: '', ippanel_otp_pattern_variable: 'code',
    assembly_section_title: '', assembly_nav_label: '',
    assembly_intro_message: '', guest_can_view: true,
    report_threshold: 3, comments_enabled: true,
});
const superSaving = ref(false);
async function loadSuperSettings() {
    const { data } = await api.get('/super-admin/settings');
    Object.assign(superSettings.value, data);
    superSettings.value.guest_can_view = data.guest_can_view === '1' || data.guest_can_view === true;
    superSettings.value.comments_enabled = data.comments_enabled === '1' || data.comments_enabled === true;
    superSettings.value.report_threshold = parseInt(data.report_threshold) || 3;
}
async function saveSuperSettings() {
    superSaving.value = true;
    try {
        await api.patch('/super-admin/settings', superSettings.value);
        flash('تنظیمات ذخیره شد ✅');
    } catch (err) { flash(err.response?.data?.message || 'خطا'); }
    finally { superSaving.value = false; }
}
async function setSuperRole(user) {
    const roles = ['user', 'admin', 'super_admin'];
    const current = roles.indexOf(user.role);
    const newRole = prompt(`نقش کاربر:\n0 = کاربر عادی\n1 = ادمین\n2 = ادمین کل\n\nوارد کنید (0-2):`, String(current));
    if (newRole === null) return;
    const role = roles[parseInt(newRole)];
    if (!role) { flash('نقش نامعتبر'); return; }
    try {
        await api.patch(`/super-admin/users/${user.id}/role`, { role });
        user.role = role;
        flash(`نقش به ${role} تغییر یافت ✅`);
    } catch (err) { flash(err.response?.data?.message || 'خطا'); }
}

// ── Tab router ────────────────────────────────────────────────────────────────
const tabs = computed(() => {
    const base = [
        { key: 'pending',   label: 'در انتظار' },
        { key: 'problems',  label: 'مشکلات' },
        { key: 'users',     label: 'کاربران' },
        { key: 'officials', label: 'مسئولین' },
        { key: 'assembly',  label: 'مجمع' },
        { key: 'stats',     label: 'آمار' },
    ];
    if (isSuperAdmin.value) {
        base.push({ key: 'reported',       label: 'گزارش‌ها' });
        base.push({ key: 'super_settings', label: 'تنظیمات کلی' });
    }
    return base;
});

async function switchTab(name) {
    tab.value = name;
    search.value = '';
    referralProblem.value = null;
    loading.value = true;
    try {
        if (name === 'stats')          await loadStats();
        if (name === 'pending')        await loadPending();
        if (name === 'problems')       await loadProblems();
        if (name === 'users')          await loadUsers();
        if (name === 'officials')      await loadOfficials();
        if (name === 'assembly')       await loadAssembly();
        if (name === 'reported')       await loadReported();
        if (name === 'super_settings') await loadSuperSettings();
    } finally { loading.value = false; }
}

const membershipStatusLabel = { pending: 'در انتظار', approved: 'تأیید شده', rejected: 'رد شده', recorded: 'ثبت شده' };
const membershipStatusColor = {
    pending:  'bg-amber-100 text-amber-700',
    approved: 'bg-emerald-100 text-emerald-700',
    rejected: 'bg-rose-100 text-rose-700',
    recorded: 'bg-blue-100 text-blue-700',
};
const statusLabel = { pending: 'در انتظار', approved: 'تأییدشده', rejected: 'ردشده' };
const statusColor  = { pending: 'bg-amber-100 text-amber-700', approved: 'bg-emerald-100 text-emerald-700', rejected: 'bg-rose-100 text-rose-700' };

onMounted(() => switchTab('pending'));
</script>

<template>
    <div class="pb-24">
        <header class="sticky top-0 z-10 bg-white/95 px-4 py-3 backdrop-blur shadow-sm">
            <h1 class="text-center text-lg font-bold text-slate-800">⚙️ مدیریت</h1>
            <!-- Scrollable tab bar for 6 tabs -->
            <div class="mt-3 flex gap-1 overflow-x-auto rounded-2xl bg-slate-100 p-1 text-xs">
                <button
                    v-for="t in tabs" :key="t.key"
                    class="shrink-0 rounded-xl px-3 py-2 font-semibold transition whitespace-nowrap"
                    :class="tab === t.key ? 'bg-white text-blue-600 shadow-sm' : 'text-slate-500'"
                    @click="switchTab(t.key)"
                >{{ t.label }}</button>
            </div>
        </header>

        <!-- Flash message -->
        <p v-if="message" class="fixed left-1/2 top-4 z-50 -translate-x-1/2 rounded-full bg-slate-800 px-4 py-2 text-sm text-white shadow-lg">
            {{ message }}
        </p>

        <div v-if="loading" class="flex justify-center py-16">
            <div class="h-8 w-8 animate-spin rounded-full border-4 border-blue-200 border-t-blue-600"></div>
        </div>

        <div v-else class="px-4 py-4">

            <!-- ─── آمار ─────────────────────────────────────────── -->
            <div v-if="tab === 'stats' && stats">
                <button
                    class="mb-4 w-full rounded-2xl py-3 text-sm font-bold active:scale-95"
                    :class="commentsEnabled ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700'"
                    @click="toggleComments"
                >{{ commentsEnabled ? '💬 نظرات/پاسخ‌ها: فعال (کلیک برای غیرفعال)' : '🔕 نظرات/پاسخ‌ها: غیرفعال (کلیک برای فعال)' }}</button>
                <div class="grid grid-cols-2 gap-3">
                    <div v-for="item in [
                        { label:'کاربران', value:stats.users, icon:'👥' },
                        { label:'کل مشکلات', value:stats.problems_total, icon:'📋' },
                        { label:'در انتظار', value:stats.problems_pending, icon:'⏳' },
                        { label:'تأییدشده', value:stats.problems_approved, icon:'✅' },
                        { label:'ردشده', value:stats.problems_rejected, icon:'❌' },
                        { label:'برگزیده', value:stats.problems_featured, icon:'⭐' },
                        { label:'راه‌حل‌ها', value:stats.solutions, icon:'💡' },
                        { label:'دیدگاه‌ها', value:stats.comments, icon:'💬' },
                        { label:'حمایت‌ها', value:stats.supports, icon:'🤝' },
                    ]" :key="item.label" class="rounded-3xl bg-white p-4 text-center shadow-sm">
                        <div class="text-2xl">{{ item.icon }}</div>
                        <div class="mt-1 text-2xl font-extrabold text-slate-800">{{ item.value }}</div>
                        <div class="text-xs text-slate-400">{{ item.label }}</div>
                    </div>
                </div>
            </div>

            <!-- ─── در انتظار ──────────────────────────────────────── -->
            <div v-else-if="tab === 'pending'" class="space-y-3">
                <p v-if="!pending.length" class="py-10 text-center text-sm text-slate-400">مشکلی در انتظار نیست. 🎉</p>
                <div v-for="p in pending" :key="p.id" class="rounded-3xl bg-white p-4 shadow-sm">
                    <p class="font-bold text-slate-800">{{ p.title }}</p>
                    <p class="mt-1 line-clamp-3 text-sm text-slate-500">{{ p.description }}</p>
                    <p class="mt-2 text-xs text-slate-400">{{ p.user?.first_name || 'کاربر' }} · {{ p.category?.title || 'بدون دسته' }}</p>
                    <div class="mt-3 flex gap-2">
                        <button class="flex-1 rounded-xl bg-emerald-600 py-2 text-sm font-semibold text-white active:scale-95" @click="setStatus(p, 'approved')">تأیید</button>
                        <button class="flex-1 rounded-xl bg-rose-600 py-2 text-sm font-semibold text-white active:scale-95" @click="setStatus(p, 'rejected')">رد</button>
                    </div>
                </div>
            </div>

            <!-- ─── مشکلات ─────────────────────────────────────────── -->
            <div v-else-if="tab === 'problems'" class="space-y-3">
                <!-- Referral modal -->
                <div v-if="referralProblem" class="fixed inset-0 z-40 flex items-end bg-black/40" @click.self="referralProblem = null">
                    <div class="w-full rounded-t-3xl bg-white p-5 space-y-4 max-h-[85dvh] overflow-y-auto">
                        <h2 class="font-bold text-slate-800">📨 ارجاع به مسئول</h2>
                        <p class="text-sm text-slate-500 line-clamp-2">{{ referralProblem.title }}</p>

                        <div>
                            <label class="text-xs font-semibold text-slate-600">مسئول</label>
                            <select v-model="referralOfficialId"
                                class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm outline-none focus:border-blue-500">
                                <option :value="null" disabled>انتخاب کنید…</option>
                                <option v-for="o in officials" :key="o.id" :value="o.id">{{ o.name }} – {{ o.position }}</option>
                            </select>
                            <p v-if="!officials.length" class="mt-1 text-xs text-slate-400">ابتدا در تب مسئولین، مسئولان را تعریف کنید.</p>
                        </div>

                        <div>
                            <label class="text-xs font-semibold text-slate-600">متن پیامک (قابل ویرایش)</label>
                            <textarea v-model="referralMsg" rows="6"
                                class="mt-1 w-full resize-none rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm outline-none focus:border-blue-500"></textarea>
                        </div>

                        <!-- Previous referrals -->
                        <div v-if="referrals.length" class="space-y-2">
                            <p class="text-xs font-semibold text-slate-500">ارجاع‌های قبلی</p>
                            <div v-for="r in referrals" :key="r.id" class="rounded-xl bg-slate-50 p-3 text-xs text-slate-600">
                                <span class="font-semibold">{{ r.official?.name }}</span> —
                                {{ r.sent_at ? new Date(r.sent_at).toLocaleDateString('fa-IR') : 'ارسال ناموفق' }}
                            </div>
                        </div>

                        <div class="flex gap-3">
                            <button class="flex-1 rounded-xl bg-slate-100 py-2.5 text-sm font-semibold text-slate-600" @click="referralProblem = null">انصراف</button>
                            <button
                                :disabled="referralLoading"
                                class="flex-1 rounded-xl bg-blue-600 py-2.5 text-sm font-semibold text-white active:scale-95 disabled:opacity-60"
                                @click="sendReferral">{{ referralLoading ? 'در حال ارسال…' : 'ارسال پیامک' }}
                            </button>
                        </div>
                    </div>
                </div>

                <div class="flex gap-2">
                    <input v-model="search" placeholder="جستجو…" class="flex-1 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm outline-none focus:border-blue-500" @keyup.enter="loadProblems" />
                    <select v-model="statusFilter" class="rounded-xl border border-slate-200 bg-white px-2 py-2 text-sm" @change="loadProblems">
                        <option value="">همه</option>
                        <option value="pending">در انتظار</option>
                        <option value="approved">تأییدشده</option>
                        <option value="rejected">ردشده</option>
                    </select>
                </div>

                <p v-if="!problems.length" class="py-10 text-center text-sm text-slate-400">موردی یافت نشد.</p>
                <div v-for="p in problems" :key="p.id" class="rounded-3xl bg-white p-4 shadow-sm">
                    <div class="flex items-start justify-between gap-2">
                        <p class="font-bold text-slate-800">{{ p.title }}</p>
                        <span class="shrink-0 rounded-full px-2 py-0.5 text-xs" :class="statusColor[p.status]">{{ statusLabel[p.status] }}</span>
                    </div>
                    <p class="mt-2 text-xs text-slate-400">⭐ {{ p.is_featured ? 'برگزیده' : '—' }} · 🤝 {{ p.supports_count }} · 💡 {{ p.solutions_count }}</p>
                    <div class="mt-3 flex flex-wrap gap-2 text-sm">
                        <button class="rounded-xl bg-amber-100 px-3 py-1.5 font-semibold text-amber-700 active:scale-95" @click="toggleFeatured(p)">{{ p.is_featured ? 'حذف از برگزیده' : 'برگزیده کن' }}</button>
                        <button v-if="p.status !== 'approved'" class="rounded-xl bg-emerald-100 px-3 py-1.5 font-semibold text-emerald-700 active:scale-95" @click="setStatus(p,'approved')">تأیید</button>
                        <button v-if="p.status !== 'rejected'" class="rounded-xl bg-orange-100 px-3 py-1.5 font-semibold text-orange-700 active:scale-95" @click="setStatus(p,'rejected')">رد</button>
                        <button class="rounded-xl bg-blue-100 px-3 py-1.5 font-semibold text-blue-700 active:scale-95" @click="openReferral(p)">📨 ارجاع</button>
                        <button class="rounded-xl bg-rose-100 px-3 py-1.5 font-semibold text-rose-700 active:scale-95" @click="removeProblem(p)">حذف</button>
                    </div>
                </div>
            </div>

            <!-- ─── کاربران ─────────────────────────────────────────── -->
            <div v-else-if="tab === 'users'" class="space-y-3">
                <input v-model="search" placeholder="جستجوی شماره یا نام…" dir="rtl"
                    class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm outline-none focus:border-blue-500" @keyup.enter="loadUsers" />
                <div v-for="u in users" :key="u.id" class="flex items-center justify-between rounded-3xl bg-white p-4 shadow-sm">
                    <div>
                        <p class="font-bold text-slate-800">{{ u.first_name || 'کاربر' }} {{ u.last_name || '' }}<span v-if="u.role==='admin'" class="text-xs"> 👑</span></p>
                        <p class="text-xs text-slate-400" dir="ltr">{{ u.phone }}</p>
                        <span v-if="u.label" class="mt-1 inline-block rounded-full bg-indigo-600 px-2 py-0.5 text-[10px] font-semibold text-white">{{ u.label }}</span>
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <!-- Regular admin: only toggle admin/user (not super_admin) -->
                        <template v-if="!isSuperAdmin">
                            <button class="rounded-xl px-3 py-1.5 text-xs font-semibold active:scale-95"
                                :class="u.role==='admin'?'bg-rose-100 text-rose-700':'bg-blue-100 text-blue-700'"
                                @click="toggleRole(u)"
                                :disabled="u.role==='super_admin'"
                            >{{ u.role==='admin'?'حذف ادمین':'ادمین کن' }}</button>
                        </template>
                        <!-- Super admin: full role control -->
                        <template v-else>
                            <button class="rounded-xl bg-purple-100 px-3 py-1.5 text-xs font-semibold text-purple-700 active:scale-95" @click="setSuperRole(u)">
                                👑 {{ u.role==='super_admin'?'ادمین کل':'تغییر نقش' }}
                            </button>
                            <button class="rounded-xl bg-rose-100 px-3 py-1.5 text-xs font-semibold text-rose-700 active:scale-95" @click="banUser(u)">🔒 مسدود</button>
                        </template>
                        <button class="rounded-xl bg-indigo-100 px-3 py-1.5 text-xs font-semibold text-indigo-700 active:scale-95" @click="setLabel(u)">🏷️ لیبل</button>
                    </div>
                </div>
            </div>

            <!-- ─── مسئولین ─────────────────────────────────────────── -->
            <div v-else-if="tab === 'officials'" class="space-y-4">

                <!-- Form -->
                <div class="rounded-3xl bg-white p-5 shadow-sm space-y-3">
                    <h2 class="font-bold text-slate-800">{{ officialForm.id ? 'ویرایش مسئول' : 'افزودن مسئول جدید' }}</h2>

                    <input v-model="officialForm.name" placeholder="نام و نام خانوادگی *" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm outline-none focus:border-blue-500" />
                    <input v-model="officialForm.position" placeholder="سمت / عنوان شغلی *" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm outline-none focus:border-blue-500" />
                    <div class="flex gap-2">
                        <input v-model="officialForm.phone" placeholder="شماره موبایل (اختیاری)" dir="ltr" maxlength="11"
                            class="flex-1 rounded-xl border border-slate-200 px-3 py-2 text-sm outline-none focus:border-blue-500"
                            @blur="searchLinkedUser" />
                        <button class="shrink-0 rounded-xl bg-slate-100 px-3 py-2 text-xs text-slate-600" @click="searchLinkedUser">جستجو</button>
                    </div>
                    <div v-if="linkedUser" class="rounded-xl bg-blue-50 px-3 py-2 text-xs text-blue-800">
                        ✅ کاربر موجود: {{ linkedUser.name || linkedUser.phone }} — حساب لینک می‌شود
                    </div>
                    <div v-else-if="officialForm.phone && officialForm.phone.length===11" class="text-xs text-slate-400">
                        کاربری با این شماره پیدا نشد — مسئول بدون حساب ثبت می‌شود
                    </div>
                    <textarea v-model="officialForm.notes" rows="2" placeholder="یادداشت (اختیاری)"
                        class="w-full resize-none rounded-xl border border-slate-200 px-3 py-2 text-sm outline-none focus:border-blue-500"></textarea>

                    <div class="flex gap-2">
                        <button v-if="officialForm.id" class="rounded-xl bg-slate-100 px-4 py-2 text-sm text-slate-600" @click="resetOfficialForm">انصراف</button>
                        <button :disabled="officialSaving" class="flex-1 rounded-xl bg-blue-600 py-2 text-sm font-semibold text-white active:scale-95 disabled:opacity-60" @click="saveOfficial">
                            {{ officialSaving ? 'در حال ذخیره…' : (officialForm.id ? 'بروزرسانی' : 'افزودن') }}
                        </button>
                    </div>
                </div>

                <!-- Officials list -->
                <div v-if="!officials.length" class="py-8 text-center text-sm text-slate-400">هنوز مسئولی تعریف نشده است.</div>
                <div v-for="o in officials" :key="o.id" class="rounded-3xl bg-white p-4 shadow-sm">
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <p class="font-bold text-slate-800">{{ o.name }}</p>
                            <p class="text-sm text-slate-500">{{ o.position }}</p>
                            <p v-if="o.phone" class="text-xs text-slate-400" dir="ltr">{{ o.phone }}</p>
                            <span v-if="o.user" class="mt-1 inline-block rounded-full bg-blue-100 px-2 py-0.5 text-[10px] text-blue-700">🔗 کاربر لینک‌شده</span>
                        </div>
                        <div class="flex flex-col gap-1.5 shrink-0">
                            <button class="rounded-xl bg-amber-100 px-3 py-1.5 text-xs font-semibold text-amber-700" @click="editOfficial(o)">✏️ ویرایش</button>
                            <button class="rounded-xl bg-rose-100 px-3 py-1.5 text-xs font-semibold text-rose-700" @click="deleteOfficial(o)">🗑️ حذف</button>
                        </div>
                    </div>
                    <p v-if="o.notes" class="mt-2 text-xs text-slate-400">{{ o.notes }}</p>
                </div>
            </div>

            <!-- ─── مجمع ────────────────────────────────────────────── -->
            <div v-else-if="tab === 'assembly'" class="space-y-4">

                <!-- Assembly stats bar -->
                <div v-if="assemblyStats" class="grid grid-cols-4 gap-2 text-center text-xs">
                    <div v-for="s in [
                        { label:'کل', value: assemblyStats.total, color:'text-slate-700' },
                        { label:'در انتظار', value: assemblyStats.pending, color:'text-amber-600' },
                        { label:'تأیید', value: assemblyStats.approved, color:'text-emerald-600' },
                        { label:'ثبت شده', value: assemblyStats.recorded, color:'text-blue-600' },
                    ]" :key="s.label" class="rounded-2xl bg-white p-3 shadow-sm">
                        <div class="text-xl font-extrabold" :class="s.color">{{ s.value }}</div>
                        <div class="text-slate-400">{{ s.label }}</div>
                    </div>
                </div>

                <!-- Sub-tab bar -->
                <div class="flex gap-1 rounded-2xl bg-slate-100 p-1 text-xs">
                    <button v-for="st in [{ key:'memberships',label:'عضویت‌ها'},{key:'roles',label:'مسئولیت‌ها'},{key:'settings',label:'تنظیمات'}]"
                        :key="st.key"
                        class="flex-1 rounded-xl py-2 font-semibold transition"
                        :class="assemblyTab===st.key?'bg-white text-blue-600 shadow-sm':'text-slate-500'"
                        @click="assemblyTab=st.key">{{ st.label }}</button>
                </div>

                <!-- Memberships list -->
                <div v-if="assemblyTab==='memberships'" class="space-y-3">
                    <div class="flex gap-2">
                        <select v-model="membershipFilter" class="flex-1 rounded-xl border border-slate-200 bg-white px-2 py-2 text-sm" @change="loadMemberships">
                            <option value="">همه وضعیت‌ها</option>
                            <option value="pending">در انتظار</option>
                            <option value="approved">تأیید شده</option>
                            <option value="rejected">رد شده</option>
                            <option value="recorded">ثبت شده</option>
                        </select>
                        <button class="rounded-xl bg-emerald-100 px-4 py-2 text-sm font-semibold text-emerald-700 active:scale-95" @click="exportCsvBlob">⬇️ CSV</button>
                    </div>

                    <p v-if="!memberships.length" class="py-8 text-center text-sm text-slate-400">عضوی یافت نشد.</p>
                    <div v-for="m in memberships" :key="m.id" class="rounded-3xl bg-white p-4 shadow-sm space-y-2">
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <p class="font-bold text-slate-800">{{ m.user?.name?.trim() || 'کاربر' }}</p>
                                <p class="text-xs text-slate-400" dir="ltr">{{ m.user?.phone }}</p>
                            </div>
                            <span class="shrink-0 rounded-full px-2 py-0.5 text-xs" :class="membershipStatusColor[m.status]">{{ membershipStatusLabel[m.status] }}</span>
                        </div>

                        <!-- Selected roles -->
                        <div class="flex flex-wrap gap-1">
                            <span v-for="roleId in m.roles" :key="roleId"
                                class="rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-600">
                                {{ assemblyRoles.find(r=>r.id===roleId)?.title || roleId }}
                            </span>
                        </div>

                        <p v-if="m.description" class="text-xs text-slate-500 line-clamp-3">{{ m.description }}</p>
                        <p v-if="m.admin_note" class="text-xs text-rose-600">یادداشت: {{ m.admin_note }}</p>
                        <p class="text-xs text-slate-400">{{ new Date(m.created_at).toLocaleDateString('fa-IR') }}</p>

                        <div class="flex flex-wrap gap-2 pt-1">
                            <button v-if="m.status!=='approved'" class="rounded-xl bg-emerald-100 px-3 py-1.5 text-xs font-semibold text-emerald-700 active:scale-95" @click="updateMembership(m,'approved')">تأیید</button>
                            <button v-if="m.status!=='rejected'" class="rounded-xl bg-rose-100 px-3 py-1.5 text-xs font-semibold text-rose-700 active:scale-95" @click="updateMembership(m,'rejected')">رد</button>
                            <button v-if="m.status!=='recorded'" class="rounded-xl bg-blue-100 px-3 py-1.5 text-xs font-semibold text-blue-700 active:scale-95" @click="updateMembership(m,'recorded')">📋 ثبت شده</button>
                        </div>
                    </div>
                </div>

                <!-- Roles management -->
                <div v-else-if="assemblyTab==='roles'" class="space-y-3">
                    <div class="flex gap-2">
                        <input v-model="newRoleTitle" placeholder="عنوان مسئولیت جدید…"
                            class="flex-1 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm outline-none focus:border-blue-500"
                            @keyup.enter="addRole" />
                        <button class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-semibold text-white active:scale-95" @click="addRole">افزودن</button>
                    </div>
                    <p v-if="!assemblyRoles.length" class="py-6 text-center text-sm text-slate-400">هنوز مسئولیتی تعریف نشده.</p>
                    <div v-for="role in assemblyRoles" :key="role.id" class="flex items-center justify-between rounded-2xl bg-white px-4 py-3 shadow-sm">
                        <span class="text-sm text-slate-800">{{ role.title }}</span>
                        <button class="rounded-xl bg-rose-100 px-3 py-1.5 text-xs font-semibold text-rose-700 active:scale-95" @click="deleteRole(role)">حذف</button>
                    </div>
                </div>

                <!-- Settings: intro message -->
                <div v-else-if="assemblyTab==='settings'" class="space-y-3">
                    <label class="text-sm font-semibold text-slate-700">متن پیام بالای فرم عضویت</label>
                    <textarea v-model="assemblyIntro" rows="6" placeholder="این متن در بالای فرم عضویت نمایش داده می‌شود…"
                        class="w-full resize-none rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm outline-none focus:border-blue-500"></textarea>
                    <button :disabled="introSaving" class="w-full rounded-xl bg-blue-600 py-2.5 text-sm font-semibold text-white active:scale-95 disabled:opacity-60" @click="saveIntro">
                        {{ introSaving ? 'در حال ذخیره…' : 'ذخیره' }}
                    </button>
                </div>

            </div>

            <!-- ─── گزارش‌ها (super_admin) ───────────────────────── -->
            <div v-else-if="tab === 'reported' && isSuperAdmin" class="space-y-4">
                <p class="text-sm font-semibold text-slate-700">محتوای پنهان‌شده (بیش از آستانه گزارش)</p>

                <div v-if="!reportedSolutions.length && !reportedComments.length" class="py-10 text-center text-sm text-slate-400">
                    محتوای گزارش‌شده‌ای وجود ندارد. ✅
                </div>

                <!-- Reported solutions -->
                <template v-if="reportedSolutions.length">
                    <p class="text-xs font-bold text-slate-500 uppercase">راه‌حل‌ها</p>
                    <div v-for="s in reportedSolutions" :key="s.id" class="rounded-3xl bg-white p-4 shadow-sm space-y-2">
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <p class="text-sm text-slate-700 leading-6">{{ s.body }}</p>
                                <p class="text-xs text-slate-400">{{ s.user?.name }} | مشکل: {{ s.problem?.title }}</p>
                            </div>
                            <span class="shrink-0 rounded-full bg-rose-100 px-2 py-0.5 text-xs text-rose-700">{{ s.reports_count }} گزارش</span>
                        </div>
                        <div class="flex gap-2">
                            <button class="flex-1 rounded-xl bg-emerald-100 py-1.5 text-xs font-semibold text-emerald-700" @click="reviewContent('solution', s, 'restore')">✅ بازگردانی</button>
                            <button class="flex-1 rounded-xl bg-slate-100 py-1.5 text-xs font-semibold text-slate-600" @click="reviewContent('solution', s, 'remove')">🚫 تأیید پنهان</button>
                            <button class="rounded-xl bg-rose-100 px-3 py-1.5 text-xs font-semibold text-rose-700" @click="banUser(s.user)">🔒 مسدود</button>
                        </div>
                    </div>
                </template>

                <!-- Reported comments -->
                <template v-if="reportedComments.length">
                    <p class="text-xs font-bold text-slate-500 uppercase">پاسخ‌ها</p>
                    <div v-for="c in reportedComments" :key="c.id" class="rounded-3xl bg-white p-4 shadow-sm space-y-2">
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <p class="text-sm text-slate-700 leading-6">{{ c.body }}</p>
                                <p class="text-xs text-slate-400">{{ c.user?.name }}</p>
                            </div>
                            <span class="shrink-0 rounded-full bg-rose-100 px-2 py-0.5 text-xs text-rose-700">{{ c.reports_count }} گزارش</span>
                        </div>
                        <div class="flex gap-2">
                            <button class="flex-1 rounded-xl bg-emerald-100 py-1.5 text-xs font-semibold text-emerald-700" @click="reviewContent('comment', c, 'restore')">✅ بازگردانی</button>
                            <button class="flex-1 rounded-xl bg-slate-100 py-1.5 text-xs font-semibold text-slate-600" @click="reviewContent('comment', c, 'remove')">🚫 تأیید پنهان</button>
                            <button class="rounded-xl bg-rose-100 px-3 py-1.5 text-xs font-semibold text-rose-700" @click="banUser(c.user)">🔒 مسدود</button>
                        </div>
                    </div>
                </template>
            </div>

            <!-- ─── تنظیمات کلی (super_admin only) ───────────────── -->
            <div v-else-if="tab === 'super_settings' && isSuperAdmin" class="space-y-4">
                <div class="rounded-3xl bg-white p-5 shadow-sm space-y-4">
                    <h2 class="font-bold text-slate-800">📱 تنظیمات پیامک (IPPanel)</h2>
                    <input v-model="superSettings.ippanel_api_key" placeholder="API Key" dir="ltr" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm outline-none focus:border-blue-500" />
                    <input v-model="superSettings.ippanel_sender" placeholder="شماره فرستنده" dir="ltr" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm outline-none focus:border-blue-500" />
                    <div class="flex gap-2">
                        <input v-model="superSettings.ippanel_otp_pattern_code" placeholder="کد پترن OTP" dir="ltr" class="flex-1 rounded-xl border border-slate-200 px-3 py-2 text-sm outline-none focus:border-blue-500" />
                        <input v-model="superSettings.ippanel_otp_pattern_variable" placeholder="متغیر (مثال: code)" dir="ltr" class="w-28 rounded-xl border border-slate-200 px-3 py-2 text-sm outline-none focus:border-blue-500" />
                    </div>
                </div>

                <div class="rounded-3xl bg-white p-5 shadow-sm space-y-4">
                    <h2 class="font-bold text-slate-800">🏛️ بخش مجمع / مشارکت</h2>
                    <input v-model="superSettings.assembly_nav_label" placeholder="برچسب منو (مثال: مشارکت)" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm outline-none focus:border-blue-500" />
                    <input v-model="superSettings.assembly_section_title" placeholder="عنوان صفحه عضویت" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm outline-none focus:border-blue-500" />
                    <textarea v-model="superSettings.assembly_intro_message" rows="4" placeholder="متن پیام بالای فرم عضویت…" class="w-full resize-none rounded-xl border border-slate-200 px-3 py-2 text-sm outline-none focus:border-blue-500"></textarea>
                </div>

                <div class="rounded-3xl bg-white p-5 shadow-sm space-y-4">
                    <h2 class="font-bold text-slate-800">⚙️ تنظیمات عمومی</h2>
                    <label class="flex items-center gap-3 cursor-pointer">
                        <input type="checkbox" v-model="superSettings.guest_can_view" class="h-4 w-4 rounded accent-blue-600" />
                        <span class="text-sm text-slate-700">کاربران مهمان می‌توانند مشکلات را مشاهده کنند</span>
                    </label>
                    <label class="flex items-center gap-3 cursor-pointer">
                        <input type="checkbox" v-model="superSettings.comments_enabled" class="h-4 w-4 rounded accent-blue-600" />
                        <span class="text-sm text-slate-700">نظرات و پاسخ‌ها فعال باشند</span>
                    </label>
                    <div class="flex items-center gap-3">
                        <label class="text-sm text-slate-700 shrink-0">آستانه گزارش (خودکار پنهان):</label>
                        <input v-model.number="superSettings.report_threshold" type="number" min="1" max="100" class="w-20 rounded-xl border border-slate-200 px-3 py-2 text-sm outline-none focus:border-blue-500" />
                        <span class="text-xs text-slate-400">گزارش</span>
                    </div>
                </div>

                <button :disabled="superSaving" class="w-full rounded-xl bg-blue-600 py-3 font-semibold text-white active:scale-95 disabled:opacity-60" @click="saveSuperSettings">
                    {{ superSaving ? 'در حال ذخیره…' : '💾 ذخیره تنظیمات' }}
                </button>
            </div>

        </div>
    </div>
</template>
