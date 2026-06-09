import { defineStore } from 'pinia';
import api from '../api';
import { messenger } from '../messenger';

export const useAuthStore = defineStore('auth', {
    state: () => ({
        token: localStorage.getItem('token') || null,
        user: null,
        status: 'idle', // idle | loading | authenticated | need_contact | error | no_messenger
        botDeepLink: null,
        error: null,
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
                this.status = 'no_messenger';
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
            // After the user shares their contact with the bot, retry login.
            // (The bot webhook links the phone server-side.)
        },
    },
});
