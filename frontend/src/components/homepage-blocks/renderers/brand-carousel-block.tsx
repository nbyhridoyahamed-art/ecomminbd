import Link from "next/link";
import { Store } from "lucide-react";

import type { AutoManualBrandSettings } from "@/types/homepage-block";
import type { StorefrontBrand } from "@/types/storefront";

export function BrandCarouselBlock({ settings, brands }: { settings: AutoManualBrandSettings; brands: StorefrontBrand[] }) {
  if (brands.length === 0) return null;

  return (
    <section className="space-y-4">
      <h2 className="text-section font-semibold text-text-primary">{settings.heading}</h2>
      <div className="flex gap-4 overflow-x-auto pb-2">
        {brands.map((brand) => (
          <Link
            key={brand.id}
            href={`/brand/${brand.slug}`}
            className="flex w-32 shrink-0 flex-col items-center gap-2 rounded-lg border border-border bg-surface p-4 text-center transition-shadow hover:shadow-md"
          >
            <div className="flex size-14 items-center justify-center overflow-hidden rounded-full bg-border/20">
              {brand.logo_url ? (
                // eslint-disable-next-line @next/next/no-img-element -- remote storage URL, not a static asset
                <img src={brand.logo_url} alt={brand.name} className="size-full object-cover" />
              ) : (
                <Store className="size-6 text-text-muted" />
              )}
            </div>
            <span className="line-clamp-2 text-sm font-medium text-text-primary">{brand.name}</span>
          </Link>
        ))}
      </div>
    </section>
  );
}
