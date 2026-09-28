"use client";

import { AutoManualPicker } from "@/components/builder/auto-manual-picker";
import type { ContentPanelProps } from "@/components/builder/panel-types";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { useAllProducts } from "@/hooks/use-products";
import type { AutoManualProductSettings } from "@/types/homepage-block";

export function FeaturedProductsPanel({ value, onChange, storeId }: ContentPanelProps<AutoManualProductSettings>) {
  const { data: productsData } = useAllProducts(storeId);
  const options = (productsData?.data ?? []).map((product) => ({ id: product.id, label: `${product.name} (${product.sku})` }));

  return (
    <div className="space-y-4">
      <div className="space-y-1.5">
        <Label htmlFor="featured-products-heading">Heading</Label>
        <Input id="featured-products-heading" value={value.heading} onChange={(event) => onChange({ ...value, heading: event.target.value })} />
      </div>
      <div className="space-y-1.5">
        <Label htmlFor="featured-products-limit">Number to show</Label>
        <Input
          id="featured-products-limit"
          type="number"
          min={1}
          max={24}
          value={value.limit}
          onChange={(event) => onChange({ ...value, limit: Number(event.target.value) })}
        />
      </div>
      <AutoManualPicker
        mode={value.mode}
        onModeChange={(mode) => onChange({ ...value, mode })}
        selectedIds={value.product_ids}
        onSelectedIdsChange={(product_ids) => onChange({ ...value, product_ids })}
        options={options}
        autoDescription="Shows products marked Featured, newest first."
      />
    </div>
  );
}
