import { ProductCard } from "@/components/storefront/product-card";
import type { ProductCarouselSettings } from "@/types/homepage-block";
import type { StorefrontProduct } from "@/types/storefront";

export function ProductCarouselBlock({ settings, products }: { settings: ProductCarouselSettings; products: StorefrontProduct[] }) {
  if (products.length === 0) return null;

  return (
    <section className="space-y-4">
      <h2 className="text-section font-semibold text-text-primary">{settings.heading}</h2>
      <div className="flex gap-4 overflow-x-auto pb-2">
        {products.map((product) => (
          <div key={product.id} className="w-44 shrink-0">
            <ProductCard product={product} />
          </div>
        ))}
      </div>
    </section>
  );
}
