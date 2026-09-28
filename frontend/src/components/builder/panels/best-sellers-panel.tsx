"use client";

import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import type { ContentPanelProps } from "@/components/builder/panel-types";
import type { LimitOnlySettings } from "@/types/homepage-block";

export function BestSellersPanel({ value, onChange }: ContentPanelProps<LimitOnlySettings>) {
  return (
    <div className="space-y-4">
      <div className="space-y-1.5">
        <Label htmlFor="best-sellers-heading">Heading</Label>
        <Input id="best-sellers-heading" value={value.heading} onChange={(event) => onChange({ ...value, heading: event.target.value })} />
      </div>
      <div className="space-y-1.5">
        <Label htmlFor="best-sellers-limit">Number to show</Label>
        <Input
          id="best-sellers-limit"
          type="number"
          min={1}
          max={24}
          value={value.limit}
          onChange={(event) => onChange({ ...value, limit: Number(event.target.value) })}
        />
      </div>
    </div>
  );
}
