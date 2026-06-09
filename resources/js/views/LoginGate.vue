<script setup>
import { ref } from 'vue';
import { useAuthStore } from '../stores/auth';

defineProps({ status: String });
const auth = useAuthStore();

const phone = ref('');
const code = ref('');

function submitPhone() {
    if (/^09\d{9}$/.test(phone.value)) auth.sendOtp(phone.value);
    else auth.otpError = 'شماره موبایل معتبر وارد کنید (مثال: 09123456789)';
}

function submitCode() {
    if (/^\d{5}$/.test(code.value)) auth.verifyOtp(code.value);
    else auth.otpError = 'کد ۵ رقمی را وارد کنید.';
}
</script>

<template>
    <div class="flex min-h-dvh flex-col items-center justify-center gap-6 px-8 text-center">
        <div class="flex items-center gap-2 text-2xl font-bold text-slate-800">
            <span>🗣️</span><span>هم‌اندیش</span>
        </div>

        <!-- Verified messenger user, needs to share phone -->
        <template v-if="status === 'need_contact'">
            <p class="text-slate-600">
                برای ورود و همگام‌سازی حساب، لطفاً شماره تماس خود را با ربات هم‌اندیش به اشتراک بگذارید.
            </p>
            <button
                class="w-full rounded-2xl bg-blue-600 py-3 font-semibold text-white shadow-lg shadow-blue-200 active:scale-95"
                @click="auth.shareContact()"
            >
                اشتراک‌گذاری شماره تماس
            </button>
            <button class="text-sm text-blue-600" @click="auth.loginWithMessenger()">
                شماره را به اشتراک گذاشتم، تلاش دوباره
            </button>
        </template>

        <!-- Web browser: phone + OTP login -->
        <template v-else-if="status === 'web_login'">
            <template v-if="!auth.otpSent">
                <p class="text-slate-600">برای ورود، شماره موبایل خود را وارد کنید.</p>
                <form class="w-full space-y-3" @submit.prevent="submitPhone">
                    <input
                        v-model.trim="phone"
                        type="tel"
                        inputmode="numeric"
                        dir="ltr"
                        maxlength="11"
                        placeholder="09123456789"
                        class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-center tracking-widest outline-none focus:border-blue-500"
                    />
                    <button
                        type="submit"
                        :disabled="auth.otpLoading"
                        class="w-full rounded-2xl bg-blue-600 py-3 font-semibold text-white shadow-lg shadow-blue-200 active:scale-95 disabled:opacity-60"
                    >
                        {{ auth.otpLoading ? 'در حال ارسال…' : 'دریافت کد تأیید' }}
                    </button>
                </form>
            </template>

            <template v-else>
                <p class="text-slate-600">
                    کد ۵ رقمی ارسال‌شده به <span dir="ltr">{{ auth.otpPhone }}</span> را وارد کنید.
                </p>
                <form class="w-full space-y-3" @submit.prevent="submitCode">
                    <input
                        v-model.trim="code"
                        type="text"
                        inputmode="numeric"
                        dir="ltr"
                        maxlength="5"
                        placeholder="•••••"
                        class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-center text-xl tracking-[0.5em] outline-none focus:border-blue-500"
                    />
                    <button
                        type="submit"
                        :disabled="auth.otpLoading"
                        class="w-full rounded-2xl bg-blue-600 py-3 font-semibold text-white shadow-lg shadow-blue-200 active:scale-95 disabled:opacity-60"
                    >
                        {{ auth.otpLoading ? 'در حال بررسی…' : 'ورود' }}
                    </button>
                </form>
                <button class="text-sm text-blue-600" @click="auth.resetOtp()">تغییر شماره</button>
            </template>

            <p v-if="auth.otpError" class="text-sm text-rose-600">{{ auth.otpError }}</p>
        </template>

        <!-- Error -->
        <template v-else>
            <p class="text-rose-600">{{ auth.error || 'خطا در ورود.' }}</p>
            <button
                class="rounded-2xl bg-slate-800 px-6 py-3 font-semibold text-white active:scale-95"
                @click="auth.bootstrap()"
            >
                تلاش دوباره
            </button>
        </template>
    </div>
</template>
