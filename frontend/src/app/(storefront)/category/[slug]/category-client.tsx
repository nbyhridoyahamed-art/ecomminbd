"use client";

import { useEffect, useState } from "react";
import Link from "next/link";
import { notFound } from "next/navigation";
import { PackageSearch } from "lucide-react";

import { trackEvent } from "@/lib/analytics";
import { ApiError } from "@/types/api";
import { EmptyState } from "@/components/ui/empty-state";
import { Skeleton } from "@/components/ui/skeleton";
import { ProductCard } from "@/components/storefront/product-card";
import { StorefrontPagination } from "@/components/storefront/storefront-pagination";
import { useStorefrontCategory } from "@/hooks/use-storefront-catalog";

/**
 * The interactive category listing — a Client Component so it can fetch
 * live data (TanStack Query) and handle pagination. Its parent page.tsx is
 * a Server Component that fetches the same category once more, server-side,
 * purely to build real <head> metadata and JSON-LD — see ARCHITECTURE.md's
 * "fetch twice, once per side" note on why this app's storefront can't yet
 * share one fetch across both.
 */
export function CategoryClient({ slug }: { slug: string }) {
  const [page, setPage] = useState(1);
  const { data, isLoading, error } = useStorefrontCategory(slug, page);

  useEffect(() => {
    const categoryId = data?.data.category.id;
    if (!categoryId) return;
    trackEvent("category_view", { category_id: categoryId });
  }, [data?.data.category.id]);

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
        <p className="text-text-secondary">Something went wrong loading this category. Please try again.</p>
      </div>
    );
  }

  const { category, products } = data.data;

  return (
    <div className="mx-auto max-w-[1400px] space-y-6 px-4 py-8">
      <div className="space-y-2">
        <h1 className="text-page-title font-semibold text-text-primary">{category.name}</h1>
        {category.description ? <p className="text-text-secondary">{category.description}</p> : null}
        {category.children.length > 0 ? (
          <div className="flex flex-wrap gap-2 pt-2">
            {category.children.map((child) => (
              <Link
                key={child.id}
                href={`/category/${child.slug}`}
                className="rounded-full border border-border px-3 py-1 text-sm text-text-secondary hover:border-primary hover:text-primary"
              >
                {child.name}
              </Link>
            ))}
          </div>
        ) : null}
      </div>

      {products.length === 0 ? (
        <EmptyState icon={<PackageSearch />} title="No products in this category yet" />
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
