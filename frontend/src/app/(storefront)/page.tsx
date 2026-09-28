"use client";

import Link from "next/link";
import { Package } from "lucide-react";

import { Button } from "@/components/ui/button";
import { Skeleton } from "@/components/ui/skeleton";
import { ProductCard } from "@/components/storefront/product-card";
import { useStorefrontCategories, useStorefrontProducts, useStorefrontStore } from "@/hooks/use-storefront-catalog";

export default function StorefrontHomePage() {
  const { data: store } = useStorefrontStore();
  const { data: categories } = useStorefrontCategories();
  const { data: featured, isLoading: featuredLoading } = useStorefrontProducts({ featured: true, page: 1 });

  const featuredProducts = featured?.data ?? [];

  return (
    <div className="mx-auto max-w-[1400px] space-y-12 px-4 py-8">
      <section className="rounded-lg border border-border bg-surface px-6 py-12 text-center tablet:py-16">
        <p className="text-page-title font-semibold text-text-primary tablet:text-display">
          {store?.name ?? "Welcome"}
        </p>
        <p className="mx-auto mt-3 max-w-xl text-text-secondary">
          Quality products, cash on delivery, anywhere in Bangladesh.
        </p>
        <Button asChild size="lg" className="mt-6">
          <Link href="/products">Shop All Products</Link>
        </Button>
      </section>

      {categories && categories.length > 0 ? (
        <section className="space-y-4">
          <h2 className="text-section font-semibold text-text-primary">Shop by Category</h2>
          <div className="grid grid-cols-2 gap-4 tablet:grid-cols-3 desktop:grid-cols-6">
            {categories.map((category) => (
              <Link
                key={category.id}
                href={`/category/${category.slug}`}
                className="flex flex-col items-center gap-2 rounded-lg border border-border bg-surface p-4 text-center transition-shadow hover:shadow-md"
              >
                <div className="flex size-14 items-center justify-center overflow-hidden rounded-full bg-border/20">
                  {category.image_url ? (
                    // eslint-disable-next-line @next/next/no-img-element -- remote storage URL, not a static asset
                    <img src={category.image_url} alt={category.name} className="size-full object-cover" />
                  ) : (
                    <Package className="size-6 text-text-muted" />
                  )}
                </div>
                <span className="text-sm font-medium text-text-primary">{category.name}</span>
              </Link>
            ))}
          </div>
        </section>
      ) : null}

      {featuredLoading ? (
        <section className="space-y-4">
          <h2 className="text-section font-semibold text-text-primary">Featured Products</h2>
          <div className="grid grid-cols-2 gap-4 tablet:grid-cols-3 desktop:grid-cols-5">
            {Array.from({ length: 5 }).map((_, index) => (
              <Skeleton key={index} className="aspect-square w-full" />
            ))}
          </div>
        </section>
      ) : featuredProducts.length > 0 ? (
        <section className="space-y-4">
          <div className="flex items-center justify-between">
            <h2 className="text-section font-semibold text-text-primary">Featured Products</h2>
            <Link href="/products?featured=1" className="text-sm font-medium text-primary hover:underline">
              View all
            </Link>
          </div>
          <div className="grid grid-cols-2 gap-4 tablet:grid-cols-3 desktop:grid-cols-5">
            {featuredProducts.map((product) => (
              <ProductCard key={product.id} product={product} />
            ))}
          </div>
        </section>
      ) : null}
    </div>
  );
}
