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

        rememberMessengerUid() {
            const uid = messenger.currentUserId();
            if (uid) localStorage.setItem('messenger_uid', uid);
        },

        logout() {
            this.token = null;
            this.user = null;
            localStorage.removeItem('token');
            localStorage.removeItem('messenger_uid');
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

            // Session-mixup guard: if we're inside a messenger and the host
            // account differs from the one the stored token was issued for
            // (another Eitaa/Bale account on the same device), drop the token
            // and authenticate fresh — otherwise user B would land in user
            // A's profile (and even their admin panel).
            // Also drops legacy tokens that predate the uid bookkeeping
            // (tokenUid null) — re-login is instant for the same account.
            const currentUid = messenger.available ? messenger.currentUserId() : null;
            const tokenUid = localStorage.getItem('messenger_uid');
            if (this.token && currentUid && tokenUid !== currentUid) {
                this.logout();
            }

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
                this.rememberMessengerUid();
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
            const { ok, response } = await messenger.requestContact(this.botDeepLink);

            // Eitaa hands the signed contact straight back to the web app —
            // complete login directly without waiting for the bot webhook.
            if (ok && response) {
                try {
                    const { data } = await api.post('/auth/messenger/contact', {
                        provider: messenger.provider,
                        init_data: messenger.initData(),
                        contact_response: response,
                    });
                    this.setToken(data.token);
                    this.rememberMessengerUid();
                    this.user = data.user.data ?? data.user;
                    this.status = 'authenticated';
                    return;
                } catch (_) {
                    // fall through to webhook polling below
                }
            }

            // Otherwise the bot webhook links the phone server-side; poll the
            // login a few times so the user doesn't have to press "retry".
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
