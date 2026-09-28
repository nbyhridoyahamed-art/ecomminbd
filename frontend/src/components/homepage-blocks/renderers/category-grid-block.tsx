import Link from "next/link";
import { Package } from "lucide-react";

import type { AutoManualCategorySettings } from "@/types/homepage-block";
import type { StorefrontCategory } from "@/types/storefront";

export function CategoryGridBlock({ settings, categories }: { settings: AutoManualCategorySettings; categories: StorefrontCategory[] }) {
  if (categories.length === 0) return null;

  return (
    <section className="space-y-4">
      <h2 className="text-section font-semibold text-text-primary">{settings.heading}</h2>
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
  );
}
