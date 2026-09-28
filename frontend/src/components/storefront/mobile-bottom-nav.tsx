"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import { Home, ShoppingBag, ShoppingCart } from "lucide-react";

import { cn } from "@/lib/utils";
import { useCartStore } from "@/stores/cart-store";

const NAV_ITEMS = [
  { href: "/", label: "Home", icon: Home },
  { href: "/products", label: "Shop", icon: ShoppingBag },
] as const;

export function MobileBottomNav() {
  const pathname = usePathname();
  const { items, openCart } = useCartStore();
  const itemCount = items.reduce((sum, item) => sum + item.quantity, 0);

  return (
    <nav className="fixed inset-x-0 bottom-0 z-40 flex h-14 items-center border-t border-border bg-surface desktop:hidden">
      {NAV_ITEMS.map(({ href, label, icon: Icon }) => {
        const active = href === "/" ? pathname === "/" : pathname.startsWith(href);
        return (
          <Link
            key={href}
            href={href}
            className={cn(
              "flex flex-1 flex-col items-center gap-0.5 text-xs",
              active ? "text-primary" : "text-text-muted",
            )}
          >
            <Icon className="size-5" />
            {label}
          </Link>
        );
      })}
      <button
        type="button"
        onClick={openCart}
        className="relative flex flex-1 flex-col items-center gap-0.5 text-xs text-text-muted"
      >
        <ShoppingCart className="size-5" />
        Cart
        {itemCount > 0 ? (
          <span className="absolute right-[28%] top-0 flex size-4 items-center justify-center rounded-full bg-primary text-[10px] font-semibold text-white">
            {itemCount > 9 ? "9+" : itemCount}
          </span>
        ) : null}
      </button>
    </nav>
  );
}
