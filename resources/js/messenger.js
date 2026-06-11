// Abstraction over the Bale / Eitaa (Telegram-style) mini-app host SDKs.
// Detects the environment, exposes the signed initData, and provides the
// contact-share + UI helpers the app needs. Degrades gracefully in a browser.

// Provider can be forced via the mini-app URL (?provider=eitaa|bale).
// We persist it because SPA navigation may drop the query string.
function detectForcedProvider() {
    const fromQuery = new URLSearchParams(window.location.search).get('provider');
    if (fromQuery === 'eitaa' || fromQuery === 'bale') {
        sessionStorage.setItem('messenger_provider', fromQuery);
        return fromQuery;
    }
    const stored = sessionStorage.getItem('messenger_provider');
    return stored === 'eitaa' || stored === 'bale' ? stored : null;
}

// Telegram-style hosts put the signed init-data in the URL fragment
// (#tgWebAppData=...) before any SDK loads. This is the most reliable
// source in Eitaa, where no JS SDK may be injected at all.
function initDataFromHash() {
    const hash = window.location.hash.slice(1);
    if (!hash) return '';
    const params = new URLSearchParams(hash);
    const data = params.get('tgWebAppData');
    if (data) {
        sessionStorage.setItem('messenger_init_data', data);
        return data;
    }
    return sessionStorage.getItem('messenger_init_data') || '';
}

function pickWebApp() {
    const forced = detectForcedProvider();

    // Bale exposes window.Bale.WebApp; Eitaa exposes window.Eitaa.WebApp;
    // both mirror the Telegram WebApp surface (window.Telegram.WebApp).
    if (window.Eitaa?.WebApp) return { provider: 'eitaa', wa: window.Eitaa.WebApp };
    if (window.Bale?.WebApp) return { provider: forced ?? 'bale', wa: window.Bale.WebApp };
    if (window.Telegram?.WebApp && window.Telegram.WebApp.initData) {
        return { provider: forced ?? 'bale', wa: window.Telegram.WebApp };
    }

    // No SDK object — fall back to the URL-fragment init-data if the host
    // provided it (Eitaa does), keyed by the forced provider.
    if (forced && initDataFromHash()) {
        return { provider: forced, wa: null };
    }

    return { provider: null, wa: null };
}

const { provider, wa } = pickWebApp();
const hashInitData = initDataFromHash();
// The effective init-data we'll send to the server for HMAC validation.
const resolvedInitData = wa?.initData || hashInitData || '';

export const messenger = {
    provider,
    // Available only when we have BOTH a provider and non-empty signed init-data.
    // Checking initData here prevents the Bale SDK (loaded on every page) from
    // falsely activating when the app is opened in a plain browser — the SDK
    // sets window.Bale.WebApp but leaves initData empty outside the Bale app.
    available: !!provider && !!resolvedInitData,

    init() {
        try {
            wa?.ready?.();
            wa?.expand?.();
        } catch (_) { /* noop */ }
    },

    // The signed init-data string the backend validates via HMAC.
    initData() {
        return resolvedInitData;
    },

    // Best-effort unsigned user info for optimistic UI only (never trusted).
    unsafeUser() {
        if (wa?.initDataUnsafe?.user) return wa.initDataUnsafe.user;
        // Fall back to parsing the raw init-data (Eitaa hash fallback).
        try {
            const params = new URLSearchParams(this.initData());
            const user = params.get('user');
            return user ? JSON.parse(user) : null;
        } catch (_) {
            return null;
        }
    },

    // Messenger account id of the CURRENT session (string) or null.
    currentUserId() {
        const id = this.unsafeUser()?.id;
        return id === undefined || id === null ? null : String(id);
    },

    // Ask the host to prompt the user to share their phone with the bot.
    // Resolves { ok, response } — `response` is the signed contact payload
    // some hosts (Eitaa) hand straight back to the web app; the backend
    // validates its HMAC and completes login without the bot webhook.
    // Falls back to deep-linking the bot when requestContact is unsupported.
    requestContact(botDeepLink) {
        if (wa?.requestContact) {
            return new Promise((resolve) => {
                try {
                    wa.requestContact((ok, result) => {
                        const raw = result?.response ?? result?.responseUnsafe ?? result;
                        const response = typeof raw === 'string' ? raw : '';
                        resolve({ ok: !!ok, response });
                    });
                } catch (_) {
                    this.openBot(botDeepLink);
                    resolve({ ok: false, response: '' });
                }
            });
        }
        this.openBot(botDeepLink);
        return Promise.resolve({ ok: false, response: '' });
    },

    openBot(url) {
        if (wa?.openTelegramLink) return wa.openTelegramLink(url);
        if (wa?.openLink) return wa.openLink(url);
        window.open(url, '_blank');
    },

    haptic(type = 'light') {
        try { wa?.HapticFeedback?.impactOccurred?.(type); } catch (_) { /* noop */ }
    },
};
