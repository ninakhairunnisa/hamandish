import { defineStore } from 'pinia';

// Client-side cart. Persisted to localStorage so it survives reloads.
// Customer details are only collected at checkout (not stored here).
const STORAGE_KEY = 'cart';

function load() {
    try {
        return JSON.parse(localStorage.getItem(STORAGE_KEY)) || [];
    } catch (_) {
        return [];
    }
}

export const useCartStore = defineStore('cart', {
    state: () => ({
        // items: [{ id, name, price, image_url, stock, quantity }]
        items: load(),
    }),

    getters: {
        count: (s) => s.items.reduce((n, i) => n + i.quantity, 0),
        total: (s) => s.items.reduce((n, i) => n + i.price * i.quantity, 0),
        isEmpty: (s) => s.items.length === 0,
    },

    actions: {
        persist() {
            localStorage.setItem(STORAGE_KEY, JSON.stringify(this.items));
        },

        add(product, qty = 1) {
            const existing = this.items.find((i) => i.id === product.id);
            const max = product.stock ?? 999;
            if (existing) {
                existing.quantity = Math.min(existing.quantity + qty, max);
            } else {
                this.items.push({
                    id: product.id,
                    name: product.name,
                    price: product.price,
                    image_url: product.image_url,
                    stock: max,
                    quantity: Math.min(qty, max),
                });
            }
            this.persist();
        },

        setQuantity(id, qty) {
            const item = this.items.find((i) => i.id === id);
            if (!item) return;
            item.quantity = Math.max(1, Math.min(qty, item.stock ?? 999));
            this.persist();
        },

        remove(id) {
            this.items = this.items.filter((i) => i.id !== id);
            this.persist();
        },

        clear() {
            this.items = [];
            this.persist();
        },

        // Payload for the checkout endpoint.
        orderItems() {
            return this.items.map((i) => ({ product_id: i.id, quantity: i.quantity }));
        },
    },
});
