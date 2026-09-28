"use client";

import Link from "next/link";
import { Package } from "lucide-react";

import { useCountdown } from "@/hooks/use-countdown";
import { formatMoney } from "@/lib/money";
import type { FlashSaleSettings } from "@/types/homepage-block";
import type { StorefrontFlashSaleItem } from "@/types/storefront";

const UNITS = [
  { key: "days", label: "d" },
  { key: "hours", label: "h" },
  { key: "minutes", label: "m" },
  { key: "seconds", label: "s" },
] as const;

export function FlashSaleBlock({ settings, items }: { settings: FlashSaleSettings; items: StorefrontFlashSaleItem[] }) {
  const countdown = useCountdown(settings.ends_at);

  if (items.length === 0) return null;

  return (
    <section className="space-y-4">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <h2 className="text-section font-semibold text-text-primary">{settings.heading}</h2>
        <div className="flex items-center gap-2">
          {UNITS.map((unit) => (
            <span
              key={unit.key}
              className="flex items-baseline gap-1 rounded-md bg-surface-elevated px-2.5 py-1.5 text-sm font-semibold text-text-primary"
            >
              {String(countdown[unit.key]).padStart(2, "0")}
              <span className="text-xs font-normal text-text-muted">{unit.label}</span>
            </span>
          ))}
        </div>
      </div>
      <div className="grid grid-cols-2 gap-4 tablet:grid-cols-3 desktop:grid-cols-5">
        {items.map((item) => (
          <Link
            key={item.product.id}
            href={`/products/${item.product.slug}`}
            className="group flex flex-col overflow-hidden rounded-lg border border-border bg-surface transition-shadow hover:shadow-md"
          >
            <div className="relative aspect-square w-full overflow-hidden bg-border/20">
              {item.product.primary_image_url ? (
                // eslint-disable-next-line @next/next/no-img-element -- remote storage URL, not a static asset
                <img
                  src={item.product.primary_image_url}
                  alt={item.product.name}
                  className="size-full object-cover transition-transform group-hover:scale-105"
                />
              ) : (
                <div className="flex size-full items-center justify-center">
                  <Package className="size-10 text-text-muted" />
                </div>
              )}
            </div>
            <div className="flex flex-1 flex-col gap-1 p-3">
              <p className="line-clamp-2 text-sm font-medium text-text-primary">{item.product.name}</p>
              <div className="mt-auto flex items-baseline gap-2">
                <span className="font-semibold text-text-primary">{formatMoney(item.sale_price, item.product.currency_code)}</span>
                <span className="text-xs text-text-muted line-through">{formatMoney(item.product.price, item.product.currency_code)}</span>
              </div>
            </div>
          </Link>
        ))}
      </div>
    </section>
  );
}
