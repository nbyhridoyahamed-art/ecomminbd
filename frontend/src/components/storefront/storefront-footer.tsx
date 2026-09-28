"use client";

import Link from "next/link";

import { useStorefrontCategories, useStorefrontStore } from "@/hooks/use-storefront-catalog";
import { useStorefrontPages } from "@/hooks/use-storefront-pages";

export function StorefrontFooter() {
  const { data: store } = useStorefrontStore();
  const { data: categories } = useStorefrontCategories();
  const { data: pages } = useStorefrontPages();

  return (
    <footer className="mt-auto border-t border-border bg-surface pb-16 desktop:pb-0">
      <div className="mx-auto grid max-w-[1400px] gap-8 px-4 py-10 tablet:grid-cols-3">
        <div className="space-y-2">
          <p className="text-lg font-semibold text-text-primary">{store?.name ?? "Store"}</p>
          <p className="text-sm text-text-secondary">Cash on delivery, anywhere in Bangladesh.</p>
        </div>

        <div className="space-y-2">
          <p className="text-sm font-semibold text-text-primary">Shop</p>
          <ul className="space-y-1.5 text-sm text-text-secondary">
            <li>
              <Link href="/products" className="hover:text-primary">
                All Products
              </Link>
            </li>
            <li>
              <Link href="/brands" className="hover:text-primary">
                Brands
              </Link>
            </li>
            <li>
              <Link href="/blog" className="hover:text-primary">
                Blog
              </Link>
            </li>
          </ul>
        </div>

        {categories && categories.length > 0 ? (
          <div className="space-y-2">
            <p className="text-sm font-semibold text-text-primary">Categories</p>
            <ul className="space-y-1.5 text-sm text-text-secondary">
              {categories.slice(0, 5).map((category) => (
                <li key={category.id}>
                  <Link href={`/category/${category.slug}`} className="hover:text-primary">
                    {category.name}
                  </Link>
                </li>
              ))}
            </ul>
          </div>
        ) : null}

        {pages && pages.length > 0 ? (
          <div className="space-y-2">
            <p className="text-sm font-semibold text-text-primary">Information</p>
            <ul className="space-y-1.5 text-sm text-text-secondary">
              {pages.map((page) => (
                <li key={page.slug}>
                  <Link href={`/pages/${page.slug}`} className="hover:text-primary">
                    {page.title}
                  </Link>
                </li>
              ))}
            </ul>
          </div>
        ) : null}
      </div>

      <div className="border-t border-border px-4 py-4 text-center text-xs text-text-muted">
        &copy; {new Date().getFullYear()} {store?.name ?? "Store"}. All rights reserved.
      </div>
    </footer>
  );
}
