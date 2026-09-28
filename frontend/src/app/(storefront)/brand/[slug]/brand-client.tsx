"use client";

import { useState } from "react";
import { notFound } from "next/navigation";
import { Package, PackageSearch } from "lucide-react";

import { ApiError } from "@/types/api";
import { EmptyState } from "@/components/ui/empty-state";
import { Skeleton } from "@/components/ui/skeleton";
import { ProductCard } from "@/components/storefront/product-card";
import { StorefrontPagination } from "@/components/storefront/storefront-pagination";
import { useStorefrontBrand } from "@/hooks/use-storefront-catalog";

/**
 * The interactive brand listing — a Client Component so it can fetch live
 * data (TanStack Query) and handle pagination. Its parent page.tsx is a
 * Server Component that fetches the same brand once more, server-side,
 * purely to build real <head> metadata and JSON-LD — see ARCHITECTURE.md's
 * "fetch twice, once per side" note on why this app's storefront can't yet
 * share one fetch across both.
 */
export function BrandClient({ slug }: { slug: string }) {
  const [page, setPage] = useState(1);
  const { data, isLoading, error } = useStorefrontBrand(slug, page);

  if (error instanceof ApiError && error.status === 404) {
    notFound();
  }

  if (isLoading) {
    return (
      <div className="mx-auto max-w-[1400px] space-y-6 px-4 py-8">
        <Skeleton className="h-8 w-1/3" />
        <div className="grid grid-cols-2 gap-4 tablet:grid-cols-3 desktop:grid-cols-4">
          {Array.from({ length: 8 }).map((_, index) => (
            <Skeleton key={index} className="aspect-square w-full" />
          ))}
        </div>
      </div>
    );
  }

  if (error || !data) {
    return (
      <div className="mx-auto max-w-[1400px] px-4 py-16 text-center">
        <p className="text-text-secondary">Something went wrong loading this brand. Please try again.</p>
      </div>
    );
  }

  const { brand, products } = data.data;

  return (
    <div className="mx-auto max-w-[1400px] space-y-6 px-4 py-8">
      <div className="flex items-center gap-4">
        <div className="flex size-16 shrink-0 items-center justify-center overflow-hidden rounded-full border border-border bg-border/20">
          {brand.logo_url ? (
            // eslint-disable-next-line @next/next/no-img-element -- remote storage URL, not a static asset
            <img src={brand.logo_url} alt={brand.name} className="size-full object-cover" />
          ) : (
            <Package className="size-6 text-text-muted" />
          )}
        </div>
        <div>
          <h1 className="text-page-title font-semibold text-text-primary">{brand.name}</h1>
          {brand.description ? <p className="text-text-secondary">{brand.description}</p> : null}
        </div>
      </div>

      {products.length === 0 ? (
        <EmptyState icon={<PackageSearch />} title="No products from this brand yet" />
      ) : (
        <>
          <div className="grid grid-cols-2 gap-4 tablet:grid-cols-3 desktop:grid-cols-4">
            {products.map((product) => (
              <ProductCard key={product.id} product={product} />
            ))}
          </div>
          <StorefrontPagination meta={data.meta} onPageChange={setPage} />
        </>
      )}
    </div>
  );
}
