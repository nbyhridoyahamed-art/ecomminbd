"use client";

import Link from "next/link";
import { ShoppingCart } from "lucide-react";

import { formatMoney } from "@/lib/money";
import { Button } from "@/components/ui/button";
import { EmptyState } from "@/components/ui/empty-state";
import { CartLineItem } from "@/components/storefront/cart-line-item";
import { useCartStore } from "@/stores/cart-store";

export default function CartPage() {
  const { items } = useCartStore();
  const subtotal = items.reduce((sum, item) => sum + item.unitPrice * item.quantity, 0);
  const currencyCode = items[0]?.currencyCode ?? "BDT";

  if (items.length === 0) {
    return (
      <div className="mx-auto max-w-[1400px] px-4 py-16">
        <EmptyState
          icon={<ShoppingCart />}
          title="Your cart is empty"
          description="Browse products and add something you like."
          action={
            <Button asChild>
              <Link href="/products">Browse products</Link>
            </Button>
          }
        />
      </div>
    );
  }

  return (
    <div className="mx-auto max-w-[1400px] px-4 py-8">
      <h1 className="mb-6 text-page-title font-semibold text-text-primary">Your Cart</h1>

      <div className="grid gap-8 desktop:grid-cols-3">
        <div className="divide-y divide-border rounded-lg border border-border px-4 desktop:col-span-2">
          {items.map((item) => (
            <CartLineItem key={`${item.productId}:${item.variantId ?? "0"}`} item={item} />
          ))}
        </div>

        <div className="space-y-4 self-start rounded-lg border border-border p-4">
          <div className="flex justify-between font-semibold text-text-primary">
            <span>Subtotal</span>
            <span>{formatMoney(subtotal, currencyCode)}</span>
          </div>
          <p className="text-xs text-text-muted">
            Prices shown are estimates from when items were added. The exact total is confirmed at checkout.
          </p>
          <Button asChild size="lg" className="w-full">
            <Link href="/checkout">Proceed to Checkout</Link>
          </Button>
          <Button asChild variant="outline" className="w-full">
            <Link href="/products">Continue shopping</Link>
          </Button>
        </div>
      </div>
    </div>
  );
}
