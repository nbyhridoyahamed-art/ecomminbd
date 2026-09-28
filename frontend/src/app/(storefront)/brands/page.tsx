"use client";

import Link from "next/link";
import { Package } from "lucide-react";

import { EmptyState } from "@/components/ui/empty-state";
import { Skeleton } from "@/components/ui/skeleton";
import { useStorefrontBrands } from "@/hooks/use-storefront-catalog";

export default function BrandsPage() {
  const { data: brands, isLoading } = useStorefrontBrands();

  return (
    <div className="mx-auto max-w-[1400px] space-y-6 px-4 py-8">
      <h1 className="text-page-title font-semibold text-text-primary">Brands</h1>

      {isLoading ? (
        <div className="grid grid-cols-2 gap-4 tablet:grid-cols-4 desktop:grid-cols-6">
          {Array.from({ length: 6 }).map((_, index) => (
            <Skeleton key={index} className="h-24 w-full" />
          ))}
        </div>
      ) : !brands || brands.length === 0 ? (
        <EmptyState icon={<Package />} title="No brands yet" />
      ) : (
        <div className="grid grid-cols-2 gap-4 tablet:grid-cols-4 desktop:grid-cols-6">
          {brands.map((brand) => (
            <Link
              key={brand.id}
              href={`/brand/${brand.slug}`}
              className="flex flex-col items-center justify-center gap-2 rounded-lg border border-border bg-surface p-4 text-center transition-shadow hover:shadow-md"
            >
              <div className="flex size-12 items-center justify-center overflow-hidden rounded-full bg-border/20">
                {brand.logo_url ? (
                  // eslint-disable-next-line @next/next/no-img-element -- remote storage URL, not a static asset
                  <img src={brand.logo_url} alt={brand.name} className="size-full object-cover" />
                ) : (
                  <Package className="size-5 text-text-muted" />
                )}
              </div>
              <span className="text-sm font-medium text-text-primary">{brand.name}</span>
            </Link>
          ))}
        </div>
      )}
    </div>
  );
}
