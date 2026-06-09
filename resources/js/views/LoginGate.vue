<script setup>
import { useAuthStore } from '../stores/auth';

defineProps({ status: String });
const auth = useAuthStore();
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

        <!-- Opened outside Bale / Eitaa -->
        <template v-else-if="status === 'no_messenger'">
            <p class="text-slate-600">
                این وب‌اپ برای استفاده داخل پیام‌رسان‌های «بله» و «ایتا» طراحی شده است.
                لطفاً آن را از داخل ربات هم‌اندیش باز کنید.
            </p>
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
