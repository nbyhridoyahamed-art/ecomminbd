"use client";

import { AutoManualPicker } from "@/components/builder/auto-manual-picker";
import type { ContentPanelProps } from "@/components/builder/panel-types";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { useAllBrands } from "@/hooks/use-brands";
import type { AutoManualBrandSettings } from "@/types/homepage-block";

export function BrandCarouselPanel({ value, onChange, storeId }: ContentPanelProps<AutoManualBrandSettings>) {
  const { data: brandsData } = useAllBrands(storeId);
  const options = (brandsData?.data ?? []).map((brand) => ({ id: brand.id, label: brand.name }));

  return (
    <div className="space-y-4">
      <div className="space-y-1.5">
        <Label htmlFor="brand-carousel-heading">Heading</Label>
        <Input id="brand-carousel-heading" value={value.heading} onChange={(event) => onChange({ ...value, heading: event.target.value })} />
      </div>
      <div className="space-y-1.5">
        <Label htmlFor="brand-carousel-limit">Number to show</Label>
        <Input
          id="brand-carousel-limit"
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
        selectedIds={value.brand_ids}
        onSelectedIdsChange={(brand_ids) => onChange({ ...value, brand_ids })}
        options={options}
        autoDescription="Shows your brands automatically."
      />
    </div>
  );
}
