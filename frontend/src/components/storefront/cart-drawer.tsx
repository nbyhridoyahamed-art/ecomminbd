"use client";

import Link from "next/link";
import { ShoppingCart } from "lucide-react";

import { formatMoney } from "@/lib/money";
import { Button } from "@/components/ui/button";
import { EmptyState } from "@/components/ui/empty-state";
import { Separator } from "@/components/ui/separator";
import { Sheet, SheetContent, SheetHeader, SheetTitle } from "@/components/ui/sheet";
import { CartLineItem } from "@/components/storefront/cart-line-item";
import { useCartStore } from "@/stores/cart-store";

export function CartDrawer() {
  const { items, isOpen, closeCart } = useCartStore();
  const currencyCode = items[0]?.currencyCode ?? "BDT";
  const subtotal = items.reduce((sum, item) => sum + item.unitPrice * item.quantity, 0);

  return (
    <Sheet open={isOpen} onOpenChange={(open) => (open ? undefined : closeCart())}>
      <SheetContent side="right" className="flex w-full max-w-md flex-col">
        <SheetHeader>
          <SheetTitle>Your Cart</SheetTitle>
        </SheetHeader>

        {items.length === 0 ? (
          <EmptyState
            icon={<ShoppingCart />}
            title="Your cart is empty"
            description="Browse products and add something you like."
            action={
              <Button asChild variant="secondary" onClick={closeCart}>
                <Link href="/products">Continue shopping</Link>
              </Button>
            }
          />
        ) : (
          <>
            <div className="flex-1 divide-y divide-border overflow-y-auto">
              {items.map((item) => (
                <CartLineItem key={`${item.productId}:${item.variantId ?? "0"}`} item={item} />
              ))}
            </div>

            <Separator />

            <div className="space-y-3">
              <p className="text-xs text-text-muted">
                Prices shown are estimates from when items were added. The exact total is confirmed at checkout.
              </p>
              <div className="flex items-center justify-between text-sm font-semibold text-text-primary">
                <span>Subtotal</span>
                <span>{formatMoney(subtotal, currencyCode)}</span>
              </div>
              <div className="grid grid-cols-2 gap-2">
                <Button asChild variant="outline" onClick={closeCart}>
                  <Link href="/cart">View cart</Link>
                </Button>
                <Button asChild onClick={closeCart}>
                  <Link href="/checkout">Checkout</Link>
                </Button>
              </div>
            </div>
          </>
        )}
      </SheetContent>
    </Sheet>
  );
}
