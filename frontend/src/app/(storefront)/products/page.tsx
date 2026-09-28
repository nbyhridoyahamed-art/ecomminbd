"use client";

import { Suspense, useMemo } from "react";
import { useRouter, useSearchParams } from "next/navigation";
import { PackageSearch } from "lucide-react";

import { EmptyState } from "@/components/ui/empty-state";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Skeleton } from "@/components/ui/skeleton";
import { ProductCard } from "@/components/storefront/product-card";
import { StorefrontPagination } from "@/components/storefront/storefront-pagination";
import { useStorefrontBrands, useStorefrontCategories, useStorefrontProducts } from "@/hooks/use-storefront-catalog";

function ProductGridSkeleton() {
  return (
    <div className="grid grid-cols-2 gap-4 tablet:grid-cols-3 desktop:grid-cols-4">
      {Array.from({ length: 8 }).map((_, index) => (
        <Skeleton key={index} className="aspect-square w-full" />
      ))}
    </div>
  );
}

function ProductsPageContent() {
  const router = useRouter();
  const searchParams = useSearchParams();

  const search = searchParams.get("search") ?? "";
  const category = searchParams.get("category") ?? "";
  const brand = searchParams.get("brand") ?? "";
  const sort = searchParams.get("sort") ?? "";
  const featured = searchParams.get("featured") === "1";
  const page = Number(searchParams.get("page") ?? "1");

  const { data: categories } = useStorefrontCategories();
  const { data: brands } = useStorefrontBrands();
  const { data, isLoading } = useStorefrontProducts({
    page,
    search: search || undefined,
    category: category || undefined,
    brand: brand || undefined,
    featured: featured || undefined,
    sort: (sort || undefined) as "price_asc" | "price_desc" | "newest" | undefined,
  });

  const flatCategories = useMemo(() => categories?.flatMap((cat) => [cat, ...cat.children]) ?? [], [categories]);
  const products = data?.data ?? [];

  function updateParam(key: string, value: string | null) {
    const params = new URLSearchParams(searchParams.toString());
    if (value) {
      params.set(key, value);
    } else {
      params.delete(key);
    }
    if (key !== "page") {
      params.delete("page");
    }
    router.push(`/products?${params.toString()}`);
  }

  return (
    <div className="mx-auto max-w-[1400px] space-y-6 px-4 py-8">
      <h1 className="text-page-title font-semibold text-text-primary">
        {search ? `Search results for "${search}"` : "All Products"}
      </h1>

      <div className="flex flex-wrap gap-3">
        <Select
          value={category || "all"}
          onValueChange={(value) => updateParam("category", value === "all" ? null : value)}
        >
          <SelectTrigger className="w-48">
            <SelectValue placeholder="All categories" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="all">All categories</SelectItem>
            {flatCategories.map((cat) => (
              <SelectItem key={cat.id} value={cat.slug}>
                {cat.name}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>

        <Select value={brand || "all"} onValueChange={(value) => updateParam("brand", value === "all" ? null : value)}>
          <SelectTrigger className="w-48">
            <SelectValue placeholder="All brands" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="all">All brands</SelectItem>
            {(brands ?? []).map((b) => (
              <SelectItem key={b.id} value={b.slug}>
                {b.name}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>

        <Select value={sort || "default"} onValueChange={(value) => updateParam("sort", value === "default" ? null : value)}>
          <SelectTrigger className="w-48">
            <SelectValue placeholder="Sort by" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="default">Name (A-Z)</SelectItem>
            <SelectItem value="newest">Newest</SelectItem>
            <SelectItem value="price_asc">Price: Low to High</SelectItem>
            <SelectItem value="price_desc">Price: High to Low</SelectItem>
          </SelectContent>
        </Select>
      </div>

      {isLoading ? (
        <ProductGridSkeleton />
      ) : products.length === 0 ? (
        <EmptyState
          icon={<PackageSearch />}
          title="No products found"
          description="Try a different search or filter."
        />
      ) : (
        <>
          <div className="grid grid-cols-2 gap-4 tablet:grid-cols-3 desktop:grid-cols-4">
            {products.map((product) => (
              <ProductCard key={product.id} product={product} />
            ))}
          </div>
          <StorefrontPagination meta={data?.meta} onPageChange={(newPage) => updateParam("page", String(newPage))} />
        </>
      )}
    </div>
  );
}

export default function ProductsPage() {
  return (
    <Suspense
      fallback={
        <div className="mx-auto max-w-[1400px] px-4 py-8">
          <ProductGridSkeleton />
        </div>
      }
    >
      <ProductsPageContent />
    </Suspense>
  );
}
