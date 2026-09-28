"use client";

import { useMemo, useState } from "react";
import Link from "next/link";
import { notFound } from "next/navigation";
import { Minus, Package, Plus } from "lucide-react";

import { formatMoney } from "@/lib/money";
import { ApiError } from "@/types/api";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Skeleton } from "@/components/ui/skeleton";
import { useStorefrontProduct } from "@/hooks/use-storefront-catalog";
import { useCartStore } from "@/stores/cart-store";
import type { StorefrontVariant } from "@/types/storefront";

function attributeOptions(variants: StorefrontVariant[]): Map<string, string[]> {
  const options = new Map<string, string[]>();

  for (const variant of variants) {
    for (const { attribute_name, value } of variant.attribute_values) {
      const values = options.get(attribute_name) ?? [];
      if (!values.includes(value)) values.push(value);
      options.set(attribute_name, values);
    }
  }

  return options;
}

function matchVariant(variants: StorefrontVariant[], selected: Record<string, string>, attributeNames: string[]) {
  if (attributeNames.some((name) => !selected[name])) return null;

  return (
    variants.find((variant) =>
      attributeNames.every(
        (name) => variant.attribute_values.find((av) => av.attribute_name === name)?.value === selected[name],
      ),
    ) ?? null
  );
}

/**
 * The interactive PDP — a Client Component so it can fetch live data
 * (TanStack Query) and handle variant selection/cart state. Its parent
 * page.tsx is a Server Component that fetches the same product once more,
 * server-side, purely to build real <head> metadata and JSON-LD — see
 * ARCHITECTURE.md's "fetch twice, once per side" note on why this app's
 * storefront can't yet share one fetch across both.
 */
export function ProductDetailClient({ slug }: { slug: string }) {
  const { data: product, isLoading, error } = useStorefrontProduct(slug);
  const { addItem } = useCartStore();

  const [activeImage, setActiveImage] = useState(0);
  const [selectedAttributes, setSelectedAttributes] = useState<Record<string, string>>({});
  const [quantity, setQuantity] = useState(1);

  const attributeNames = useMemo(
    () => (product ? Array.from(attributeOptions(product.variants).keys()) : []),
    [product],
  );
  const attributeChoices = useMemo(
    () => (product ? attributeOptions(product.variants) : new Map<string, string[]>()),
    [product],
  );
  const matchedVariant = useMemo(
    () => (product ? matchVariant(product.variants, selectedAttributes, attributeNames) : null),
    [product, selectedAttributes, attributeNames],
  );

  if (error instanceof ApiError && error.status === 404) {
    notFound();
  }

  if (isLoading) {
    return (
      <div className="mx-auto grid max-w-[1400px] gap-8 px-4 py-8 tablet:grid-cols-2">
        <Skeleton className="aspect-square w-full" />
        <div className="space-y-4">
          <Skeleton className="h-8 w-3/4" />
          <Skeleton className="h-6 w-1/3" />
          <Skeleton className="h-24 w-full" />
        </div>
      </div>
    );
  }

  if (error || !product) {
    return (
      <div className="mx-auto max-w-[1400px] px-4 py-16 text-center">
        <p className="text-text-secondary">Something went wrong loading this product. Please try again.</p>
      </div>
    );
  }

  const isVariable = product.type === "variable";
  const isBundle = product.type === "bundle";
  const needsVariantSelection = isVariable && product.variants.length > 0;

  const effectivePrice = matchedVariant?.price ?? product.price;
  const effectiveSalePrice = matchedVariant ? matchedVariant.sale_price : product.sale_price;
  const onSale = effectiveSalePrice !== null && effectiveSalePrice < effectivePrice;
  const displayPrice = onSale ? effectiveSalePrice! : effectivePrice;

  const inStock = needsVariantSelection ? (matchedVariant ? matchedVariant.in_stock : true) : product.in_stock;
  const canAddToCart = inStock && (!needsVariantSelection || matchedVariant !== null);

  const images = product.images.length > 0 ? product.images : [];

  function handleAddToCart() {
    if (!canAddToCart) return;

    addItem(
      {
        productId: product!.id,
        variantId: matchedVariant?.id ?? null,
        name: product!.name,
        slug: product!.slug,
        variantLabel: matchedVariant
          ? matchedVariant.attribute_values.map((av) => av.value).join(" / ")
          : null,
        imageUrl: images[0]?.url ?? null,
        unitPrice: displayPrice,
        currencyCode: product!.currency_code,
      },
      quantity,
    );
  }

  return (
    <div className="mx-auto max-w-[1400px] px-4 py-8">
      <div className="grid gap-8 tablet:grid-cols-2">
        <div className="space-y-3">
          <div className="flex aspect-square items-center justify-center overflow-hidden rounded-lg border border-border bg-border/20">
            {images[activeImage] ? (
              // eslint-disable-next-line @next/next/no-img-element -- remote storage URL, not a static asset
              <img src={images[activeImage].url} alt={product.name} className="size-full object-cover" />
            ) : (
              <Package className="size-16 text-text-muted" />
            )}
          </div>
          {images.length > 1 ? (
            <div className="flex gap-2">
              {images.map((image, index) => (
                <button
                  key={image.id}
                  type="button"
                  onClick={() => setActiveImage(index)}
                  className={`size-16 shrink-0 overflow-hidden rounded-md border ${
                    index === activeImage ? "border-primary" : "border-border"
                  }`}
                >
                  {/* eslint-disable-next-line @next/next/no-img-element -- remote storage URL, not a static asset */}
                  <img src={image.url} alt="" className="size-full object-cover" />
                </button>
              ))}
            </div>
          ) : null}
        </div>

        <div className="space-y-4">
          <div className="space-y-1">
            {product.brand ? (
              <Link href={`/brand/${product.brand.slug}`} className="text-sm text-text-secondary hover:text-primary">
                {product.brand.name}
              </Link>
            ) : null}
            <h1 className="text-page-title font-semibold text-text-primary">{product.name}</h1>
            {product.category ? (
              <Link
                href={`/category/${product.category.slug}`}
                className="text-sm text-text-secondary hover:text-primary"
              >
                {product.category.name}
              </Link>
            ) : null}
          </div>

          <div className="flex items-baseline gap-3">
            <span className="text-section font-semibold text-text-primary">
              {formatMoney(displayPrice, product.currency_code)}
            </span>
            {onSale ? (
              <span className="text-text-muted line-through">
                {formatMoney(effectivePrice, product.currency_code)}
              </span>
            ) : null}
            {!inStock ? <Badge variant="neutral">Out of stock</Badge> : null}
          </div>

          {product.short_description ? <p className="text-text-secondary">{product.short_description}</p> : null}

          {isBundle && product.components && product.components.length > 0 ? (
            <div className="space-y-2 rounded-md border border-border p-3">
              <p className="text-sm font-medium text-text-primary">This bundle includes:</p>
              <ul className="space-y-1 text-sm text-text-secondary">
                {product.components.map((component, index) => (
                  <li key={index}>
                    {component.quantity}x {component.product_name}
                  </li>
                ))}
              </ul>
            </div>
          ) : null}

          {needsVariantSelection ? (
            <div className="space-y-3">
              {attributeNames.map((name) => (
                <div key={name} className="space-y-1.5">
                  <p className="text-sm font-medium text-text-primary">{name}</p>
                  <div className="flex flex-wrap gap-2">
                    {(attributeChoices.get(name) ?? []).map((value) => (
                      <button
                        key={value}
                        type="button"
                        onClick={() => setSelectedAttributes((prev) => ({ ...prev, [name]: value }))}
                        className={`rounded-md border px-3 py-1.5 text-sm ${
                          selectedAttributes[name] === value
                            ? "border-primary bg-primary/10 text-primary"
                            : "border-border text-text-primary hover:bg-border/30"
                        }`}
                      >
                        {value}
                      </button>
                    ))}
                  </div>
                </div>
              ))}
            </div>
          ) : null}

          <div className="flex items-center gap-3">
            <div className="flex items-center gap-1">
              <Button
                type="button"
                variant="outline"
                size="icon"
                aria-label="Decrease quantity"
                onClick={() => setQuantity((q) => Math.max(1, q - 1))}
              >
                <Minus className="size-4" />
              </Button>
              <span className="w-10 text-center">{quantity}</span>
              <Button
                type="button"
                variant="outline"
                size="icon"
                aria-label="Increase quantity"
                onClick={() => setQuantity((q) => Math.min(100, q + 1))}
              >
                <Plus className="size-4" />
              </Button>
            </div>
            <Button type="button" size="lg" className="flex-1" disabled={!canAddToCart} onClick={handleAddToCart}>
              {!inStock ? "Out of stock" : needsVariantSelection && !matchedVariant ? "Select options" : "Add to Cart"}
            </Button>
          </div>

          {product.description ? (
            <div className="space-y-1 border-t border-border pt-4">
              <p className="text-sm font-medium text-text-primary">Description</p>
              <p className="whitespace-pre-line text-sm text-text-secondary">{product.description}</p>
            </div>
          ) : null}
        </div>
      </div>
    </div>
  );
}
