import type { ProductVariantSnapshot } from "@/types/product";

/** "Red / M (SKU-RED-M)", or null for a line item that has no variant. */
export function variantLabel(variant: ProductVariantSnapshot | null): string | null {
  if (!variant) return null;

  const attributes = variant.attribute_values.map((av) => av.value).join(" / ");
  return attributes ? `${attributes} (${variant.sku})` : variant.sku;
}
