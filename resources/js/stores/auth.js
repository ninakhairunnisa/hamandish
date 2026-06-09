import { defineStore } from 'pinia';
import api from '../api';
import { messenger } from '../messenger';

export const useAuthStore = defineStore('auth', {
    state: () => ({
        token: localStorage.getItem('token') || null,
        user: null,
        // idle | loading | authenticated | need_contact | error | web_login
        status: 'idle',
        botDeepLink: null,
        error: null,
        // OTP web-login sub-state
        otpPhone: '',
        otpSent: false,
        otpError: null,
        otpLoading: false,
    }),

    getters: {
        isAuthenticated: (s) => !!s.token,
    },

    actions: {
        setToken(token) {
            this.token = token;
            localStorage.setItem('token', token);
        },

        logout() {
            this.token = null;
            this.user = null;
            localStorage.removeItem('token');
        },

        async fetchMe() {
            const { data } = await api.get('/auth/me');
            this.user = data.data ?? data;
            return this.user;
        },

        // Bootstrap: if we already have a token use it, otherwise try the
        // messenger mini-app login (Bale / Eitaa) via signed initData.
        // Outside a messenger we fall back to the phone+OTP web login.
        async bootstrap() {
            this.status = 'loading';
            messenger.init();

            if (this.token) {
                try {
                    await this.fetchMe();
                    this.status = 'authenticated';
                    return;
                } catch (_) {
                    this.logout();
                }
            }

            if (!messenger.available) {
                this.status = 'web_login';
                return;
            }

            await this.loginWithMessenger();
        },

        async loginWithMessenger() {
            this.status = 'loading';
            try {
                const { data } = await api.post('/auth/messenger', {
                    provider: messenger.provider,
                    init_data: messenger.initData(),
                });
                this.setToken(data.token);
                this.user = data.user.data ?? data.user;
                this.status = 'authenticated';
            } catch (err) {
                const res = err.response;
                if (res?.status === 409 && res.data?.need_contact) {
                    this.botDeepLink = res.data.bot_deep_link;
                    this.status = 'need_contact';
                } else {
                    this.error = res?.data?.message || 'خطا در ورود.';
                    this.status = 'error';
                }
            }
        },

        async shareContact() {
            await messenger.requestContact(this.botDeepLink);
            // The bot webhook links the phone server-side; poll the login a few
            // times so the user doesn't have to press "retry" manually.
            for (let attempt = 0; attempt < 5; attempt++) {
                await new Promise((r) => setTimeout(r, 2000));
                await this.loginWithMessenger();
                if (this.status === 'authenticated') return;
            }
        },

        // ── Phone + OTP login (web browsers, outside Bale/Eitaa) ──────────
        async sendOtp(phone) {
            this.otpLoading = true;
            this.otpError = null;
            try {
                await api.post('/auth/send-otp', { phone });
                this.otpPhone = phone;
                this.otpSent = true;
            } catch (err) {
                this.otpError =
                    err.response?.data?.errors?.phone?.[0] ||
                    err.response?.data?.message ||
                    'خطا در ارسال کد.';
            } finally {
                this.otpLoading = false;
            }
        },

        async verifyOtp(code) {
            this.otpLoading = true;
            this.otpError = null;
            try {
                const { data } = await api.post('/auth/verify-otp', {
                    phone: this.otpPhone,
                    token: code,
                });
                this.setToken(data.token);
                this.user = data.user.data ?? data.user;
                this.status = 'authenticated';
            } catch (err) {
                this.otpError =
                    err.response?.data?.errors?.token?.[0] ||
                    err.response?.data?.message ||
                    'کد وارد شده صحیح نیست.';
            } finally {
                this.otpLoading = false;
            }
        },

        resetOtp() {
            this.otpSent = false;
            this.otpError = null;
        },
    },
});
