"use client";

import type { ContentPanelProps } from "@/components/builder/panel-types";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import type { SpacerSettings } from "@/types/homepage-block";

export function SpacerPanel({ value, onChange }: ContentPanelProps<SpacerSettings>) {
  return (
    <div className="space-y-1.5">
      <Label htmlFor="spacer-height">Height (px)</Label>
      <Input
        id="spacer-height"
        type="number"
        min={8}
        max={400}
        value={value.height_px}
        onChange={(event) => onChange({ ...value, height_px: Number(event.target.value) })}
      />
    </div>
  );
}
