// Abstraction over the Bale / Eitaa (Telegram-style) mini-app host SDKs.
// Detects the environment, exposes the signed initData, and provides the
// contact-share + UI helpers the app needs. Degrades gracefully in a browser.

function pickWebApp() {
    // Bale exposes window.Bale.WebApp; Eitaa exposes window.Eitaa.WebApp;
    // both mirror the Telegram WebApp surface (window.Telegram.WebApp).
    if (window.Bale?.WebApp) return { provider: 'bale', wa: window.Bale.WebApp };
    if (window.Eitaa?.WebApp) return { provider: 'eitaa', wa: window.Eitaa.WebApp };
    if (window.Telegram?.WebApp) return { provider: 'bale', wa: window.Telegram.WebApp };
    return { provider: null, wa: null };
}

const { provider, wa } = pickWebApp();

export const messenger = {
    provider,
    available: !!wa,

    init() {
        try {
            wa?.ready?.();
            wa?.expand?.();
        } catch (_) { /* noop */ }
    },

    // The signed init-data string the backend validates via HMAC.
    initData() {
        return wa?.initData || '';
    },

    // Best-effort unsigned user info for optimistic UI only (never trusted).
    unsafeUser() {
        return wa?.initDataUnsafe?.user || null;
    },

    // Ask the host to prompt the user to share their phone with the bot.
    // Newer hosts support requestContact(); otherwise we deep-link the bot.
    requestContact(botDeepLink) {
        if (wa?.requestContact) {
            return new Promise((resolve) => {
                try {
                    wa.requestContact((ok) => resolve(!!ok));
                } catch (_) {
                    this.openBot(botDeepLink);
                    resolve(false);
                }
            });
        }
        this.openBot(botDeepLink);
        return Promise.resolve(false);
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
