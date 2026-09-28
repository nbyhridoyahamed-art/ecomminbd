import Link from "next/link";
import { Package } from "lucide-react";

import { formatMoney } from "@/lib/money";
import { Badge } from "@/components/ui/badge";
import type { StorefrontProduct } from "@/types/storefront";

export function ProductCard({ product }: { product: StorefrontProduct }) {
  const onSale = product.sale_price !== null && product.sale_price < product.price;

  return (
    <Link
      href={`/products/${product.slug}`}
      className="group flex flex-col overflow-hidden rounded-lg border border-border bg-surface transition-shadow hover:shadow-md"
    >
      <div className="relative aspect-square w-full overflow-hidden bg-border/20">
        {product.primary_image_url ? (
          // eslint-disable-next-line @next/next/no-img-element -- remote storage URL, not a static asset
          <img
            src={product.primary_image_url}
            alt={product.name}
            className="size-full object-cover transition-transform group-hover:scale-105"
          />
        ) : (
          <div className="flex size-full items-center justify-center">
            <Package className="size-10 text-text-muted" />
          </div>
        )}
        {!product.in_stock ? (
          <Badge variant="neutral" className="absolute left-2 top-2 bg-surface/90">
            Out of stock
          </Badge>
        ) : product.featured ? (
          <Badge variant="primary" className="absolute left-2 top-2">
            Featured
          </Badge>
        ) : null}
      </div>

      <div className="flex flex-1 flex-col gap-1 p-3">
        <p className="line-clamp-2 text-sm font-medium text-text-primary">{product.name}</p>
        <div className="mt-auto flex items-baseline gap-2">
          <span className="font-semibold text-text-primary">
            {formatMoney(onSale ? product.sale_price! : product.price, product.currency_code)}
          </span>
          {onSale ? (
            <span className="text-xs text-text-muted line-through">
              {formatMoney(product.price, product.currency_code)}
            </span>
          ) : null}
        </div>
      </div>
    </Link>
  );
}
