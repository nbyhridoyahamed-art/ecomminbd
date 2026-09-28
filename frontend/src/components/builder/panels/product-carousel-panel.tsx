"use client";

import { useState } from "react";

import type { ContentPanelProps } from "@/components/builder/panel-types";
import { Checkbox } from "@/components/ui/checkbox";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { useCategories } from "@/hooks/use-categories";
import { useAllProducts } from "@/hooks/use-products";
import type { ProductCarouselSettings } from "@/types/homepage-block";

const MODE_LABELS: Record<ProductCarouselSettings["mode"], string> = {
  auto: "Automatic",
  manual: "Hand-picked",
  category: "From a category",
};

/**
 * product_carousel is the one auto/manual-shaped block with a third mode
 * ("category"), so it can't reuse AutoManualPicker (binary by design — two
 * other panels already depend on that external API). The manual checkbox
 * list below intentionally mirrors AutoManualPicker's own, inlined for this
 * panel only.
 */
export function ProductCarouselPanel({ value, onChange, storeId }: ContentPanelProps<ProductCarouselSettings>) {
  const { data: productsData } = useAllProducts(storeId);
  const { data: categories } = useCategories(storeId);
  const [search, setSearch] = useState("");

  const products = productsData?.data ?? [];
  const filteredProducts = products.filter((product) => product.name.toLowerCase().includes(search.toLowerCase()));

  function toggleProduct(id: number) {
    onChange({
      ...value,
      product_ids: value.product_ids.includes(id) ? value.product_ids.filter((existing) => existing !== id) : [...value.product_ids, id],
    });
  }

  return (
    <div className="space-y-4">
      <div className="space-y-1.5">
        <Label htmlFor="product-carousel-heading">Heading</Label>
        <Input id="product-carousel-heading" value={value.heading} onChange={(event) => onChange({ ...value, heading: event.target.value })} />
      </div>
      <div className="space-y-1.5">
        <Label htmlFor="product-carousel-limit">Number to show</Label>
        <Input
          id="product-carousel-limit"
          type="number"
          min={1}
          max={24}
          value={value.limit}
          onChange={(event) => onChange({ ...value, limit: Number(event.target.value) })}
        />
      </div>

      <div className="space-y-1.5">
        <Label>Source</Label>
        <Select value={value.mode} onValueChange={(mode) => onChange({ ...value, mode: mode as ProductCarouselSettings["mode"] })}>
          <SelectTrigger className="max-w-xs">
            <SelectValue>{MODE_LABELS[value.mode]}</SelectValue>
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="auto">Automatic</SelectItem>
            <SelectItem value="manual">Hand-picked</SelectItem>
            <SelectItem value="category">From a category</SelectItem>
          </SelectContent>
        </Select>
        {value.mode === "auto" ? <p className="text-xs text-text-muted">Shows newest products automatically.</p> : null}
      </div>

      {value.mode === "manual" ? (
        <div className="space-y-2">
          <Input placeholder="Search..." value={search} onChange={(event) => setSearch(event.target.value)} />
          <div className="max-h-56 space-y-1 overflow-y-auto rounded-md border border-border p-2">
            {filteredProducts.length === 0 ? (
              <p className="p-2 text-sm text-text-muted">No matches.</p>
            ) : (
              filteredProducts.map((product) => (
                <label key={product.id} className="flex items-center gap-2 rounded px-2 py-1.5 text-sm hover:bg-border/30">
                  <Checkbox checked={value.product_ids.includes(product.id)} onCheckedChange={() => toggleProduct(product.id)} />
                  {product.name} ({product.sku})
                </label>
              ))
            )}
          </div>
          <p className="text-xs text-text-muted">{value.product_ids.length} selected — shown in the order picked.</p>
        </div>
      ) : null}

      {value.mode === "category" ? (
        <div className="space-y-1.5">
          <Label>Category</Label>
          <Select
            value={value.category_id !== null ? String(value.category_id) : ""}
            onValueChange={(id) => onChange({ ...value, category_id: Number(id) })}
          >
            <SelectTrigger className="max-w-xs">
              <SelectValue placeholder="Select a category" />
            </SelectTrigger>
            <SelectContent>
              {(categories ?? []).map((category) => (
                <SelectItem key={category.id} value={String(category.id)}>
                  {category.name}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>
      ) : null}
    </div>
  );
}
