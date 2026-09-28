"use client";

import type { ContentPanelProps } from "@/components/builder/panel-types";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import type { CustomCssSettings } from "@/types/homepage-block";

export function CustomCssPanel({ value, onChange }: ContentPanelProps<CustomCssSettings>) {
  return (
    <div className="space-y-1.5">
      <Label htmlFor="custom-css-code">CSS</Label>
      <Textarea
        id="custom-css-code"
        className="font-mono text-xs"
        rows={12}
        value={value.css ?? ""}
        onChange={(event) => onChange({ ...value, css: event.target.value || null })}
      />
      <p className="text-xs text-text-muted">Raw CSS, injected as-is on the storefront. Staff use only — never paste rules from an untrusted source.</p>
    </div>
  );
}
