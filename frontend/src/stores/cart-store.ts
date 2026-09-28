"use client";

import { create } from "zustand";
import { persist } from "zustand/middleware";

import { trackEvent } from "@/lib/analytics";

// Mirrors CheckoutRequest's items.*.quantity cap on the backend — clamping
// here just avoids a late 422 surprise at checkout; the server is still the
// real limit.
const MAX_QUANTITY = 100;

export interface CartItem {
  productId: number;
  variantId: number | null;
  name: string;
  slug: string;
  variantLabel: string | null;
  imageUrl: string | null;
  /** A snapshot from when the item was added — checkout always recomputes the real, current price server-side. */
  unitPrice: number;
  currencyCode: string;
  quantity: number;
}

interface CartState {
  items: CartItem[];
  isOpen: boolean;
  addItem: (item: Omit<CartItem, "quantity">, quantity?: number) => void;
  removeItem: (productId: number, variantId: number | null) => void;
  updateQuantity: (productId: number, variantId: number | null, quantity: number) => void;
  clear: () => void;
  openCart: () => void;
  closeCart: () => void;
}

function lineKey(productId: number, variantId: number | null) {
  return `${productId}:${variantId ?? "0"}`;
}

export const useCartStore = create<CartState>()(
  persist(
    (set, get) => ({
      items: [],
      isOpen: false,

      addItem: (item, quantity = 1) => {
        const key = lineKey(item.productId, item.variantId);
        const existing = get().items.find((line) => lineKey(line.productId, line.variantId) === key);

        if (existing) {
          set({
            items: get().items.map((line) =>
              lineKey(line.productId, line.variantId) === key
                ? { ...line, quantity: Math.min(MAX_QUANTITY, line.quantity + quantity) }
                : line,
            ),
          });
        } else {
          set({ items: [...get().items, { ...item, quantity: Math.min(MAX_QUANTITY, quantity) }] });
        }

        set({ isOpen: true });
        trackEvent("add_to_cart", { product_id: item.productId });
      },

      removeItem: (productId, variantId) => {
        const key = lineKey(productId, variantId);
        set({ items: get().items.filter((line) => lineKey(line.productId, line.variantId) !== key) });
        trackEvent("remove_from_cart", { product_id: productId });
      },

      updateQuantity: (productId, variantId, quantity) => {
        if (quantity <= 0) {
          get().removeItem(productId, variantId);
          return;
        }

        const key = lineKey(productId, variantId);
        set({
          items: get().items.map((line) =>
            lineKey(line.productId, line.variantId) === key
              ? { ...line, quantity: Math.min(MAX_QUANTITY, quantity) }
              : line,
          ),
        });
      },

      clear: () => set({ items: [] }),
      openCart: () => set({ isOpen: true }),
      closeCart: () => set({ isOpen: false }),
    }),
    {
      // Only the items themselves survive a reload — isOpen is transient
      // UI state, and persisting it would leave the drawer stuck open.
      name: "nby-storefront-cart",
      partialize: (state) => ({ items: state.items }),
    },
  ),
);
