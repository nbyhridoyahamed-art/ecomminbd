"use client";

import { useState } from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { Menu, Search, ShoppingCart, User } from "lucide-react";

import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Sheet, SheetContent, SheetHeader, SheetTitle } from "@/components/ui/sheet";
import { CategoryMegaMenu } from "@/components/storefront/category-mega-menu";
import { useStorefrontCategories, useStorefrontStore } from "@/hooks/use-storefront-catalog";
import { useCustomerAuthToken } from "@/lib/customer-auth-token";
import { useCartStore } from "@/stores/cart-store";

export function StorefrontHeader() {
  const router = useRouter();
  const { data: store } = useStorefrontStore();
  const { data: categories } = useStorefrontCategories();
  const { items, openCart } = useCartStore();
  const customerToken = useCustomerAuthToken();
  const [search, setSearch] = useState("");
  const [mobileNavOpen, setMobileNavOpen] = useState(false);

  const itemCount = items.reduce((sum, item) => sum + item.quantity, 0);

  const submitSearch = (event: React.FormEvent) => {
    event.preventDefault();
    const query = search.trim();
    router.push(query ? `/products?search=${encodeURIComponent(query)}` : "/products");
  };

  return (
    <header className="sticky top-0 z-40 border-b border-border bg-surface">
      <div className="mx-auto flex h-16 max-w-[1400px] items-center gap-4 px-4">
        <Sheet open={mobileNavOpen} onOpenChange={setMobileNavOpen}>
          <SheetContent side="left" className="w-72 p-0">
            <SheetHeader className="border-b border-border p-4">
              <SheetTitle>{store?.name ?? "Menu"}</SheetTitle>
            </SheetHeader>
            <nav className="flex flex-col gap-1 p-4">
              <Link href="/products" onClick={() => setMobileNavOpen(false)} className="rounded-md px-2 py-2 text-sm font-medium hover:bg-border/30">
                All Products
              </Link>
              <Link href="/brands" onClick={() => setMobileNavOpen(false)} className="rounded-md px-2 py-2 text-sm font-medium hover:bg-border/30">
                Brands
              </Link>
              {categories && categories.length > 0 ? (
                <>
                  <p className="mt-3 px-2 text-xs font-semibold uppercase text-text-muted">Categories</p>
                  {categories.map((category) => (
                    <Link
                      key={category.id}
                      href={`/category/${category.slug}`}
                      onClick={() => setMobileNavOpen(false)}
                      className="rounded-md px-2 py-2 text-sm hover:bg-border/30"
                    >
                      {category.name}
                    </Link>
                  ))}
                </>
              ) : null}
            </nav>
          </SheetContent>

          <Button
            type="button"
            variant="ghost"
            size="icon"
            className="desktop:hidden"
            aria-label="Open menu"
            onClick={() => setMobileNavOpen(true)}
          >
            <Menu />
          </Button>
        </Sheet>

        <Link href="/" className="shrink-0 text-lg font-semibold text-text-primary">
          {store?.name ?? "Store"}
        </Link>

        <nav className="hidden items-center gap-5 desktop:flex">
          <CategoryMegaMenu />
          <Link href="/products" className="text-sm font-medium text-text-primary hover:text-primary">
            All Products
          </Link>
          <Link href="/brands" className="text-sm font-medium text-text-primary hover:text-primary">
            Brands
          </Link>
        </nav>

        <form onSubmit={submitSearch} className="ml-auto flex flex-1 items-center gap-2 tablet:max-w-sm">
          <Input
            type="search"
            placeholder="Search products..."
            value={search}
            onChange={(event) => setSearch(event.target.value)}
            aria-label="Search products"
          />
          <Button type="submit" variant="secondary" size="icon" aria-label="Search">
            <Search className="size-4" />
          </Button>
        </form>

        <Button asChild variant="ghost" size="icon" className="shrink-0" aria-label={customerToken ? "My account" : "Sign in"}>
          <Link href="/account">
            <User />
          </Link>
        </Button>

        <Button type="button" variant="ghost" size="icon" className="relative shrink-0" aria-label="Open cart" onClick={openCart}>
          <ShoppingCart />
          {itemCount > 0 ? (
            <span className="absolute -right-1 -top-1 flex size-4 items-center justify-center rounded-full bg-primary text-[10px] font-semibold text-white">
              {itemCount > 9 ? "9+" : itemCount}
            </span>
          ) : null}
        </Button>
      </div>
    </header>
  );
}
