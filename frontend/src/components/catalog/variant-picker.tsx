"use client";

import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import type { Product } from "@/types/product";

interface VariantPickerProps {
  product: Product | undefined;
  value: string;
  onChange: (value: string) => void;
  error?: string;
}

/** Renders nothing for a simple product or one with no generated variants yet. */
export function VariantPicker({ product, value, onChange, error }: VariantPickerProps) {
  if (!product || product.type !== "variable" || product.variants.length === 0) {
    return null;
  }

  return (
    <div className="w-48 space-y-1">
      <Select value={value} onValueChange={onChange}>
        <SelectTrigger aria-label="Select variant">
          <SelectValue placeholder="Select variant">
            {product.variants.find((v) => String(v.id) === value)?.sku}
          </SelectValue>
        </SelectTrigger>
        <SelectContent>
          {product.variants.map((v) => (
            <SelectItem key={v.id} value={String(v.id)}>
              {v.attribute_values.map((av) => av.value).join(" / ") || v.sku} ({v.sku})
            </SelectItem>
          ))}
        </SelectContent>
      </Select>
      {error ? <p className="text-xs text-danger">{error}</p> : null}
    </div>
  );
}
