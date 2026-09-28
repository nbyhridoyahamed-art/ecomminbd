"use client";

import { Minus, Package, Plus, X } from "lucide-react";

import { formatMoney } from "@/lib/money";
import { Button } from "@/components/ui/button";
import { useCartStore, type CartItem } from "@/stores/cart-store";

export function CartLineItem({ item }: { item: CartItem }) {
  const { updateQuantity, removeItem } = useCartStore();

  return (
    <div className="flex gap-3 py-3">
      <div className="flex size-16 shrink-0 items-center justify-center overflow-hidden rounded-md border border-border bg-border/20">
        {item.imageUrl ? (
          // eslint-disable-next-line @next/next/no-img-element -- remote storage URL, not a static asset
          <img src={item.imageUrl} alt={item.name} className="size-full object-cover" />
        ) : (
          <Package className="size-6 text-text-muted" />
        )}
      </div>

      <div className="flex flex-1 flex-col gap-1">
        <div className="flex items-start justify-between gap-2">
          <div>
            <p className="text-sm font-medium text-text-primary">{item.name}</p>
            {item.variantLabel ? <p className="text-xs text-text-muted">{item.variantLabel}</p> : null}
          </div>
          <button
            type="button"
            onClick={() => removeItem(item.productId, item.variantId)}
            aria-label={`Remove ${item.name}`}
            className="shrink-0 text-text-muted hover:text-danger"
          >
            <X className="size-4" />
          </button>
        </div>

        <div className="flex items-center justify-between">
          <div className="flex items-center gap-1">
            <Button
              type="button"
              variant="outline"
              size="icon"
              className="size-7"
              aria-label="Decrease quantity"
              onClick={() => updateQuantity(item.productId, item.variantId, item.quantity - 1)}
            >
              <Minus className="size-3" />
            </Button>
            <span className="w-8 text-center text-sm">{item.quantity}</span>
            <Button
              type="button"
              variant="outline"
              size="icon"
              className="size-7"
              aria-label="Increase quantity"
              onClick={() => updateQuantity(item.productId, item.variantId, item.quantity + 1)}
            >
              <Plus className="size-3" />
            </Button>
          </div>
          <span className="text-sm font-semibold text-text-primary">
            {formatMoney(item.unitPrice * item.quantity, item.currencyCode)}
          </span>
        </div>
      </div>
    </div>
  );
}
