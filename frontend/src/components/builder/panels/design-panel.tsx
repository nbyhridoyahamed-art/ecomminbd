"use client";

import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import type { HomepageBlockStyles } from "@/types/homepage-block";

interface DesignPanelProps {
  value: HomepageBlockStyles;
  onChange: (value: HomepageBlockStyles) => void;
}

/** The "Design" tab (spec section 58) — non-responsive visual tokens, shared by every block type. */
export function DesignPanel({ value, onChange }: DesignPanelProps) {
  return (
    <div className="space-y-4">
      <div className="grid grid-cols-2 gap-3">
        <div className="space-y-1.5">
          <Label htmlFor="design-bg-color">Background color</Label>
          <div className="flex items-center gap-2">
            <input
              type="color"
              className="size-9 shrink-0 rounded border border-border"
              value={value.background_color || "#ffffff"}
              onChange={(event) => onChange({ ...value, background_color: event.target.value })}
              aria-label="Pick background color"
            />
            <Input
              id="design-bg-color"
              value={value.background_color ?? ""}
              placeholder="inherit"
              onChange={(event) => onChange({ ...value, background_color: event.target.value || null })}
            />
          </div>
        </div>
        <div className="space-y-1.5">
          <Label htmlFor="design-text-color">Text color</Label>
          <div className="flex items-center gap-2">
            <input
              type="color"
              className="size-9 shrink-0 rounded border border-border"
              value={value.text_color || "#0f172a"}
              onChange={(event) => onChange({ ...value, text_color: event.target.value })}
              aria-label="Pick text color"
            />
            <Input
              id="design-text-color"
              value={value.text_color ?? ""}
              placeholder="inherit"
              onChange={(event) => onChange({ ...value, text_color: event.target.value || null })}
            />
          </div>
        </div>
      </div>
      <div className="space-y-1.5">
        <Label htmlFor="design-radius">Border radius</Label>
        <Input
          id="design-radius"
          placeholder="e.g. 12px"
          value={value.border_radius ?? ""}
          onChange={(event) => onChange({ ...value, border_radius: event.target.value || null })}
        />
      </div>
      <div className="space-y-1.5">
        <Label htmlFor="design-shadow">Box shadow</Label>
        <Input
          id="design-shadow"
          placeholder="e.g. 0 4px 12px rgba(0,0,0,0.08)"
          value={value.box_shadow ?? ""}
          onChange={(event) => onChange({ ...value, box_shadow: event.target.value || null })}
        />
      </div>
    </div>
  );
}
