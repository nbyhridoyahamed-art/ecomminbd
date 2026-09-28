"use client";

import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import type { HomepageBlockStyles } from "@/types/homepage-block";

interface AdvancedPanelProps {
  value: HomepageBlockStyles;
  onChange: (value: HomepageBlockStyles) => void;
}

/** The "Advanced" tab (spec section 58) — a real but deliberately small escape hatch: a custom CSS class, not a rules engine. */
export function AdvancedPanel({ value, onChange }: AdvancedPanelProps) {
  return (
    <div className="space-y-1.5">
      <Label htmlFor="advanced-custom-class">Custom CSS class</Label>
      <Input
        id="advanced-custom-class"
        placeholder="e.g. my-promo-block"
        value={value.custom_class ?? ""}
        onChange={(event) => onChange({ ...value, custom_class: event.target.value || null })}
      />
      <p className="text-xs text-text-muted">Applied to this block&apos;s wrapper element — useful for a one-off style from your theme&apos;s CSS.</p>
    </div>
  );
}
