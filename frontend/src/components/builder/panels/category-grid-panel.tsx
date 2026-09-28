"use client";

import { AutoManualPicker } from "@/components/builder/auto-manual-picker";
import type { ContentPanelProps } from "@/components/builder/panel-types";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { useCategories } from "@/hooks/use-categories";
import type { AutoManualCategorySettings } from "@/types/homepage-block";

export function CategoryGridPanel({ value, onChange, storeId }: ContentPanelProps<AutoManualCategorySettings>) {
  const { data: categories } = useCategories(storeId);
  const options = (categories ?? []).map((category) => ({ id: category.id, label: category.name }));

  return (
    <div className="space-y-4">
      <div className="space-y-1.5">
        <Label htmlFor="category-grid-heading">Heading</Label>
        <Input id="category-grid-heading" value={value.heading} onChange={(event) => onChange({ ...value, heading: event.target.value })} />
      </div>
      <div className="space-y-1.5">
        <Label htmlFor="category-grid-limit">Number to show</Label>
        <Input
          id="category-grid-limit"
          type="number"
          min={1}
          max={12}
          value={value.limit}
          onChange={(event) => onChange({ ...value, limit: Number(event.target.value) })}
        />
      </div>
      <AutoManualPicker
        mode={value.mode}
        onModeChange={(mode) => onChange({ ...value, mode })}
        selectedIds={value.category_ids}
        onSelectedIdsChange={(category_ids) => onChange({ ...value, category_ids })}
        options={options}
        autoDescription="Shows your top-level categories automatically."
      />
    </div>
  );
}
