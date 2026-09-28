import { ProductCard } from "@/components/storefront/product-card";
import type { LimitOnlySettings } from "@/types/homepage-block";
import type { StorefrontProduct } from "@/types/storefront";

export function LatestProductsBlock({ settings, products }: { settings: LimitOnlySettings; products: StorefrontProduct[] }) {
  if (products.length === 0) return null;

  return (
    <section className="space-y-4">
      <h2 className="text-section font-semibold text-text-primary">{settings.heading}</h2>
      <div className="grid grid-cols-2 gap-4 tablet:grid-cols-3 desktop:grid-cols-5">
        {products.map((product) => (
          <ProductCard key={product.id} product={product} />
        ))}
      </div>
    </section>
  );
}
